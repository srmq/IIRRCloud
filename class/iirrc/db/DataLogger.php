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

require_once('conf/config.php');

use \PDO;
use \iirrc\errors\InvalidCSVLineException;
use \iirrc\errors\IOException;
use \DateTimeZone;
use \DateTime;

class DataLogger extends CSVLogger {

    public function parseLine(string $datalogLine, int $lineNum) : array {
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

    public function getLastReportedTS(int $deviceId) : ?DateTime {
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
        $parsedData['tbDevice_id'] = $deviceId;
        $parsedData['received_at'] = $rcvAt->format('Y-m-d H:i:s');
        $parsedData['origin_ip'] = $originIP;

        $sql = 'INSERT INTO tbIrrigLog (tbDevice_id, reported_ts, received_at, origin_ip, moist_surface, moist_middle, moist_deep, isIrrigating) '
                . 'VALUES (:tbDevice_id, :reported_ts, :received_at, :origin_ip, :moist_surface, :moist_middle, :moist_deep, :isIrrigating)';
        $stmt = $this->pdo->prepare($sql);

        foreach ($parsedData as $key => &$val) {
            $stmt->bindParam(':' . $key, $val);
        }
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to insert line");
        }
    }

    public function getMaxAllowedLines() : int {
        return MAX_LOG_LINES;
    }

}

?>