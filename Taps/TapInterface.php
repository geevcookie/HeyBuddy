<?php

namespace HeyBuddy\Taps;

/**
 * Interface which all taps need to implement.
 *
 * @package Hey-HeyBuddy
 * @author Zander Janse van Rensburg
 **/
interface TapInterface
{
    public function __construct($options);
    public function send($queue, $item);
    public function receive($queue, $pk);
} // END interface TapInterface