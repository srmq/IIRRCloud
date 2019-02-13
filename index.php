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

require_once('vendor/autoload.php');
require_once('conf/config.php');

abstract class OpStatusCodes {
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

$app = new \Slim\App;
$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    global $relay;

    $myHandler = new class($args, $response) extends AbstractRouteHandler {

        public function handle(Request $request): Response {
            $name = $this->args['name'];
            $this->response->getBody()->write("Hello, $name from " 
                . $request->getAttribute('client-ip') 
                . " at " 
                . $request->getAttribute('request-date')->format('D, d M Y H:i:s \G\M\T')
                . " and username "
                . $request->getAttribute(USERNAME_ATTR)
            );
            return $this->response;    
        }
    };

    return $relay->handle($request->withAttribute('request-handler', $myHandler));
});

$app->post('/v100/datalog/send', function (Request $request, Response $response, array $args) {
    global $relay;

    $myHandler = new class($args, $response) extends AbstractRouteHandler {

        public function handle(Request $request): Response {
            try {
                if (!AbstractRouteHandler::isCSVMedia($request)) {
                    throw new ExpectedCSVBodyException();
                }
                $stream = $request->getBody();
                $dataToProcess = "";
                while(!$stream->eof()) {
                    $dataChunk = $stream->read(BUFSIZE - strlen($dataToProcess));
                    $dataChunk = strtr($dataChunk, array('\r' => ''));
                    $dataToProcess .= $dataChunk;
                    unset($dataChunk);
                    while (($nlPos = strpos($dataToProcess, '\n')) >= 0) {
                        $dataLine = substr($dataToProcess, 0, $nlPos);
                        //checar se dataLine é valida se nao for, dar erro, retornar
                        //lembrar que para ser valido tem que estar ordenado e não estar no futuro
                        //tem que ser maior que ultima recebida também
                        //quantos registros foram adicionados bem como ts do último
                        //valido -> insere no bd 
                        //se ultrapassar numero de registros maximo por request, também reclamar
                        $dataToProcess = substr($dataToProcess, $nlPos + 1);
                        if ($dataToProcess === false) {
                            $dataToProcess = "";
                        }
                    } 
                    if(strlen($dataToProcess) >= BUFSIZE) {
                            //FIXME retorna erro de linha muito longa
                    }
                }
                if(strlen($dataToProcess) > 0) {
                    //se for valida processa, ultima linha sem \n
                }
            } catch(ExpectedCSVBodyException $ex) {

            }
            /*$this->response->getBody()->write("Hello, $name from " 
                . $request->getAttribute('client-ip') 
                . " at " 
                . $request->getAttribute('request-date')->format('D, d M Y H:i:s \G\M\T')
                . " and username "
                . $request->getAttribute(USERNAME_ATTR)
            );*/
            return $this->response;    
        }
    };

    return $relay->handle($request->withAttribute('request-handler', $myHandler));
});

$app->run();

?>