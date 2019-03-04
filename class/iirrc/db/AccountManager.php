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

class AccountManager  {

    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function accountForUserExists(array $user) : boolean {
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


    public function setAccount(array $user, DateTime $startAt, DateTime $endsAt = NULL, int $maxDevices) {
        if(!array_key_exists('uid', $user)) {
            throw new InvalidArgumentException("user must have an uid");
        }
        if($this->accountForUserExists($user)) {
            $sql = 'UPDATE tbAccount SET (start_at = :start_at, ends_at = :ends_at, max_devices = :max_devices) WHERE tbUser_uid = :tbUser_uid';
        } else {
            $sql = 'INSERT INTO tbAccount(tbUser_uid, start_at, ends_at, max_devices) VALUES (:tbUser_uid, :start_at, :ends_at, :max_devices)';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tbUser_uid', $user['uid']);
        $stmt->bindParam(':start_at', $startAt);
        $stmt->bindParam(':ends_at', $endsAt);
        $stmt->bindParam(':max_devices', $maxDevices);
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to setAccount");
        }

    }

}

?>