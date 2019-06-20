<?php

namespace Tests\Api;

use Dotenv\Dotenv;

use PHPUnit\Framework\TestCase;

/**
 * Description of BaseApiTest
 *
 * @author Marcos Peña
 */
class BaseApiTest extends TestCase
{
    protected $apiKey;
    protected $apiSecret;
    protected $token;
    protected $sessionKey;
    protected $username;
    
    protected function setUp()
    {
        $dotenv = new Dotenv(__DIR__);
        $dotenv->load();
        $this->apiKey = getenv('lastfm_api_key');
        $this->apiSecret = getenv('lastfm_api_secret');
        $this->token = getenv('lastfm_token');
        $this->sessionKey = getenv('lastfm_session_key');
        $this->username = getenv('lastfm_username');
    }
    
    public function testDotenvFileExists()
    {
        $this->assertFileExists(__DIR__ . '/.env', 'You need to setup a .env file to run the tests. https://github.com/vlucas/phpdotenv');
    }
}
