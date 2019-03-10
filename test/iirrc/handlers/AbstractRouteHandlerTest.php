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

namespace iirrc\test\handlers;

use iirrc\handlers\AbstractRouteHandler;
use \DateTime;
use \DateTimeZone;
use \DateInterval;


class AbstractRouteHandlerTest extends \PHPUnit\Framework\TestCase {
    public function testIsInTheFuture() {
        $nowTime = new DateTime('now', new DateTimeZone('UTC'));
        $futureDate = (clone $nowTime)->add(DateInterval::createFromDateString("6 minutes"));
        $this->assertTrue(AbstractRouteHandler::isInTheFuture($futureDate));
        $futureDate = (clone $nowTime)->add(DateInterval::createFromDateString("3 minutes"));
        $this->assertFalse(AbstractRouteHandler::isInTheFuture($futureDate));
        $pastDate = (clone $nowTime)->sub(DateInterval::createFromDateString("1 hour"));
        $this->assertFalse(AbstractRouteHandler::isInTheFuture($pastDate));
    }
}


?>