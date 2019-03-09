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

class UserManager  {

    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function userExists(array $user) : bool {
        if(array_key_exists('uid', $user)) {
            $sql = 'SELECT uid FROM tbUser WHERE uid = ? LIMIT 1';
            $val = $user['uid'];            
        } else if(array_key_exists('email', $user)) {
            $sql = 'SELECT uid FROM tbUser WHERE email = ? LIMIT 1';
            $val = $user['email'];            
       }
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($val))) {
            throw new IOException("input/output error when calling userExists");
        }
        $uid = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        return !empty($uid);
    }

    public function insertNewUser(array &$user) : void {
        if(array_key_exists('uid', $user)) {
            throw new InvalidArgumentException("New user should not already have uid");
        }
        $exIfNotExists = function(array $arr, array $keys) {
            foreach($keys as $key => $val) {
                if (!array_key_exists($key, $arr)) {
                    throw new InvalidArgumentException("New user must have {$key}");        
                }
            }
        };
        $exIfNotExists($user, array_fill_keys(array('name', 'surname', 'email', 'password'), ''));

        $sql = 'INSERT INTO tbUser (name, surname, email, password) VALUES (:name, :surname, :email, :password)';
        $stmt = $this->pdo->prepare($sql);

        foreach ($user as $key => &$val) {
            $stmt->bindParam(':' . $key, $val);
        }
        if(!$stmt->execute()) {
            throw new IOException("input/output error when trying to insert new user");
        }
        $user['uid'] = $this->pdo->lastInsertId();
    }

    public function deleteUser(array &$user) {
        if(!array_key_exists('uid', $user)) {
            throw new InvalidArgumentException("User must have an uid");
        }
        $sql = 'DELETE FROM tbUser WHERE uid = ?';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($user['uid']))) {
            throw new IOException("input/output error when calling deleteUser");
        }

        unset($user['uid']);
    }
}

?>