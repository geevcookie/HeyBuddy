<?php namespace HeyBuddy\Clients;

use Exception;

class ExecClientTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // Remove test log file.
        @unlink(__DIR__ . '/../logs/test.log');
    }

    public function testGenerateCommand()
    {
        $client   = new ExecClient();
        $response = $client->generateCommand('test', 'test');
        $file     = __DIR__ . '/../../logs/test.log';
        $expect   = "nohup nice -n 10 /usr/bin/php  -f test test >> $file & echo $!";

        $this->assertEquals(realpath($expect), realpath($response));
    }

    public function testSetters()
    {
        $client = new ExecClient();
        $path   = __DIR__ . '/../';
        $client->setIniPath($path.'testIni')->setLogPath($path.'logs/')->setPhpPath('testPhp');

        $response = $client->generateCommand('test', 'test');
        $logFile  = $path . 'logs/test.log';
        $iniFile  = $path . 'testIni';
        $expect   = "nohup nice -n 10 testPhp -c $iniFile -f test test >> $logFile & echo $!";

        $this->assertEquals(realpath($expect), realpath($response));
    }

    /**
     * @expectedException Exception
     */
    public function testFalseWorker()
    {
        $client = new ExecClient();
        $client->send('test', __DIR__ . '/../falseFile');
    }

    public function testSend()
    {
        $logPath  = __DIR__ . '/../logs/';
        $client   = new ExecClient(array('logPath' => $logPath));
        $response = $client->send('test', __DIR__ . '/../testWorker.php');

        $this->assertEquals(true, $response);
        $this->assertTrue(file_exists($logPath . 'test.log'));

        // Ugly but not worth going into async testing.
        sleep(1);
        $content = file_get_contents($logPath . 'test.log');

        $this->assertEquals('test', $content);
    }
}