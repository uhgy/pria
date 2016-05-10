<?php

// Autoload 自动载入
require '../vendor/autoload.php';

use Predis\Client;

$quit = False;
$limit = 10000000;

function gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

        // 16 bits for "time_mid"
        mt_rand( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

function update_token($conn, $token, $user, $item=None)
{
    $timestamp = time();
    $conn->hset('login:', $token, $user);

}


class TestCh01 extends PHPUnit_Framework_TestCase
{
    protected static $conn;

    public function setUp()
    {
        self::$conn = new Client('tcp://localhost:6379');
        self::$conn->select(2);

    }

    public function tearDown()
    {
        self::$conn = NULL;
        global $quit, $limit;
        $quit = False;
        $limit = 10000000;
        echo "\n";
        echo "\n";
    }

    public function test_login_cookies()
    {
        $conn = self::$conn;
        global $quit, $limit;
        $token = (string)gen_uuid();

        update_token($conn, $token, 'username', 'itemX');
        echo "we just logged-in/updated token:" . $token . "\n";
        echo "For user:" . "username";
        echo "\n";


    }


}

$suite = new PHPUnit_Framework_TestSuite();
$suite->addTestSuite('TestCh01');

PHPUnit_TextUI_TestRunner::run($suite);