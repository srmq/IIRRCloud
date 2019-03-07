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
use \InvalidArgumentException;
use \PDO;
use \DateTime;
use \DateTimeZone;

class AccountManager  {

    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function isAccountExpired(array $userAccount) : bool {
        $dtInFuture = function(DateTime $dt) : bool {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            return $dt >= $now;
        };
        return !empty($userAccount['ends_at']) && !($dtInFuture(new DateTime($userAccount['ends_at']), new DateTimeZone('UTC')));
    }

    public function getAccountForUserId(int $userId) : array {
        $sql = 'SELECT * FROM tbAccount WHERE tbUser_uid = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($userId))) {
            throw new IOException("input/output error when calling getAccountForUserId");
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function accountForUserExists(array $user) : bool {
        if(!array_key_exists('uid', $user)) {
            throw new InvalidArgumentException("user must have an uid");
        }

        $sql = 'SELECT tbUser_uid FROM tbAccount WHERE tbUser_uid = ? LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($user['uid']))) {
            throw new IOException("input/output error when calling userExists");
        }
        $uid = $stmt->fetch(PDO::FETCH_NUM);
        return !empty($uid);
    }


    public function setAccount(array $user, DateTime $startAt, int $maxDevices, DateTime $endsAt = NULL) {
        if(!array_key_exists('uid', $user)) {
            throw new InvalidArgumentException("user must have an uid");
        }
        if($this->accountForUserExists($user)) {
            $sql = 'UPDATE tbAccount SET (start_at = :start_at, ends_at = :ends_at, max_devices = :max_devices) WHERE tbUser_uid = :tbUser_uid';
        } else {
            $sql = 'INSERT INTO tbAccount (tbUser_uid, start_at, ends_at, max_devices) VALUES (:tbUser_uid, :start_at, :ends_at, :max_devices)';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tbUser_uid', $user['uid']);
        $stmt->bindValue(':start_at', $startAt->format('Y-m-d H:i:s'));
        $stmt->bindValue(':ends_at', ($endsAt == NULL) ? NULL : $endsAt->format('Y-m-d H:i:s'), ($endsAt == NULL) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':max_devices', $maxDevices);
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to setAccount");
        }

    }

    public function removeAccount(array $user) {
        if(!array_key_exists('uid', $user)) {
            throw new InvalidArgumentException("user must have an uid");
        }
        $sql = 'DELETE FROM tbAccount WHERE tbUser_uid = ?';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($user['uid']))) {
            throw new IOException("input/output error when calling removeAccount");
        }
    }

}

?>