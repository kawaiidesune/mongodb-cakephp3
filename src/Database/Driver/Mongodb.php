<?php
/**
 * @author Véronique Bellamy <v@vero.moe>
 * @license MIT
 *
 * @since 0.1-dev
 */
namespace Hayko\Mongodb\Database\Driver;
use MongoDB\Driver\Manager;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\Query;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use MongoDB\Driver\Exception\ExecutionTimeoutException;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Driver\Exception\LogicException;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Exception\SSLConnectionException;
use MongoDB\Driver\Exception\UnexpectedValueException;
use MongoDB\Driver\Exception\WriteException;
use \SSH2;

class Mongodb {
	/**
	 * Config
	 * 
	 * @access private
	 * @todo What the fuck is this shit? Don't we define this stuff in app.php? Why use it here too?
	 * @used-by Mongodb::__construct()
	 * @var array
	 */
	private $_config;

	/**
	 * Database Instance
	 *
	 * @access protected
	 * @todo This looks pretty vestigal too.
	 * @var resource
	 */
	protected $_db = null;

	/**
	 * Mongo Driver Version
	 *
	 * @access protected
	 * @todo This seems redundant as hell. Seriously, if we can use the constant throughout the programme, it might be wise to axe this.
	 * @var string
	 */
	protected $_driverVersion = MONGODB_VERSION;

	/**
	 * Base Config
	 *
	 * set_string_id:
	 *    true: In read() method, convert \MongoDB\BSON\ObjectId object to string and set it to array 'id'.
	 *    false: not convert and set.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_baseConfig = [
		'set_string_id'		=> true,
		'persistent'		=> true,
		'host'      		=> 'localhost',
		'database'  		=> '',
		'port'      		=> 27017,
		'login'				=> '',
		'password'			=> '',
		'replicaset'		=> '',
		'ssh'				=> [
			'host'			=> '',
			'user'			=> '',
			'password'		=> '',
			'port'			=> 22,
			'key'			=> [
				'public'	=> '',
				'private'	=> '',
				'passphrase'=> ''
			]
		]
	];

	/**
	 * Direct connection with database
	 *
	 * @access private
	 * @todo Re-evaluate the statement in var, as this cannot hold a Mongo datatype as it shouldn't exist in the new MongoDB PHP library.
	 * @var mixed null | Mongo
	 */
	private $connection = null;

	/**
	 * @access public
	 * @uses Mongodb::_config()
	 */
	public function __construct($config) {
		$this->_config = $config;
	}

	/**
	 * return configuration
	 * 
	 * @access public
	 * @return array
	 */
	public function config() {
		return $this->_config;
	}

