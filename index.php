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
use Zikarsky\DataStructure\Buffer;

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

$app->post('/v100/datalog', function (Request $request, Response $response, array $args) {
    global $relay;

    $myHandler = new class($args, $response) extends AbstractRouteHandler {

        public function handle(Request $request): Response {
            $stream = $request->getBody();
            $dataBuf = new Buffer(BUFSIZE);
            while(!$stream->eof()) {
                if ($dataBuf->isFull()) {
                    //FIXME retorna erro do buffer cheio 
                } else {
                    $dataChunk = $stream->read(BUFSIZE - $dataBuf->count());
                    //XXX continuar copiar datachunk para buffer e consumir buffer
                }
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