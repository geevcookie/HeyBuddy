<?php namespace HeyBuddy\Clients;

class ExecClient implements ClientInterface
{
    /**
     * @var array
     */
    private $options;

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $defaultOptions = array(
            'phpPath' => '/usr/bin/php',
            'iniPath' => null,
            'logPath' => __DIR__ . '/../../logs/'
        );

        $this->options = array_merge($defaultOptions, $options);
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setPhpPath($path)
    {
        $this->options['phpPath'] = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setIniPath($path)
    {
        $this->options['iniPath'] = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setLogPath($path)
    {
        $this->options['logPath'] = $path;

        return $this;
    }

    /**
     * @param string $queue
     * @param string $file
     * @throws \Exception
     * @return bool
     */
    public function send($queue, $file)
    {
        // Check if the worker file exists.
        if (!file_exists($file)) {
            throw new \Exception('Worker file does not exist!');
        }

        $cmd = $this->generateCommand($queue, $file);
        $pid = shell_exec($cmd);

        return (bool) $pid;
    }

    /**
     * @param string $queue
     * @param string $file
     * @return string
     */
    public function generateCommand($queue, $file)
    {
        $iniPath = (isset($this->options['iniPath'])) ? '-c ' . $this->options['iniPath'] : '';
        $logFile = $this->options['logPath'] . "$queue.log";
        $cmd     = "nohup nice -n 10 %s %s -f %s %s >> %s & echo $!";

        return sprintf($cmd, $this->options['phpPath'], $iniPath, $file, $queue, $logFile);
    }
}