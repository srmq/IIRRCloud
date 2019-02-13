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

use \InvalidArgumentException;
use \Throwable;

namespace iirrc\errors;

class InvalidCSVLineException extends InvalidArgumentException {
    private $lineNum;
    public function __construct ( string $message = "" , 
            int $lineNum, 
            Throwable $previous = NULL ) {
        $this->lineNum = $lineNum;
        $code = ExceptionCodes::getCode($this->class);
        parent::__construct($message, $code, $previous);
    }
    public function getLineNum() : int {
        return $this->lineNum;
    }
}

?>