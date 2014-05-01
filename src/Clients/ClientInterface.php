<?php namespace HeyBuddy\Clients;

interface ClientInterface
{
    /**
     * @param string $queue
     * @param string $resource
     * @return bool
     */
    public function send($queue, $resource);
}