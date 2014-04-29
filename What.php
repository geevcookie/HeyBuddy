<?php

namespace HeyBuddy;

/**
 * Main class handling the receiving of messages.
 *
 * @package Hey-HeyBuddy
 * @author Zander Janse van Rensburg
 **/
class What
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
     * Holds the name of the environment the code is running in.
     * @var string
     */
    protected $environment;

    /**
     * Holds the name of the file that called the function.
     * @var string
     */
    protected $worker;

    /**
     * Initializes the connection method and the options.
     * @param string $tap     The name of the tap to use.
     * @param array  $options The options to use in the connection.
     */
    public function __construct($tap, $options, $worker)
    {
        $this->tap        = $tap;
        $this->options    = $options;
        $this->worker     = $worker;

        if (!isset($this->options['workers']))
            $this->options['workers'] = 1;

        if (substr(php_uname(), 0, 7) == "Windows")
            $this->environment = 'windows';
        else
            $this->environment = 'linux';

        $this->_initConnection();
    }

    /**
     * Intializes the connection parameters.
     *
     * @throws \Exception If Tap doesnt exist, create method failed, or if the
     * class does not implement HeyBuddy\Taps\TapInterface
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
            echo $e->getMessage();
        }
    }

    /**
     * If workers are available this function will receive a new item from the
     * tap and return it.
     * @param  string $queue The name of the queue to check.
     * @return bool          The status of the request.
     */
    public function receive($queue = '')
    {
        // Check which queue needs to be processed.
        if ($queue == '') {
            if (isset($_GET) && isset($_GET['queue']))
                $queue = $_GET['queue'];
            elseif (isset($argv[1]))
                $queue = $argv[1];
            else {
                echo "No queue specified\r\n";
                die();
            }
        }

        // Try to process the queue.
        try {
            $class = "\HeyBuddy\Taps\\".$this->tap;
            $class = new $class($this->options);
            if ($this->_checkWorkers($queue)) {
                return $class->receive($queue, $this->options['pk']);
            } else
                return false;
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Executes another instance of the worker so that processing can continue.
     * @param  string   $queue The name of the queue the worker needs to check.
     * @return bool            The status of the new worker.
     */
    public function next($queue)
    {
        try {
            $class = "\HeyBuddy\Taps\\".$this->tap;
            $class = new $class($this->options);

            if ($class->checkItems($queue))
                return $this->_executeExecWorker($queue);
            else
                return;
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Create async worker.
     * @param  string $queue The name of the queue the worker needs to check.
     * @return bool          The status of the query.
     *
     * @throws \Exception If worker file does not exist.
     */
    private function _executeExecWorker($queue)
    {
        // Check options.
        $php_path = (isset($this->options['php_path'])) ? $this->options['php_path']               : '/usr/bin/php';
        $ini_path = (isset($this->options['php_ini']))  ? '-c '.$this->options['php_ini']          : '';
        $log_path = (isset($this->options['log_path'])) ? $this->options['log_path'].$queue.'.log' : dirname(__FILE__).'/../'.$queue.'.log';

        // Generate and execute the command.
        if (substr(php_uname(), 0, 7) == "Windows") {
            $cmd = "{$php_path} {$ini_path} -f {$this->worker} {$queue}";
            pclose(popen("start /B ". $cmd, "r"));

            return true;
        } else {
            $cmd = "nohup nice -n 10 {$php_path} {$ini_path} -f {$this->worker} {$queue} >> {$log_path} &";
            $pid = shell_exec($cmd);

            if ($pid)
                return true;
            else
                return false;
        }
    }

    /**
     * Checks if there are any worker slots available by using a lock file
     * containing PIDs and then checking if those PID files are valid.
     * @param  string $queue The name of the queue.
     * @return bool          The status of the worker check.
     *
     * @throws \Exception If lock file could not be opened.
     */
    private function _checkWorkers($queue)
    {
        $lock_file = (isset($this->options['lock_path'])) ? $this->options['lock_path'].'.'.$queue.'lock' : dirname(__FILE__).'/../.'.$queue.'lock';
        $pid       = getmypid();

        if (file_exists($lock_file)) {
            $handle = @fopen($lock_file, 'r');

            if ($handle) {
                $valid_pids = array();
                // Loop through existing PIDs and make sure that they are still
                // valid.
                while (($pid = fgets($handle, 4096)) !== false) {
                    if ($this->_checkPid($pid))
                        $valid_pids[] = $pid;
                }
                if (!feof($handle))
                    throw new \Exception('Unexpected error during reading of lock file!');
                else {
                    if (($this->options['workers'] + 1) == count($valid_pids))
                        return false;
                    else {
                        $valid_pids[] = $pid;

                        // Close the file so we can recreate it.
                        fclose($handle);
                        unlink($lock_file);

                        // Recreate the file with new PIDs
                        $handle = @fopen($lock_file, 'w');
                        if ($handle) {
                            foreach ($valid_pids as $pid)
                                @fwrite($handle, $pid."\r\n");

                            fclose($handle);
                            return true;
                        } else
                            throw new \Exception('Could not recreate lock file!');
                    }
                }
            } else
                throw new \Exception('Could not open lock file!');
        } else {
            $handle = @fopen($lock_file, 'w');

            if ($handle) {
                fwrite($handle, $pid."\r\n");
                fclose($handle);

                return true;
            } else
                throw new \Exception('Could not open lock file!');
        }
    }
    
    /**
     * Checks if a PID is still valid.
     * @param  string $pid The PID to check.
     * @return bool        The status of the check.
     */
    private function _checkPid($pid)
    {
        if ($this->environment == 'linux')
            return file_exists('/proc/'.$pid);
        else {
            $processes = explode('\n', shell_exec('tasklist.exe'));
            foreach ($processes as $process) {
                if (preg_match('/^(.*)\s+'.$pid.'/', $process))
                    return true;
            }

            return false;
        }
    }

    /**
     * Handles all calls to invalid methods.
     * @param  string $method    The name of the method that was called.
     * @param  array  $arguments The arguments for the method.
     */
    public function __call($method, $arguments)
    {
        echo "Invalid command: {$method}";
    }
}