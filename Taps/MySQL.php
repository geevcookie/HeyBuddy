<?php

namespace HeyBuddy\Taps;

/**
 * Tap which uses MySQLi as the backend.
 *
 * @package Hey-HeyBuddy
 * @author Zander Janse van Rensburg
 **/
class MySQL implements TapInterface
{
    /**
     * Holds the DB host.
     * @var string
     */
    protected $host = 'localhost';

    /**
     * Holds the DB username.
     * @var string
     */
    protected $user;

    /**
     * Holds the DB password.
     * @var string
     */
    protected $pass;

    /**
     * Holds the port for the DB connection.
     * @var integer
     */
    protected $port = 3306;

    /**
     * Holds the name of the DB to use.
     * @var string
     */
    protected $name;

    /**
     * Holds the options.
     * @var array
     */
    protected $options;

    /**
     * Holds the name of the primary key column.
     * @var string
     */
    protected $pk;

    /**
     * Initializes the object with the specified options.
     * @param  array  $options The options for the tap.
     * @return object          The current instance of the tap.
     *
     * @throws \Exception If required options are not set.
     */
    public function __construct($options)
    {
        // Check if mysqli is available.
        if (!function_exists('mysqli_query'))
            throw new \Exception('MySQLi not installed!');

        // Init the options.
        if (isset($options['host']))
            $this->host = $options['host'];
        else
            throw new \Exception('"host" option not specified!');

        if (isset($options['user']))
            $this->user = $options['user'];
        else
            throw new \Exception('"user" option not specified!');

        if (isset($options['pass']))
            $this->pass = $options['pass'];
        else
            throw new \Exception('"pass" option not specified!');

        if (isset($options['name']))
            $this->name = $options['name'];
        else
            throw new \Exception('"name" option not specified!');

        if (isset($options['pk']))
            $this->pk = $options['pk'];
        else
            throw new \Exception('"pk" option not specified!');

        $this->options = $options;

        // Return the object.
        return $this;
    }

    /**
     * Creates a mysqli connection and returns the object.
     * @return object The mysqli object.
     */
    private function _connect()
    {
        $mysqli = new \mysqli($this->host, $this->user, $this->pass, $this->name, $this->port);

        if ($mysqli->connect_errno)
            throw new \Exception(printf("Connect failed: %s\n", $mysqli->connect_error));

        return $mysqli;
    }

    /**
     * Adds a new item to the specified queue.
     * @param  string $queue The queue to add the item to.
     * @param  array  $item  An assoc array containing the details of the item.
     * @return bool          The status of inserting the item to the queue.
     */
    public function send($queue, $item)
    {
        $db = $this->_connect();

        // Check if the queue exists.
        if ($this->_checkQueue($queue, $db)) {
            // Build the sql.
            $columns = '(';
            $values  = '(';
            foreach ($item as $key => $value) {
                $columns .= "`{$key}`,";
                $values  .= "'{$value}',";
            }
            $columns = substr($columns, 0, -1).") ";
            $values  = substr($values, 0, -1).")";

            $sql = "
                INSERT INTO {$queue}
                    {$columns}
                VALUES
                    {$values}
            ";

            // Get the result.
            $result = $db->query($sql);

            // Process the result.
            if (!$result) {
                $error = $db->error;
                $db->close();
                throw new \Exception($error);
            } else {
                $db->close();
                return $result;
            }
        } else
            throw new \Exception('Queue does not exist!');
    }

    /**
     * Receives and removes an item from the array.
     * @param  string $queue The name of the queue to get the item from.
     * @return bool          The status of the queury.
     */
    public function receive($queue, $pk)
    {
        $db = $this->_connect();

        // Check if the queue exists.
        if ($this->_checkQueue($queue, $db)) {
            if ($result = $db->query("SELECT * FROM {$queue} LIMIT 1")) {
                // Check if we received a result.
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();

                    // Try to delete the entry.
                    $result->close();
                    if ($result = $db->query("DELETE FROM {$queue} WHERE {$this->pk} = '".$row[$this->pk]."'")) {
                        // All was successful.
                        if ($result) {
                            return $row;
                        }
                        // Failed to delete entry so dont return anything.
                        else
                            throw new \Exception('Could not delete entry from queue!');
                    } else
                        throw new \Exception('Could not delete entry from queue!');
                } else
                    return false;
            }
        } else
            throw new \Exception('Queue does not exist!');
    }

    /**
     * Check items left in queue.
     * @param  string $queue The name of the queue to check.
     * @return bool          The status of the check.
     *
     * @throws \Exception If the status of the queue could not be checked.
     */
    public function checkItems($queue)
    {
        $db = $this->_connect();

        if ($result = $db->query("SELECT * FROM {$queue}")) {
            if ($result->num_rows > 0)
                return true;
            else
                return false;
        } else
            throw new \Exception('Could not check queue status!');
    }

    /**
     * Checks if a queue exists.
     * @param  string $queue The name of the queue.
     * @param  object $db    The mysqli object.
     * @return bool          True of table exists.
     *
     * @throws \Exception If the queue does not exist.
     */
    private function _checkQueue($queue, $db)
    {
        // Check if tables exists.
        if ($result = $db->query("SHOW TABLES LIKE '{$queue}'")) {
            // Table exists.
            if ($result->num_rows > 0) {
                return true;
            // Table does not exist
            } else {
                return false;
            }
        } else
            throw new \Exception($db->error);
    }
}