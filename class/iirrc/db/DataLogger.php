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
use \DateTimeZone;
use \DateTime;

class DataLogger  {

    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function parseMoistureLine(string $datalogLine, int $lineNum) : array {
        $result = array();
        $n = sscanf($datalogLine, '%15s,%F,%F,%F,%d', $tsString, 
                $result['moist_surface'], $result['moist_middle'], $result['moist_deep'],
                $result['isIrrigating']);
        if ($n != 5) {
            throw new InvalidCSVLineException("Could not parse 5 arguments", $lineNum);
        }
        $rcvDate = date_create($tsString, new DateTimeZone('UTC'));
        if (is_null($rcvDate)) {
            throw new InvalidCSVLineException("Date is invalid", $lineNum);
        }
        $result['reported_ts'] = $rcvDate;
        return $result;
    }

    public function getDeviceId(string $macAddr) : int {
        
        $sql = 'SELECT id FROM tbDevice WHERE mac_id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($macAddr))) {
            throw new IOException("input/output error when trying to get device id");
        }
        $deviceId = $stmt->fetch(PDO::FETCH_NUM);
        $result = -1;
        if(!empty($deviceId)) {
            $result = $deviceId[0];
        }
        return $result;
    }

    public function getLastReportedTS(int $deviceId) : DateTime {
        $sql = 'SELECT reported_ts FROM tbIrrigLog WHERE tbDevice_id = ? ORDER BY reported_ts DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($deviceId))) {
            throw new IOException("input/output error when trying to get last reported TS");
        }
        $lastTS = $stmt->fetch(PDO::FETCH_NUM);
        $result = NULL;
        if(!empty($lastTS)) {
            $result = new DateTime($lastTS[0], new DateTimeZone('UTC'));
        }
        return $result;
    }

    public function insertLine(array &$parsedData, int $deviceId, DateTime $rcvAt, string $originIP) {
        //FIXME continue
    }

    //

}

?>