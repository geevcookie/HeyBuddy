<?php namespace HeyBuddy\Taps;

interface TapInterface
{
    /**
     * Pushes a new job to the queue.
     *
     * @param string $queue
     * @param array  $data
     * @return bool
     */
    public function push($queue, array $data);

    /**
     * Get the next item from the queue.
     *
     * @param string $queue
     * @return array
     */
    public function pop($queue);
}