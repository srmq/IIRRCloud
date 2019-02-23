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

namespace iirrc\util;

use \Psr\Http\Message\ResponseInterface as Response;


abstract class HTTPUtil {
    public static function parseAuthHeader(Response $response) : array {
        $result = null;
        $headers = $response->getHeaders();
        if (array_key_exists('WWW-Authenticate', $headers)) {
            $authContent = $headers['WWW-Authenticate'];
            if(!empty($authContent)) {
                $authString = $authContent[0];
                $authData = explode(',', $authString, 10);
                $result = array();
                foreach ($authData as $authElem) {
                    list($key, $val) = explode('=', $authElem, 2);
                    $result[$key] = trim($val, " \t\n\r\0\x0B\"");
                }
            }
        }
        return $result;
    }
}



?>