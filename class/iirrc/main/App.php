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
use \iirrc\handlers\AbstractRouteHandler;
use \iirrc\handlers\CSVRouteHandler;
use \PDO;



class App {
    /**
     * Stores an instance of the Slim application.
     *
     * @var \Slim\App
     */
    private $app;
    protected static $container;
    protected static $relay;

    public function __construct() {
        global $config;
        $queue[] = (new \Middlewares\DigestAuthentication([
            'username1' => 'password1',
            'username2' => 'password2'
        ]))->attribute(USERNAME_ATTR);
        $queue[] = new \Middlewares\ClientIp();
        $queue[] = new DateAdder();
        $queue[] = new \Middlewares\RequestHandler();
                
        App::$relay = new Relay($queue);

        $this->app = new \Slim\App(['settings' => $config]);
        App::$container = $this->app->getContainer();
        App::$container['db'] = function ($c) {
            $db = $c['settings']['db'];
            $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
                $db['user'], $db['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        };
        
        $this->app->get('/hello/{name}', function (Request $request, Response $response, array $args) {    
            $myHandler = new class($args, $response, App::$container) extends AbstractRouteHandler {
        
                public function handle(Request $request): Response {
                    $name = $this->args['name'];
                    $this->response->getBody()->write("Hello, $name from " 
                        . $request->getAttribute('client-ip') 
                        . " at " 
                        . $request->getAttribute('request-date')->format('D, d M Y H:i:s \G\M\T')
                        . " and username "
                        . $request->getAttribute(USERNAME_ATTR) . "\n" . var_dump($this->container->db)
                    );
                    return $this->response;    
                }
            };
        
            return App::$relay->handle($request->withAttribute('request-handler', $myHandler));
        });
        
        $this->app->post('/v100/datalog/send', function (Request $request, Response $response, array $args) {
        
            $dataLogger = new DataLogger(App::$container->db);
        
            $myHandler = new CSVRouteHandler($dataLogger, $args, $response, $App::container); 
        
            return App::$relay->handle($request->withAttribute('request-handler', $myHandler));
        });
        
        $this->app->post('/v100/msglog/send', function (Request $request, Response $response, array $args) {
        
            $msgLogger = new MessageLogger(App::$container->db);
        
            $myHandler = new CSVRouteHandler($msgLogger, $args, $response, App::$container); 
        
            return App::$relay->handle($request->withAttribute('request-handler', $myHandler));
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