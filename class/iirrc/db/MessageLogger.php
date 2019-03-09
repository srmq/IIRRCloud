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

class MessageLogger extends CSVLogger  {

    private $messageTypes;
    private $messageCodes;
    private $stopIrrigReasons;
    private $waterCurrSensorStatus;
    private $waterStartStatus;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->messageTypes = array(
            0 => 'MSG_DEBUG',
            1 => 'MSG_INFO',
            2 => 'MSG_WARN' ,
            3 => 'MSG_ERR'
        );

        $this->messageCodes = array(
            0 => 'MSG_INCONSIST_WATER_CURRSTATUS',
            1 => 'MSG_STOPPED_IRRIG',
            2 => 'MSG_STARTED_IRRIG'
        );

        $this->stopIrrigReasons = array(
            0 => 'STOPIRRIG_SLOTEND',
            1 => 'STOPIRRIG_SURFACESAT',
            2 => 'STOPIRRIG_MIDDLESAT',
            3 => 'STOPIRRIG_DEEPINCREASE',
            4 => 'STOPIRRIG_MAXTIMEDAY',
            5 => 'STOPIRRIG_WATEREMPTY'
        );

        $this->waterCurrSensorStatus = array(
            0 => 'WATER_CURRFLOWING',
            1 => 'WATER_CURRSTOP',
            2 => 'WATER_CURREMPTY',
            3 => 'WATER_CURRNOCONF'
        );

        $this->waterStartStatus = array(
            0 => 'WATER_STARTOK',
            1 => 'WATER_STARTNOACTION',
            2 => 'WATER_STARTEMPTY',
            3 => 'WATER_STARTNOCONF'
        );
    }

    public function parseLine(string $msgLine, int $lineNum) : array {
        $result = array(); 
        $n = sscanf($datalogLine, '%15s,%d,%d,%d,%d', $tsString, 
                $result['fkMessageType'], $result['fkMessageCode'], $par4,
                $par5);
        if ($n != 5) {
            throw new InvalidCSVLineException("Could not parse 5 arguments", $lineNum);
        }
        $rcvDate = date_create($tsString, new DateTimeZone('UTC'));
        if (is_null($rcvDate)) {
            throw new InvalidCSVLineException("Date is invalid", $lineNum);
        }

        if(!array_key_exists($result['fkMessageType'], $this->messageTypes)) {
            throw new InvalidCSVLineException("Invalid message type", $lineNum);
        }

        if(!array_key_exists($result['fkMessageCode'], $this->messageCodes)) {
            throw new InvalidCSVLineException("Invalid message code", $lineNum);
        }

        $result['reported_ts'] = $rcvDate->format('Y-m-d H:i:s');
        $result['fkStopIrrigReason'] = null;
        $result['fkExpWaterCurrSensorStatus'] = null;
        $result['fkDetWaterCurrSensorStatus'] = null;
        $result['fkExpWaterStartStatus'] = null;
        $result['fkDetWaterStartStatus'] = null;
        $result['fsUpdated'] = null;

        switch($this->messageCodes[$result['fkMessageCode']]) {
            case 'MSG_INCONSIST_WATER_CURRSTATUS':
                if(!array_key_exists($par4, $this->waterCurrSensorStatus)) {
                    throw new InvalidCSVLineException("Invalid ExpWaterCurrSensorStatus", $lineNum);
                }
                $result['fkExpWaterCurrSensorStatus'] = $par4;
                if(!array_key_exists($par5, $this->waterCurrSensorStatus)) {
                    throw new InvalidCSVLineException("Invalid DetWaterCurrSensorStatus", $lineNum);
                }
                $result['fkDetWaterCurrSensorStatus'] = $par5;
                break;
            case 'MSG_STOPPED_IRRIG':
                if(!array_key_exists($par4, $this->stopIrrigReasons)) {
                    throw new InvalidCSVLineException("Invalid StopIrrigReason", $lineNum);
                }
                $result['fkStopIrrigReason'] = $par4;
                if($par5 !== 0 && $par5 !== 1) {
                    throw new InvalidCSVLineException("Invalid fsUpdated", $lineNum);
                }
                $result['fsUpdated'] = $par5;
                break;
            case 'MSG_STARTED_IRRIG':
                if(!array_key_exists($par4, $this->waterStartStatus)) {
                    throw new InvalidCSVLineException("Invalid ExpWaterStartStatus", $lineNum);
                }
                $result['fkExpWaterStartStatus'] = $par4;
                if(!array_key_exists($par5, $this->waterStartStatus)) {
                    throw new InvalidCSVLineException("Invalid DetWaterStartStatus", $lineNum);
                }
                $result['fkDetWaterStartStatus'] = $par5;
                break;
            default:
                throw new InvalidCSVLineException("Invalid message code", $lineNum);
        }

        return $result;
    }

    public function getLastReportedTS(int $deviceId) : ?DateTime {
        $sql = 'SELECT reported_ts FROM tbMsgLog WHERE pk_tbDevice_id = ? ORDER BY reported_ts DESC LIMIT 1';
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
        $parsedData['pk_tbDevice_id'] = $deviceId;
        $parsedData['received_at'] = $rcvAt->format('Y-m-d H:i:s');
        $parsedData['origin_ip'] = $originIP;

        $sql = 'INSERT INTO tbMsgLog (pk_tbDevice_id, reported_ts, received_at, origin_ip, fkMessageType, fkMessageCode, fkStopIrrigReason, fkExpWaterCurrSensorStatus, fkDetWaterCurrSensorStatus, fkExpWaterStartStatus, fkDetWaterStartStatus, fsUpdated) '
                . 'VALUES (:pk_tbDevice_id, :reported_ts, :received_at, :origin_ip, :fkMessageType, :fkMessageCode, :fkStopIrrigReason, :fkExpWaterCurrSensorStatus, :fkDetWaterCurrSensorStatus, :fkExpWaterStartStatus, :fkDetWaterStartStatus, :fsUpdated)';
        $stmt = $this->pdo->prepare($sql);

        foreach ($parsedData as $key => &$val) {
            $stmt->bindParam(':' . $key, $val);
        }
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to insert line");
        }
    }

    public function getMaxAllowedLines() : int {
        return MAX_MSG_LINES;
    }


}

?>