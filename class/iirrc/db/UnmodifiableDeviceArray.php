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
use \iirrc\errors\IOException;
use \iirrc\errors\UnsupportedOperationException;
use \ArrayAccess;

class UnmodifiableDeviceArray implements ArrayAccess {

    protected $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function offsetExists ( $login ) : bool {
        if(!is_string($login)) {
            $login = strval($login);
        }
        $sql = 'SELECT mac_id FROM tbDevice WHERE login = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($login))) {
            throw new IOException("input/output error when calling offsetExists");
        }
        $macId = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        return !empty($macId);

    }

    public function offsetGet ( $login ) {
        $result = null;
        $sql = 'SELECT password FROM tbDevice WHERE login = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(array($login))) {
            throw new IOException("input/output error when calling offsetGet");
        }
        $macPass = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!empty($macPass)) {
            $result = $macPass[0];
        }

        return $result;
    }

    public function offsetSet ( $offset , $value ) {
        throw new UnsupportedOperationException('offsetSet is unsupported');
    }

    public function offsetUnset ( $offset ) : void {
        throw new UnsupportedOperationException('offsetUnset is unsupported');
    }    

}


?>