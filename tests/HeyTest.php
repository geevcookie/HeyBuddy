<?php namespace HeyBuddy;

use \Exception;

class HeyTest extends \PHPUnit_Framework_TestCase
{
    public function testSend()
    {
        $tap    = \Mockery::mock('HeyBuddy\Taps\TapInterface');
        $client = \Mockery::mock('HeyBuddy\Clients\ClientInterface');
        $tap->shouldReceive('push')->once()->with('test', array('test'))->andReturn(true);
        $client->shouldReceive('send')->once()->with('test', 'testResource')->andReturn(true);

        $hey    = new Hey($tap, $client);
        $result = $hey->push('test', array('test'), 'testResource');

        $this->assertEquals(true, $result);
    }

    /**
     * @expectedException Exception
     */
    public function testTapError()
    {
        $tap    = \Mockery::mock('HeyBuddy\Taps\TapInterface');
        $client = \Mockery::mock('HeyBuddy\Clients\ClientInterface');
        $tap->shouldReceive('push')->once()->with('test', array('test'))->andReturn(false);

        $hey    = new Hey($tap, $client);
        $result = $hey->push('test', array('test'), 'testResource');
    }

    /**
     * @expectedException Exception
     */
    public function testClientError()
    {
        $tap    = \Mockery::mock('HeyBuddy\Taps\TapInterface');
        $client = \Mockery::mock('HeyBuddy\Clients\ClientInterface');
        $tap->shouldReceive('push')->once()->with('test', array('test'))->andReturn(true);
        $client->shouldReceive('send')->once()->with('test', 'testResource')->andReturn(false);

        $hey    = new Hey($tap, $client);
        $result = $hey->push('test', array('test'), 'testResource');
    }
}