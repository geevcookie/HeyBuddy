<?php namespace HeyBuddy\Taps;

use Predis\Client;

class RedisTap implements TapInterface
{
    /**
     * @var \Predis\Client
     */
    private $predis;

    /**
     * @param Client $predis
     */
    public function __construct(Client $predis)
    {
        $this->predis = $predis;
    }

    /**
     * Pushes a new job to the queue.
     *
     * @param string $queue
     * @param array  $data
     * @return bool
     */
    public function push($queue, array $data)
    {
        return (bool) $this->predis->rpush("heybuddy:queues:$queue", json_encode($data));
    }

    /**
     * Get the next item from the queue.
     *
     * @param string $queue
     * @return array
     */
    public function pop($queue)
    {
        $response = $this->predis->lpop("heybuddy:queues:$queue");

        return json_decode($response, true);
    }
}