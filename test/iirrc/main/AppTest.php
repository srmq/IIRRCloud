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

namespace iirrc\test\main;

use \Slim\Http\Environment;
use \Slim\Http\Request;
use \iirrc\main\App;
use \iirrc\util\HTTPUtil;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\ClientException;
use \iirrc\db\UserManager;
use \iirrc\db\AccountManager;
use \iirrc\db\DeviceManager;
use \DateTime;
use \DateTimeZone;

class AppTest extends \PHPUnit\Framework\TestCase
{
    protected $app;

    public $testBaseUrl = 'http://localhost/IIRRCloud/';

    private $mockUser;

    private $mockDevice;

    public function setUp()
    {
        $this->app = (new App())->get();
        $userManager = new UserManager($this->app->getContainer()->db);
        $this->mockUser = array('name' => 'Mock', 'surname' => 'User', 'email' => 'me@somewhere.org', 'password' => '1234');
        $userManager->insertNewUser($this->mockUser);
        $accountManager = new AccountManager($this->app->getContainer()->db);
        $accountManager->setAccount($this->mockUser, new DateTime('now', new DateTimeZone('UTC')), 1);
        $this->mockDevice = array('mac_id' => 'ABCDE1234567', 'password' => 'FreeLula', 'name' => 'Mock Device', 'model' => 'Fake', 'manufact_dt' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        
        $deviceManager = new DeviceManager($this->app->getContainer()->db);
        $deviceManager->insertNewDevice($this->mockDevice);
        $deviceManager->associateToAccount($this->mockUser, $this->mockDevice, $accountManager);
    }

    public function tearDown() {
        $deviceManager = new DeviceManager($this->app->getContainer()->db);
        $deviceManager->deleteDevice($this->mockDevice);
        $accountManager = new AccountManager($this->app->getContainer()->db);
        $accountManager->removeAccount($this->mockUser);
        $userManager = new UserManager($this->app->getContainer()->db);
        $userManager->deleteUser($this->mockUser);
    }

    public function testInsertMockUserOk() {
        $userManager = new UserManager($this->app->getContainer()->db);
        $this->assertTrue($userManager->userExists($this->mockUser));
    }

    public function testInsertMockAccountOk() {
        $accountManager = new AccountManager($this->app->getContainer()->db);
        $this->assertTrue($accountManager->accountForUserExists($this->mockUser));
        $account = $accountManager->getAccountForUserId((int)$this->mockUser['uid']);
        $this->assertFalse($accountManager->isAccountExpired($account));
    }

    public function testMockDeviceAssociated() {
        $deviceManager = new DeviceManager($this->app->getContainer()->db);
        $devices = $deviceManager->devicesIdForUserWithId((int)$this->mockUser['uid']);
        $this->assertTrue(in_array($this->mockDevice['id'], $devices));
        $this->assertTrue(count($devices) == 1);
    }

    public function testSendParamsNoLogin() {
        $datalogURL = '/v100/datalog/send-params';
        $msglogURL = '/v100/msglog/send-params';
        $invalidlogURL = '/v100/invalid/send-params';
        $client = new Client(['base_uri' => $this->testBaseUrl]);
        $testNoAuth = function(string $url, array $options = []) use ($client) {
            try {
                $responseNoAuth = $client->request('GET', $url, $options);
                $this->assertTrue(false, 'Should generate ClientException due to 401 Unauthorized error with url ' . $url);
            } catch(ClientException $e) {
                $this->assertTrue(true);
            }
        };
        $testNoAuth($datalogURL);
        $testNoAuth($msglogURL);
        $testNoAuth($invalidlogURL);
        $testNoAuth($datalogURL, [
            'auth' => ['invalidUser', 'password', 'digest']]);
        $testNoAuth($msglogURL, [
            'auth' => ['invalidUser', 'password', 'digest']]);
        $testNoAuth($invalidlogURL, [
            'auth' => ['invalidUser', 'password', 'digest']]);   
    }

    /*
    public function testLocalhost() {
        // Create a client with a base URI
        $client = new Client(['base_uri' => 'http://localhost:8080/']);
        // Send a request to https://foo.com/api/test
        $response = $client->request('GET', '/hello/srmqthegreat', [
            'auth' => ['username1', 'password1', 'digest']]);      
        //$parsedAuthInfo = HTTPUtil::parseAuthHeader($response);
        echo ">>>>GUZZLE: \n";
        echo ((string)$response->getBody()) . "\n";
        echo $response->getStatusCode() . "\n";
        $this->assertTrue(true);
    }

    public function testTodoGet() {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/hello/srmqthegreat'
            ]);
        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $parsedAuthInfo = HTTPUtil::parseAuthHeader($response);
        echo var_dump($parsedAuthInfo) . '\n';
        echo ((string)$response->getBody()) . "\n";
        echo $response->getStatusCode() . "\n";
*/
        /*
        $userName = 'username1';
        $pass='password1';
        $uri = '/hello/srmqthegreat';
        $cnonce = uniqid();
        $a1 = md5("{$userName}:{$parsedAuthInfo['Digest realm']}:{$pass}");
        $a2 = md5("GET:{$uri}");
        $hash = md5("{$a1}:{$parsedAuthInfo['nonce']}:00000001:{$cnonce}:{$parsedAuthInfo['qop']}:{$a2}");
        
        $authStr =  "Digest username=\"{$userName}\", realm=\"{$parsedAuthInfo['Digest realm']}\", nonce=\"{$parsedAuthInfo['nonce']}\", uri=\"{$uri}\", qop=auth, nc=00000001, cnonce=\"{$cnonce}\", response=\"{$hash}\", opaque=\"{$parsedAuthInfo['opaque']}\"";
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/hello/srmqthegreat',
            'HTTP_Authorization' => $authStr
            ]);

        $req = Request::createFromEnvironment($env);
        echo "\n" . var_dump($req->getHeaders());
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        echo ((string)$response->getBody()) . "\n";
        echo $response->getStatusCode() . "\n";

        
*/
/*        $this->assertTrue(true);
    } */ 
}