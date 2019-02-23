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

namespace iirrc\handlers;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use \Slim\Container;
use \DateTime;
use iirrc\errors\ExpectedCSVBodyException;
use iirrc\errors\IOException;
use iirrc\errors\InvalidCSVLineException;
use iirrc\db\CSVLogger;
use \LogicException;

require_once('conf/config.php');

class CSVRouteHandler extends AbstractRouteHandler {

    private $csvLogger;

    public function __construct(CSVLogger $csvLogger, array $args, Response $response, Container $container) {
        parent::__construct($args, $response, $container);
        $this->csvLogger = $csvLogger;
    }


    public function handle(Request $request): Response {
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
                throw new ExpectedCSVBodyException("Content-type is not CSV");
            }
            $stream = $request->getBody();
            $dataToProcess = "";
            $lineNum = 0;
            $deviceManager = new DeviceManager($container->db);
            $deviceId = $deviceManager->getDeviceId($deviceMac);
            unset($deviceManager);
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
                    if ($lineNum > MAXLINES) {
                        throw new InvalidCSVLineException("More than " . MAXLINES . " in request", lineNum);
                    }
                    $dataLine = substr($dataToProcess, 0, $nlPos);
                    $parsedData = $this->csvLogger->parseLine($dataLine, $lineNum);
                    if (AbstractRouteHandler::isInTheFuture($parsedData['reported_ts'])) {
                        throw new InvalidCSVLineException("Reported date in the future", $lineNum);
                    }
                    if(isset($lastOk)) {
                        $currentMinusLast = $lastOk['reported_ts']->diff($parsedData['reported_ts']);
                        if ($currentMinusLast->s < 0) {
                            throw new InvalidCSVLineException("Unordered data", $lineNum);
                        }
                    }
                    if($checkLastReported) {
                        $lastInsertedDB = $this->csvLogger->getLastReportedTS($deviceId);
                        if(!is_null($lastInsertedDB)) {
                            $currentMinusLast = $lastInsertedDB->diff($parsedData['reported_ts']);
                            if ($currentMinusLast->s < 0) {
                                throw new InvalidCSVLineException("Line ts is before last inserted data", $lineNum);
                            }
                        }
                        $checkLastReported = false;
                    }
                    //$parsedData passed all tests, now insert it to db
                    $this->csvLogger->insertLine($parsedData, $deviceId, $receivedAt, $originIP);
                    
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
            $result = array();
            if(strlen($dataToProcess) > 0) {
                $result['status'] = RESTOpStatusCodes::WARN;
            } else {
                $result['status'] = RESTOpStatusCodes::OK;
            }
            $result['numprocess'] = $lineNum;
            $this->response->getBody()->write(json_encode($result));
        } catch(InvalidCSVLineException $ex) {
            $result = array();
            $result['status'] = RESTOpStatusCodes::ERR;
            $result['errno'] = $ex->getCode();
            $result['errline'] = $ex->getLineNum();
            $this->response->getBody()->write(json_encode($result));
        } catch(Exception $ex) {
            $result = array();
            $result['status'] = RESTOpStatusCodes::ERR;
            $result['errno'] = $ex->getCode();
            $this->response->getBody()->write(json_encode($result));                
        }

        return $this->response->withHeader('Content-Type', 'application/json');   
    }

}

?>