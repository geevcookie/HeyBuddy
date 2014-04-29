<?php

namespace HeyBuddy;

/**
 * Main class handling the sending of messages.
 *
 * @package Hey-HeyBuddy
 * @author Zander Janse van Rensburg
 **/
class Hey
{
	/**
	 * Holds the name of the tap that will be used.
	 * @var string
	 */
	protected $tap;

	/**
	 * Holds the options of the connection.
	 * @var array
	 */
	protected $options;

	/**
	 * Holds the connection which is an object of the specified Tap.
	 * @var object
	 */
	protected $connection;

	/**
	 * Initializes the connection method and the options.
	 * @param string $tap     The name of the tap to use.
	 * @param array  $options The options to use in the connection.
	 */
	public function __construct($tap = '', $options = '')
	{
		if ($tap == '' || $options == '')
			throw new \HeyBuddy\HeyException('Tap or options not specified!');

		$this->tap        = $tap;
		$this->options    = $options;

		$this->_initConnection();
	}

	/**
	 * Intializes the connection parameters.
	 *
	 * @throws \HeyBuddy\HeyException If Tap doesnt exist, create method failed,
	 * or if the class does not implement HeyBuddy\Taps\TapInterface
	 */
	private function _initConnection()
	{
		try {
			$class      = "\HeyBuddy\Taps\\".$this->tap;
			$tap        = new $class($this->options);
			$implements = class_implements($tap);
			if (in_array('HeyBuddy\Taps\TapInterface', $implements))
				$this->connection = $tap;
			else
				throw new \Exception('Tap does not implement correct interface!');
		} catch (\Exception $e) {
			throw new \HeyBuddy\HeyException($e->getMessage(), 1);
		}
	}

	/**
	 * Sends the new queue item and calls _initWorker which will send the async
	 * request to the specified worker script.
	 * 
	 * @param  string $queue The name of the queue to save the item in.
	 * @param  array  $item  The item to save.
	 * @return bool          The status of the entire request.
	 *
	 * @throws \HeyBuddy\HeyException If errors occur during worker initiation.
	 */
	public function send($queue, $item)
	{
		try {
			$class = 'HeyBuddy\Taps\\'.$this->tap;
			if ($this->connection->send($queue, $item))
				if ($this->_initWorker($queue))
					return true;
				else
					throw new \Exception('Worker could not be started!');
			else
				throw new \Exception('Message could not be sent!');
		} catch (\Exception $e) {
			throw new \HeyBuddy\HeyException($e->getMessage(), 1);
		}
	}

	/**
	 * Initializes the asynchronous worker.
	 * @param  string $queue The name of the queue the worker needs to check.
	 * @return bool          The status of creating the worker.
	 *
	 * @throws Exception If invalid worker type is specified.
	 */
	private function _initWorker($queue)
	{
		if (!isset($this->options['worker_type']))
			return $this->_executeExecWorker($queue);
		elseif ($this->options['worker_type'] == 'exec')
			return $this->_executeExecWorker($queue);
		elseif ($this->options['worker_type'] == 'curl')
			return $this->_executeCurlWorker($queue);
		else
			throw new \Exception('Invalid worker type specified!');
	}

	/**
	 * Create async worker.
	 * @param  string $queue The name of the queue the worker needs to check.
	 * @return bool          The status of the query.
	 *
	 * @throws Exception If worker file does not exist.
	 */
	private function _executeExecWorker($queue)
	{
		// Get the worker file and check if it exists.
		$worker = $this->options['worker'];
		if (!file_exists($worker))
			throw new \Exception('Worker file does not exist!');

		// Check options.
		$php_path = (isset($this->options['php_path'])) ? $this->options['php_path']               : '/usr/bin/php';
		$ini_path = (isset($this->options['php_ini']))  ? '-c '.$this->options['php_ini']          : '';
 		$log_path = (isset($this->options['log_path'])) ? $this->options['log_path'].$queue.'.log' : dirname(__FILE__).'/../'.$queue.'.log';

 		// Generate and execute the command.
		if (substr(php_uname(), 0, 7) == "Windows") {
			$cmd = "{$php_path} {$ini_path} -f {$worker} {$queue}";
        	pclose(popen("start /B ". $cmd, "r"));

        	return true;
	    } else {
	    	$cmd = "nohup nice -n 10 {$php_path} {$ini_path} -f {$worker} {$queue} >> {$log_path} & echo $!";
	        $pid = shell_exec($cmd);

	        if ($pid)
				return true;
			else
				return false;
	    }
	}

	/**
	 * Executes a worker via an async curl request.
	 * @param  string $queue The name of the queue the worker needs to check.
	 * @return bool          The status of the curl request.
	 */
	private function _executeCurlWorker($queue)
	{
		// Get worker URL.
		if (isset($this->options['worker_url']))
			$url = $this->options['worker_url'];
		else
			throw new \Exception('No worker URL specified!');

		$url .= "?queue={$queue}";

		// Init curl.
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($c, CURLOPT_HEADER, false);
		curl_setopt($c, CURLOPT_NOBODY, true);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_FRESH_CONNECT, true);

		// Short timeout so that call goes over to async.
		curl_setopt($c, CURLOPT_TIMEOUT, 1);

		return curl_exec($c);
	}

	/**
	 * Handles all calls to invalid methods.
	 * @param  string $method    The name of the method that was called.
	 * @param  array  $arguments The arguments for the method.
	 * 
	 * @throws \HeyBuddy\HeyException
	 */
	public function __call($method, $arguments)
	{
		throw new \HeyBuddy\HeyException("Invalid command: {$method}");
	}
} // END class Hey