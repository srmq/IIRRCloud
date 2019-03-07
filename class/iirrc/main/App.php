<?php

/*
   This file is part of IIRRCloud.

    IIRRCloud is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    IIRRCloud is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with IIRRCloud.  If not, see <https://www.gnu.org/licenses/>
 */


declare(strict_types = 1);

namespace iirrc\main;

require_once('conf/config.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \iirrc\middlewares\DateAdder;
use \Relay\Relay;
use \iirrc\db\DataLogger;
use \iirrc\db\MessageLogger;
use \iirrc\db\DeviceManager;
use \iirrc\db\UnmodifiableDeviceArray;
use \iirrc\handlers\AbstractRouteHandler;
use \iirrc\handlers\CSVRouteHandler;
use \iirrc\util\RESTOpStatusCodes;
use \PDO;
use \DateTime;
use \DateTimeZone;
use \LogicException;
use \Exception;


class App {
    /**
     * Stores an instance of the Slim application.
     *
     * @var \Slim\App
     */
    private $app;
    private static $container;
    private static $relay;

    public static function getContainer() {
        return App::$container;
    }

    public static function getRelay() {
        return App::$relay;
    }

    public function __construct() {
        global $config;

        $this->app = new \Slim\App(['settings' => $config]);
        App::$container = $this->app->getContainer();
        App::$container['db'] = function ($c) {
            $db = $c['settings']['db'];
            $connString = 'mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'];
            $pdo = new PDO($connString,
                $db['user'], $db['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        };

        $queue[] = (new \Middlewares\DigestAuthentication(new UnmodifiableDeviceArray(App::getContainer()->db)))->attribute(USERNAME_ATTR);
        $queue[] = new \Middlewares\ClientIp();
        $queue[] = new DateAdder();
        $queue[] = new \Middlewares\RequestHandler();
                
        App::$relay = new Relay($queue);


        App::$container['logger'] = function($c) {
            $logger = new \Monolog\Logger('iirrc_logger');
            $file_handler = new \Monolog\Handler\StreamHandler('./logs/app.log');
            $logger->pushHandler($file_handler);
            return $logger;
        };
        
        
        $this->app->get('/hello/{name}', function (Request $request, Response $response, array $args) {    
            $myHandler = new class($args, $response, App::getContainer()) extends AbstractRouteHandler {
        
                public function handle(Request $request): Response {
                    $name = $this->args['name'];
                    $this->response->getBody()->write("Hello, $name from " 
                        . $request->getAttribute('client-ip') 
                        . " at " 
                        . $request->getAttribute('request-date')->format('D, d M Y H:i:s \G\M\T')
                        . " and username "
                        . $request->getAttribute(USERNAME_ATTR) . "\n" . var_dump(App::getContainer()->db)
                    );
                    return $this->response;    
                }
            };
        
            return App::getRelay()->handle($request->withAttribute('request-handler', $myHandler));
        });
        
        $this->app->post('/v100/datalog/send', function (Request $request, Response $response, array $args) {
        
            $dataLogger = new DataLogger(App::getContainer()->db);
        
            $myHandler = new CSVRouteHandler($dataLogger, $args, $response, App::getContainer()); 
        
            return App::getRelay()->handle($request->withAttribute('request-handler', $myHandler));
        });
        
        $this->app->post('/v100/msglog/send', function (Request $request, Response $response, array $args) {
        
            $msgLogger = new MessageLogger(App::getContainer()->db);
        
            $myHandler = new CSVRouteHandler($msgLogger, $args, $response, App::getContainer()); 
        
            return App::getRelay()->handle($request->withAttribute('request-handler', $myHandler));
        });

        $this->app->get('/v100/{type}/send-params', function(Request $request, Response $response, array $args) {
            $myHandler = new class($args, $response, App::getContainer()) extends AbstractRouteHandler {
                public function handle(Request $request): Response {
                    try {
                        $msgOrLogData = $this->args['type'];
        
                        $deviceMac = $request->getAttribute(USERNAME_ATTR);
                        if(!isset($deviceMac)) {
                            throw new LogicException("Request does not have device's macaddr");
                        }
        
                        if ($msgOrLogData == 'msglog') {
                            $csvLogger = new MessageLogger(App::getContainer()->db);
                        } else if ($msgOrLogData == 'datalog') {
                            $csvLogger = new DataLogger(App::getContainer()->db);
                        } else {
                            return $this->response->withStatus(404);
                        }
        
                        $deviceManager = new DeviceManager(App::getContainer()->db);
                        $deviceId = $deviceManager->getDeviceId($deviceMac);
                        unset($deviceManager);
                        if($deviceId === -1) {
                            throw new LogicException("Could not find device id for macaddr");
                        }
                        
                        $params = array();
        
                        $lastTS = $csvLogger->getLastReportedTS($deviceId);
                        $params['status'] = RESTOpStatusCodes::OK;
                        $params['last-ts'] = is_null($lastTS) ? null : $lastTS->format('D, d M Y H:i:s \G\M\T');
                        $params['maxlines'] = $csvLogger->getMaxAllowedLines();
                        $params['now'] = (new DateTime('now', new DateTimeZone('UTC')))->format('D, d M Y H:i:s \G\M\T');
                    } catch(Exception $ex) {
                        $classEx = get_class($ex);
                        App::getContainer()->logger->addError("Got {$classEx} at {$ex->getFile()} line {$ex->getLine()}. Message: {$ex->getMessage()}. Code: {$ex->getCode()}.", $ex->getTrace());
                        $params = array();
                        $params['status'] = RESTOpStatusCodes::ERR;
                        $params['errno'] = $ex->getCode();
                    }
                       
                    return $this->response->withJson($params);
                }
            };
            return App::getRelay()->handle($request->withAttribute('request-handler', $myHandler));
        });
    }

    /**
     * Get an instance of the application.
     *
     * @return \Slim\App
     */
    public function get() : \Slim\App
    {
        return $this->app;
    }    

}


?>