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

abstract class AbstractRouteHandler implements RequestHandler {
    protected $args;
    protected $response;
    protected $container;

    public function __construct(array $args, Response $response, Container $container) {
        $this->args = $args;
        $this->response = $response;
        $this->container = $container;
    }

    public static function isCSVMedia(Request $request) : bool {
        return $request->getMediaType() === "text/csv";
    }

    public static function isInTheFuture(DateTime $dt) : bool {
        $nowMinusDt = $dt->diff(new DateTime('now', new DateTimeZone('UTC')));
        if ($nowMinusDt->s < -300) return true;
        return false;
    }


    public abstract function handle(Request $request): Response;

}

?>