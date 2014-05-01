<?php namespace HeyBuddy;

use HeyBuddy\Clients\ClientInterface;
use HeyBuddy\Taps\TapInterface;

class Hey
{
    /**
     * @var Taps\TapInterface
     */
    private $tap;

    /**
     * @var Clients\ClientInterface
     */
    private $client;

    /**
     * @param TapInterface $tap
     * @param \HeyBuddy\Clients\ClientInterface $client
     */
    public function __construct(TapInterface $tap, ClientInterface $client)
    {
        $this->tap    = $tap;
        $this->client = $client;
    }

    /**
     * @param string $queue
     * @param array  $data
     * @param string $resource
     * @return bool
     * @throws \RuntimeException
     */
    public function push($queue, array $data, $resource)
    {
        if (!$this->tap->push($queue, $data)) {
            throw new \RuntimeException('Could not push to queue!');
        }

        if (!$this->client->send($queue, $resource)) {
            throw new \RuntimeException('Could not call worker!');
        }

        return true;
    }
}