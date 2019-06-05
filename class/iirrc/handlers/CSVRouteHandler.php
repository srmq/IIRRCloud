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
use \DateTimeZone;
use iirrc\errors\ExpectedCSVBodyException;
use iirrc\errors\IOException;
use iirrc\errors\InvalidCSVLineException;
use iirrc\errors\InvalidUserAccountException;
use iirrc\db\CSVLogger;
use iirrc\db\UserManager;
use iirrc\db\AccountManager;
use iirrc\db\DeviceManager;
use \LogicException;
use iirrc\main\App;
use iirrc\util\RESTOpStatusCodes;

require_once('conf/config.php');

class CSVRouteHandler extends AbstractRouteHandler {

    private $csvLogger;

    public function __construct(CSVLogger $csvLogger, array $args, Response $response, Container $container) {
        parent::__construct($args, $response, $container);
        $this->csvLogger = $csvLogger;
    }


    public function handle(Request $request): Response {
        App::getContainer()->logger->addDebug("Invoked CSVRouteHandler.handle()");
        try {
            $deviceLogin = $request->getAttribute(USERNAME_ATTR);
            if(!isset($deviceLogin)) {
                App::getContainer()->logger->addDebug("Request does not have device's login, will throw LogicException");
                throw new LogicException("Request does not have device's login");
            }
            $originIP = $request->getAttribute('client-ip');
            if(!isset($originIP)) {
                App::getContainer()->logger->addDebug("Request does not have client-ip, will throw LogicException");
                throw new LogicException("Request does not have origin IP");
            }
            if (!AbstractRouteHandler::isCSVMedia($request)) {
                App::getContainer()->logger->addDebug("Request Content-type is not CSV, will throw ExpectedCSVBodyException");
                throw new ExpectedCSVBodyException("Content-type is not CSV");
            }
            $stream = $request->getBody();
            $dataToProcess = "";
            $lineNum = 0;
            $deviceManager = new DeviceManager($this->container->db);
            $device = $deviceManager->getDeviceByLogin($deviceLogin);
            $deviceId = (int)$device['id'];
            if($deviceId === -1) {
                App::getContainer()->logger->addDebug("Could not find device id for login: {$deviceLogin}, will throw LogicException");
                throw new LogicException("Could not find device id for login: {$deviceLogin}");
            }
            if(!empty($device['tbAccount_tbUser_uid'])) {
                $accountManager = new AccountManager($this->container->db);
                $userAccount = $accountManager->getAccountForUserId((int)$device['tbAccount_tbUser_uid']);
                if(empty($userAccount)) {
                    App::getContainer()->logger->addDebug("Device refers to user account that does not exist (id: {$device['tbAccount_tbUser_uid']}), will throw LogicException");
                    throw new LogicException("Reference to user account that do not exist (id: {$device['tbAccount_tbUser_uid']})");
                }
                if($accountManager->isAccountExpired($userAccount)) {
                    App::getContainer()->logger->addDebug("Account expired for device, will throw InvalidUserAccountException");
                    throw new InvalidUserAccountException("Account expired for device");
                }
            } else {
                App::getContainer()->logger->addDebug("No user account for device, will throw InvalidUserAccountException");
                throw new InvalidUserAccountException("No user account for device");
            }

            $checkLastReported = true;
            $receivedAt = new DateTime('now', new DateTimeZone('UTC'));
            if(empty($device['fst_activation'])) {
                $device['fst_activation'] = $receivedAt->format('Y-m-d H:i:s');
                $deviceManager->updateDevice($device);
            }
            unset($device);
            unset($deviceManager);
            App::getContainer()->logger->addDebug("CSVRouteHandler.handle() started processing Request stream");
            while(!$stream->eof()) {
                $dataChunk = $stream->read(BUFSIZE - strlen($dataToProcess));
                $dataChunk = strtr($dataChunk, array("\r" => ''));
                $dataToProcess .= $dataChunk;
                App::getContainer()->logger->addDebug("Data do process is now: \"{$dataToProcess}\"");
                unset($dataChunk);
                while (($nlPos = strpos($dataToProcess, "\n"))) {
                    $lineNum++;
                    if ($lineNum > MAXLINES) {
                        App::getContainer()->logger->addDebug("More than " . MAXLINES . " in request, will throw InvalidCSVLineException");
                        throw new InvalidCSVLineException("More than " . MAXLINES . " in request", lineNum);
                    }
                    $dataLine = substr($dataToProcess, 0, $nlPos);
                    $parsedData = $this->csvLogger->parseLine($dataLine, $lineNum);
                    if (AbstractRouteHandler::isInTheFuture(new DateTime($parsedData['reported_ts'], new DateTimeZone('UTC')))) {
                        App::getContainer()->logger->addDebug("CSV line with date in the future, will throw InvalidCSVLineException");
                        throw new InvalidCSVLineException("Reported date in the future", $lineNum);
                    }
                    if(isset($lastOk)) {
                        $currentMinusLast = (new DateTime($lastOk['reported_ts'], new DateTimeZone('UTC')))->diff(new DateTime($parsedData['reported_ts'], new DateTimeZone('UTC')));
                        if ($currentMinusLast->invert == 1) {
                            App::getContainer()->logger->addDebug("CSV Stream with unordered data, will throw InvalidCSVLineException");
                            throw new InvalidCSVLineException("Unordered data", $lineNum);
                        }
                    }
                    if($checkLastReported) {
                        $lastInsertedDB = $this->csvLogger->getLastReportedTS($deviceId);
                        if(!is_null($lastInsertedDB)) {
                            $currentMinusLast = $lastInsertedDB->diff(new DateTime($parsedData['reported_ts'], new DateTimeZone('UTC')));
                            if ($currentMinusLast->invert == 1) {
                                App::getContainer()->logger->addDebug("CSV Stream with line ts before last inserted data, will throw InvalidCSVLineException");
                                throw new InvalidCSVLineException("Line ts is before last inserted data", $lineNum);
                            }
                        }
                        $checkLastReported = false;
                    }
                    //$parsedData passed all tests, now insert it to db
                    App::getContainer()->logger->addDebug("CSV stream line passed tests, will now try to insert it into DB");
                    $this->csvLogger->insertLine($parsedData, $deviceId, $receivedAt, $originIP);
                    
                    $lastOk = $parsedData;
                    $dataToProcess = substr($dataToProcess, $nlPos + 1);
                    if ($dataToProcess === false) {
                        $dataToProcess = "";
                    }
                }
                if(strlen($dataToProcess) >= BUFSIZE) {
                    App::getContainer()->logger->addDebug("CSV Stream line is too long, will throw InvalidCSVLineException");
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
            $classEx = get_class($ex);
            App::getContainer()->logger->addError("Got {$classEx} at {$ex->getFile()} line {$ex->getLine()}. CSVLine: {$ex->getLineNum()}. Message: {$ex->getMessage()}. Code: {$ex->getCode()}.", $ex->getTrace());
            $result = array();
            $result['status'] = RESTOpStatusCodes::ERR;
            $result['errno'] = $ex->getCode();
            $result['errline'] = $ex->getLineNum();
            $this->response->getBody()->write(json_encode($result));
        } catch(Exception $ex) {
            $classEx = get_class($ex);
            App::getContainer()->logger->addError("Got {$classEx} at {$ex->getFile()} line {$ex->getLine()}. Message: {$ex->getMessage()}. Code: {$ex->getCode()}.", $ex->getTrace());
            $result = array();
            $result['status'] = RESTOpStatusCodes::ERR;
            $result['errno'] = $ex->getCode();
            $this->response->getBody()->write(json_encode($result));                
        }

        return $this->response->withHeader('Content-Type', 'application/json');   
    }

}

?>