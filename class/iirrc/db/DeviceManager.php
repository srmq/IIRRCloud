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

use \iirrc\errors\IOException;
use \PDO;

class DeviceManager  {

    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
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

}

?>