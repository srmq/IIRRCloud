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
use \iirrc\errors\AccountFullException;
use \InvalidArgumentException;
use \PDO;

class DeviceManager  {

    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }


    public function updateDevice(array $device) {
        $exIfNotExists = function(array $arr, array $keys) {
            foreach($keys as $key => $val) {
                if (!array_key_exists($key, $arr)) {
                    throw new InvalidArgumentException("New device must have {$key}");        
                }
            }
        };
        $exIfNotExists($device, array_fill_keys(array('id', 'mac_id', 'login', 'password'), ''));
        $sql = 'UPDATE tbDevice SET mac_id=:mac_id, login=:login, tbAccount_tbUser_uid = :tbAccount_tbUser_uid, password=:password, name=:name, model=:model, manufact_dt=:manufact_dt, fst_activation=:fst_activation, retired_at=:retired_at WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        foreach ($device as $key => &$val) {
            $stmt->bindParam(':' . $key, $val);
        }
        $optional = array_fill_keys(array('tbAccount_tbUser_uid', 'name', 'model', 'manufact_dt', 'fst_activation', 'retired_at'), '');
        foreach($optional as $key => $val) {
            if(!array_key_exists($key, $device)) {
                $stmt->bindValue(':' . $key, null, PDO::PARAM_NULL);
            } 
        }
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to updateDevice");
        }

    }


    public function insertNewDevice(array &$device) {
        if(array_key_exists('id', $device)) {
            throw new InvalidArgumentException("New device should not already have id");
        }

        $exIfNotExists = function(array $arr, array $keys) {
            foreach($keys as $key => $val) {
                if (!array_key_exists($key, $arr)) {
                    throw new InvalidArgumentException("New device must have {$key}");        
                }
            }
        };
        $exIfNotExists($device, array_fill_keys(array('mac_id', 'password'), ''));
        $sql = 'INSERT INTO tbDevice (mac_id, login, password, name, model, manufact_dt, fst_activation, retired_at) VALUES (:mac_id, :login, :password, :name, :model, :manufact_dt, :fst_activation, :retired_at)';
        $stmt = $this->pdo->prepare($sql);
        foreach ($device as $key => &$val) {
            $stmt->bindParam(':' . $key, $val);
        }
        $optional = array_fill_keys(array('name', 'model', 'manufact_dt', 'fst_activation', 'retired_at'), '');
        foreach($optional as $key => $val) {
            if(!array_key_exists($key, $device)) {
                $stmt->bindValue(':' . $key, null, PDO::PARAM_NULL);
            } 
        }
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to insert new device");
        }
        $device['id'] = $this->pdo->lastInsertId();

    }

    public function getDeviceId(string $macAddr) : int {
        
        $sql = 'SELECT id FROM tbDevice WHERE mac_id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($macAddr))) {
            throw new IOException("input/output error when trying to get device id");
        }
        $deviceId = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        $result = -1;
        if(!empty($deviceId)) {
            $result = (int)$deviceId[0];
        }
        return $result;
    }

    public function getDeviceIdByLogin(string $login) : int {
        
        $sql = 'SELECT id FROM tbDevice WHERE login = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($login))) {
            throw new IOException("input/output error when trying to get device id");
        }
        $deviceId = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        $result = -1;
        if(!empty($deviceId)) {
            $result = (int)$deviceId[0];
        }
        return $result;
    }


    public function getDeviceByMac(string $macAddr) : array {
        $sql = 'SELECT * FROM tbDevice WHERE mac_id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($macAddr))) {
            throw new IOException("input/output error when trying to getDeviceByMac");
        }
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $device;
    }

    public function getDeviceByLogin(string $login) : array {
        $sql = 'SELECT * FROM tbDevice WHERE login = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($login))) {
            throw new IOException("input/output error when trying to getDeviceByLogin");
        }
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $device;
    }


    public function deleteDevice(array &$device) {
        if(!array_key_exists('id', $device)) {
            throw new InvalidArgumentException("Device must have an id");
        }
        $sql = 'DELETE FROM tbDevice WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($device['id']))) {
            throw new IOException("input/output error when calling deleteDevice");
        }

        unset($device['id']);
    }

    public function devicesIdForUserWithId(int $uid) : array {
        $sql = 'SELECT id FROM tbDevice WHERE tbAccount_tbUser_uid = ? AND retired_at IS NULL';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($uid))) {
            throw new IOException("input/output error when trying to call devicesIdForUserWithId");
        }
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        return $result;
    }

    public function associateToAccount(array $user, array $device, AccountManager $accountM) : void {
        if(!array_key_exists('id', $device)) {
            throw new InvalidArgumentException("device must have an id");
        }

        $userAccount = $accountM->getAccountForUserId((int)$user['uid']);
        if(empty($userAccount)) {
            throw new InvalidArgumentException("User does not have a valid account");
        }
        $deviceIdsForUser = $this->devicesIdForUserWithId((int)$user['uid']);
        $remainingDevices = $userAccount['max_devices'] - count($deviceIdsForUser);
        if (in_array($device['id'], $deviceIdsForUser)) {
            $remainingDevices++;
        }
        if ($remainingDevices <= 0) {
            throw new AccountFullException("Account is already full with " . count($deviceIdsForUser) . " devices");
        }

        $sql = 'UPDATE tbDevice SET tbAccount_tbUser_uid = :tbAccount_tbUser_uid WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tbAccount_tbUser_uid', $user['uid']);
        $stmt->bindParam(':id', $device['id']);
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to associateToAccount");
        }

    }

    public function clearAccountAssociation(array $device) : void {
        $sql = 'UPDATE tbDevice SET tbAccount_tbUser_uid = :tbAccount_tbUser_uid WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tbAccount_tbUser_uid', NULL, PDO::PARAM_NULL);
        $stmt->bindParam(':id', $device['id']);
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to associateToAccount");
        }
    }


}

?>