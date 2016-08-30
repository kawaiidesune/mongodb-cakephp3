<?php
/**
 * @author Véronique Bellamy <v@vero.moe>
 * @license MIT
 *
 * @since 0.1-dev
 */
namespace Hayko\Mongodb\Database;

use Hayko\Mongodb\Database\Driver\Mongodb as Haykodb;
use Hayko\Mongodb\Database\Schema\MongoSchema;
use Cake\Datasource\ConnectionInterface;
use Cake\Database\Log\LoggedQuery;
use Cake\Database\Log\QueryLogger;

class Connection implements ConnectionInterface {

	/**
	 * Contains the configuration param for this connection
	 * 
	 * @access protected
	 * @var array
	 */
	 protected $_config;	

	/**
	 * Database Driver object
	 *
	 * @access protected
	 * @var resource
	 */
	protected $_driver = null;

	 /**
     * Whether to log queries generated during this connection.
     *
     * @access protected
     * @var bool
     */
    protected $_logQueries = false;

    /**
     * Logger object instance.
     *
     * @access protected
     * @var \Cake\Database\Log\QueryLogger
     */
    protected $_logger = null;

	/**
	 * MongoSchema
	 * 
	 * @access protected
	 * @var MongoSchema
	 */
	protected $_schemaCollection;

	/**
	 * creates a new connection with mongodb
	 * 
	 * @access public
	 * @param array $config
	 * @return bool
	 */
	public function __construct(array $config) {
		$this->_config = $config;
		$this->driver('mongodb', $config);

		if (!empty($config['log'])) {
            $this->logQueries($config['log']);
        }
	}

	/**
	 * disconnect existent connection
	 * 
	 * @access public
	 * @return void
	 */
	public function __destruct() {
		if ($this->_driver->isConnected()) {
			$this->_driver->disconnect(); // However, the new PHP MongoDB library uses a lazy way to connect.
			unset($this->_driver);
		}
	}

	/**
	 * return configuration
	 * 
	 * @access public
	 * @return array $_config
	 */
		public function config() {
			return $this->_config;
		}

	/**
	 * return configuration name
	 * 
	 * @access public
	 * @return string
	 */
	public function configName() {
		return 'mongodb'; // Seriously? A function to return a string?
	}	

	/**
	 * @todo Nice documentation skills, Hayko.
	 */
	public function driver($driver = null, $config = []) {
		if ($driver === null) {
			return $this->_driver;
		}
		$this->_driver = new Haykodb($config);
		return $this->_driver;
	}

	/**
	 * Connect to the database
	 * 
	 * @access public
	 * @return boolean
	 * @throws MissingConnectionException If the driver cannot connect, then it throws this.
	 */
	public function connect() {
		try {
			$this->_driver->connect();
			return true;
		} catch (Exception $e) {
			throw new MissingConnectionException(['reason' => $e->getMessage()]); // TODO: Where the hell is this object and has it been replaced in the new driver?
		}
	}

	/**
	 * Disconnect from the database
	 * 
	 * @access public
	 * @return boolean
	 * @todo Determine if this is required by CakePHP in order to function, as the new MongoDB PHP library connects lazily.
	 */
	public function disconnect() {
		if ($this->_driver->isConnected()) {
			return $this->_driver->disconnect();
		}
		return true;
	}

	/**
	 * database connection status
	 * 
	 * @access public
	 * @return bool
	 * @todo IF this is unnecessary, send this to the Department of Redundancy Department.
	 * @uses Mongodb::isConnected()
	 */
	public function isConnected() {
		return $this->_driver->isConnected();
	}

	/**
	 * Gets or sets schema collection for this connection
	 * 
	 * @param $collection
	 * @return \Hayko\Mongodb\Database\Schema\MongoSchema
	 */
	public function schemaCollection($collection = null) {
		return $this->_schemaCollection = new MongoSchema($this->_driver); // Well, THIS needs to be rewritten.
	}

	/**
	 * Mongo doesn't support transaction
	 * 
	 * @access public
	 * @return false
	 */
	public function transactional(callable $transaction) {
		return false;
	}

	/**
	 * Mongo doesn't support foreign keys
	 * 
	 * @access public
	 * @return false
	 */
	public function disableConstraints(callable $operation) {
		return false;
	}

	/**
	 * 
	 * @access public
	 * @return 
	 * @todo Figure out what the hell this function does and document it.
	 */
	public function logQueries($enable = null) {
		if ($enable === null) {
			return $this->_logQueries;
		}
		$this->_logQueries = $enable;
	}

	/**
	 * @access public
	 */
	public function logger($instance = null) {
		if ($instance === null) {
            if ($this->_logger === null) {
                $this->_logger = new QueryLogger;
            }
            return $this->_logger;
        }
        $this->_logger = $instance;
	}

	/**
     * Logs a Query string using the configured logger object.
     *
     * @access public
     * @param string $sql string to be logged
     * @return void
     */
    public function log($sql) {
        $query = new LoggedQuery;
        $query->query = $sql;
        $this->logger()->log($query);
    }
}