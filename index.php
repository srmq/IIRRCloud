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

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use \iirrc\middlewares\DateAdder;
use \iirrc\handlers\AbstractRouteHandler;
use Relay\Relay;
use iirrc\errors\ExpectedCSVBodyException;
use iirrc\errors\IOException;
use iirrc\errors\InvalidCSVLineException;
use iirrc\db\DataLogger;

require_once('vendor/autoload.php');
require_once('conf/config.php');

abstract class RESTOpStatusCodes {
    const OK = 0;
}

define('BUFSIZE', 1024);

$queue[] = (new Middlewares\DigestAuthentication([
    'username1' => 'password1',
    'username2' => 'password2'
]))->attribute(USERNAME_ATTR);
$queue[] = new Middlewares\ClientIp();
$queue[] = new DateAdder();
$queue[] = new Middlewares\RequestHandler();

$relay = new Relay($queue);

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();
$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    global $relay;

    $myHandler = new class($args, $response) extends AbstractRouteHandler {

        public function handle(Request $request): Response {
            global $container;
            $name = $this->args['name'];
            $this->response->getBody()->write("Hello, $name from " 
                . $request->getAttribute('client-ip') 
                . " at " 
                . $request->getAttribute('request-date')->format('D, d M Y H:i:s \G\M\T')
                . " and username "
                . $request->getAttribute(USERNAME_ATTR) . "\n" . var_dump($container->db)
            );
            return $this->response;    
        }
    };

    return $relay->handle($request->withAttribute('request-handler', $myHandler));
});

$app->post('/v100/datalog/send', function (Request $request, Response $response, array $args) {
    global $relay;

    $myHandler = new class($args, $response) extends AbstractRouteHandler {

        private function isInTheFuture(DateTime $dt) : boolean {
            $nowMinusDt = $dt->diff(new DateTime('now', new DateTimeZone('UTC')));
            if ($nowMinusDt->s < -300) return true;
            return false;
        }

        public function handle(Request $request): Response {
            global $container;
            try {
                $deviceMac = $request->getAttribute(USERNAME_ATTR);
                if(!isset($deviceMac)) {
                    throw new LogicException("Request does not have device's macaddr");
                }
                $originIP = $request->getAttribute('client-ip');
                if(!isset($originIP)) {
                    throw new LogicException("Request does not have origin IP");
                }
                if (!AbstractRouteHandler::isCSVMedia($request)) {
                    throw new ExpectedCSVBodyException();
                }
                $stream = $request->getBody();
                $dataToProcess = "";
                $lineNum = 0;
                $dataLogger = new DataLogger($container->db);
                $deviceId = $dataLogger->getDeviceId($deviceMac);
                if($deviceId === -1) {
                    throw new LogicException("Could not find device id for macaddr");
                }
                $checkLastReported = true;
                $receivedAt = new DateTime('now', new DateTimeZone('UTC'));
                while(!$stream->eof()) {
                    $dataChunk = $stream->read(BUFSIZE - strlen($dataToProcess));
                    $dataChunk = strtr($dataChunk, array('\r' => ''));
                    $dataToProcess .= $dataChunk;
                    unset($dataChunk);
                    while (($nlPos = strpos($dataToProcess, '\n')) >= 0) {
                        $lineNum++;
                        $dataLine = substr($dataToProcess, 0, $nlPos);
                        $parsedData = $dataLogger->parseMoistureLine($dataLine, $lineNum);
                        if (isInTheFuture($parsedData['reported_ts'])) {
                            throw new InvalidCSVLineException("Reported date in the future", $lineNum);
                        }
                        if(isset($lastOk)) {
                            $currentMinusLast = $lastOk['reported_ts']->diff($parsedData['reported_ts']);
                            if ($currentMinusLast->s < 0) {
                                throw new InvalidCSVLineException("Unordered data", $lineNum);
                            }
                        }
                        if($checkLastReported) {
                            $lastInsertedDB = $dataLogger->getLastReportedTS($deviceId);
                            if(!is_null($lastInsertedDB)) {
                                $currentMinusLast = $lastInsertedDB->diff($parsedData['reported_ts']);
                                if ($currentMinusLast->s < 0) {
                                    throw new InvalidCSVLineException("Line ts is before last inserted data", $lineNum);
                                }
                            }
                            $checkLastReported = false;
                        }
                        //$parsedData passed all tests, now insert it to db
                        $dataLogger->insertLine($parsedData, $deviceId, $receivedAt, $originIP);
                        
                        //checar se dataLine é valida se nao for, dar erro, retornar
                        //lembrar que para ser valido tem que estar ordenado e não estar no futuro
                        //tem que ser maior que ultima recebida também
                        //quantos registros foram adicionados bem como ts do último
                        //valido -> insere no bd 
                        //se ultrapassar numero de registros maximo por request, também reclamar

                        $lastOk = $parsedData;
                        $dataToProcess = substr($dataToProcess, $nlPos + 1);
                        if ($dataToProcess === false) {
                            $dataToProcess = "";
                        }
                    } 
                    if(strlen($dataToProcess) >= BUFSIZE) {
                            throw new InvalidCSVLineException("Line too long", $lineNum);
                    }
                }
                if(strlen($dataToProcess) > 0) {
                    //warning, last line without \n, will be ignored
                } else {
                    //all ok
                }
            } catch(ExpectedCSVBodyException $ex) {

            } catch(InvalidCSVLineException $ex) {

            } catch(LogicException $ex) {

            } catch(IOException $ex) {

            } catch(Exception $ex) {
                
            }
            return $this->response;    
        }
    };

    return $relay->handle($request->withAttribute('request-handler', $myHandler));
});

$app->run();

?>