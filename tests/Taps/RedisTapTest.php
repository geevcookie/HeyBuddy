<?php namespace HeyBuddy\Taps;

use Mockery;

class RedisTapTest extends \PHPUnit_Framework_TestCase
{
    private $predis;

    public function setUp()
    {
        $predis = Mockery::mock('Predis\Client');
        $predis->shouldReceive('rpush')
            ->with('heybuddy:queues:test', json_encode(array("test")))->atMost()->once()->andReturn(true);
        $predis->shouldReceive('lpop')
            ->with('heybuddy:queues:test')->atMost()->once()->andReturn(json_encode(array("test")));

        $this->predis = $predis;
    }

    public function testPush()
    {
        $redisTap = new RedisTap($this->predis);
        $response = $redisTap->push('test', array("test"));

        $this->assertTrue($response);
    }

    public function testPop()
    {
        $redisTap = new RedisTap($this->predis);
        $response = $redisTap->pop('test');

        $this->assertEquals(array("test"), $response);
    }

    protected function tearDown()
    {
        \Mockery::close();
    }
}