	/**
	 * connect to the database
	 * 
	 * @access public
	 * @return bool
	 */
	public function connect() {
		try {
			/****************************************************************************
			 *
			 * THIS IS THE SSH TUNNEL CODE WE WROTE. UNLESS IT'S BROKE, WE DON'T NEED TO
			 * PAY ATTENTION TO THIS!!!
			 *
			 ****************************************************************************/
			if (($this->_config['ssh']['user'] != '') && ($this->_config['ssh']['host'])) { // Because a user is required for all of the SSH authentication functions.
				if (intval($this->_config['ssh']['port']) != 0) {
					$port = $this->_config['ssh']['port'];
				} else {
					$port = 22; // The default SSH port.
				}
				$spongebob = ssh2_connect($this->_config['ssh']['host'], $port);
				if (!$spongebob) {
					trigger_error('Unable to establish a SSH connection to the host at '. $this->_config['ssh']['host'] .':'. $port);
				}
				if (($this->_config['ssh']['key']['public'] != null) && ($this->_config['ssh']['key']['private'] != null)) {
					// TODO: Add error handling if ONE of these keys is defined, but the other one is missing. Same with the passphrase.
					if ($this->_config['ssh']['key']['passphrase'] != null) {
						if (!ssh2_auth_pubkey_file($spongebob, $this->_config['ssh']['user'], $this->_config['ssh']['key']['public'], $this->_config['ssh']['key']['private'], $this->_config['ssh']['key']['passphrase'])) {
							trigger_error('Unable to connect using the public keys specified at '. $this->_config['ssh']['key']['public'] .' (for the public key), '. $this->_config['ssh']['key']['private'] .' (for the private key) on '. $this->_config['ssh']['user'] .'@'. $this->_config['ssh']['host'] .':'. $port .' (Using a passphrase to decrypt the key)');
							return false;
						}
					} else {
						if (!ssh2_auth_pubkey_file($spongebob, $this->_config['ssh']['user'], $this->_config['ssh']['key']['public'], $this->_config['ssh']['key']['private'])) {
							trigger_error('Unable to connect using the public keys specified at '. $this->_config['ssh']['key']['public'] .' (for the public key), '. $this->_config['ssh']['key']['private'] .' (for the private key) on '. $this->_config['ssh']['user'] .'@'. $this->_config['ssh']['host'] .':'. $port .' (Not using a passphrase to decrypt the key)');
							return false;
						}
					}
				} elseif ($this->_config['ssh']['password'] != '') { // While some people *could* have blank passwords, it's a really stupid idea.
					if (!ssh2_auth_password($spongebob, $this->_config['ssh']['user'], $this->_config['ssh']['password'])) {
						trigger_error('Unable to connect using the username and password combination for '. $this->_config['ssh']['user'] .'@'. $this->_config['ssh']['host'] .':'. $port);
						return false;
					}
				} else {
					trigger_error('Neither a password or paths to public & private keys were specified in the configuration.');
					return false;
				}
				
				$tunnel = ssh_tunnel($spongebob, $this->_config['host'], $this->_config['port']);
				if (!$tunnel) {
					trigger_error('A SSH tunnel was unable to be created to access '. $this->_config['host'] .':'. $this->_config['port'] .' on '. $this->_config['ssh']['user'] .'@'. $this->_config['ssh']['host'] .':'. $port);
				}
			}
			
			/****************************************************************************
			 *
			 * THIS IS THE END OF THE SSH TUNNEL CODE WE WROTE. TIME TO REWRITE THIS SHIT!
			 *
			 ****************************************************************************/
			// Also, if this connection fails when using a SSH tunnel, determine whether it was because we instantiated this connection improperly.
			// Furthermore, is this the right way to instantiate this class? It looks weird to me, but this is the example given in the PHP Manual.
			// (i.e. http://php.net/manual/en/class.mongodb-driver-manager.php)
			$this->connection = new \MongoDB\Driver\Manager($this->createConnectionName());
			
			// TODO: Write some code to test the connection to the server and apply error handling if this fails.
			
			// TODO: Figure out what is setting $this->_config['slaveok'], as I can't find it here or in /src/Database/connection.php, nor in the variables declared...
			if (isset($this->_config['slaveok'])) { 
				$rp = new \MongoDB\Driver\ReadPreference($this->_config['slaveok']
					? \MongoDB\Driver\ReadPreference::RP_SECONDARY_PREFERRED : \MongoDB\Driver\ReadPreference::RP_PRIMARY);
			}
			$serverStatus = new \MongoDB\Driver\Command("db.serverStatus()");

			// It returns MongoDB\Driver\Cursor on success, but what does it return on failure? O.o
			// Nothing? One can wonder because the function, when instantiated, returns MongoDB\Driver\Cursor... and I'm not sure you can
			// multitype when you define the value it does return.
			if ($this->connection->executeCommand($this->_config['database'], $serverStatus)) {
				$this->connected = true;
			}
		} catch (\MongoDB\Driver\Exception\Exception $e) {
			/****************************************************************************
			 *
			 * THIS CATCH CLAUSE...
			 * Well, plus side... once we know more about the types of errors caught in these
			 * exceptions, we can improve error handling.
			 *
			 ****************************************************************************/
			if (is_a($e, "\MongoDB\Driver\Exception\AuthenticationException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\BulkWriteException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\ConnectionException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\ConnectionTimeoutException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\ExecutionTimeoutException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\InvalidArgumentException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\LogicException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\RuntimeException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\SSLConnectionException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\UnexpectedValueException")) {
				trigger_error($e->getMessage());
			} elseif (is_a($e, "\MongoDB\Driver\Exception\WriteException")) {
				trigger_error($e->getMessage());
			} else {
				// Well, what the hell else could it be?
			}
		}
		return $this->isConnected(); // TODO: Figure out something else to return.
	}

	/**
	 * create connection string
	 * 
	 * @access private
	 * @return string
	 */
	private function createConnectionName() {
		$host = 'mongodb://';
		$hostname = $this->_config['host'] . ':' . $this->_config['port'];
		if (!empty($this->_config['login'])) {
			$host .= $this->_config['login'] . ':' . $this->_config['password'] . '@';
		}
		$host .= $hostname . '/';
		return $host;
	}

	/**]
	 * 
	 * @access public
	 * @param string $collectionName
	 * @return MongoDB\Driver\Cursor|bool If it can't select the collection, it SHOULD return false. If it can, it SHOULD return the MongoDB\Driver\Cursor object.
	 * @todo Determine whether this is necessary, as MongoDB\Driver\Manager does not have a means to select the collection and every collection is namespaced anyway.
	 */
	public function getCollection($collectionName = '') {
		if (!empty($collectionName)) {
			$this->connect();
			$filter = array();
			// TODO: Seriously, write the query. It needs parameters.
			$query = new \MongoDB\Driver\Query($filter);

			return $this->connection->executeQuery($this->_config['database'].'.'.$collectionName, $query);
		}
		return false;
	}

	/**
	 * disconnect from the database
	 * 
	 * @access public
	 * @return bool
	 * @todo Find out if this is required by any other method, and if not, remove it (per the documentation at https://github.com/alcaeus/mongo-php-adapter)
	 */
	public function disconnect() {
		if ($this->isConnected()) {
			// There is no close() method to the MongoDB\Driver\Manager in the new libraries. Removed it.
			unset($this->_db, $this->connection); // TODO: What the hell is $this->_db? Do we need it?
			return true;
		}
		return true;
	}

	/**
	 * database connection status
	 * 
	 * @access public
	 * @return bool
	 * @todo Make this interactive and GET RID OF $this->connected as a variable.
	 * @used-by Connection::isConnected()
	 */
	public function isConnected() {
		return true; // Seriously, let's fix this later.
	}
}