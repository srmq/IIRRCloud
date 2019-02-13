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

abstract class AbstractRouteHandler implements RequestHandler {
    protected $args;
    protected $response;

    public function __construct(array $args, Response $response) {
        $this->args = $args;
        $this->response = $response;
    }

    public static function isCSVMedia(Request $request) : boolean {
        return $request->getMediaType() === "text/csv";
    }

    public abstract function handle(Request $request): Response;

}

?>