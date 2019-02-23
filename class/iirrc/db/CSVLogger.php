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

namespace iirrc\db;

use \PDO;
use \iirrc\errors\InvalidCSVLineException;
use \iirrc\errors\IOException;
use \DateTimeZone;
use \DateTime;

abstract class CSVLogger  {

    protected $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public abstract function parseLine(string $datalogLine, int $lineNum) : array;

    public abstract function getLastReportedTS(int $deviceId) : DateTime;

    public abstract function insertLine(array &$parsedData, int $deviceId, DateTime $rcvAt, string $originIP);

}

?>