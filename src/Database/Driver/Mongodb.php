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

class Mongodb {
	/**
	 * Config
	 * 
	 * @var array
	 * @access private
	 * @todo What the fuck is this shit? Don't we define this stuff in app.php? Why use it here too?
	 */
	private $_config;

	/**
	 * Database Instance
	 *
	 * @var resource
	 * @access protected
	 * @todo This looks pretty vestigal too.
	 */
	protected $_db = null;

	/**
	 * Mongo Driver Version
	 *
	 * @var string
	 * @access protected
	 * @todo This seems redundant as hell. Seriously, if we can use the constant throughout the programme, it might be wise to axe this.
	 */
	protected $_driverVersion = MONGODB_VERSION;

	/**
	 * Base Config
	 *
	 * set_string_id:
	 *    true: In read() method, convert MongoId object to string and set it to array 'id'.
	 *    false: not convert and set.
	 *
	 * @var array
	 * @access public
	 * @todo Add in the SSH tunnel variables.
	 *
	 */
	protected $_baseConfig = [
		'set_string_id' => true,
		'persistent' => true,
		'host'       => 'localhost',
		'database'   => '',
		'port'       => 27017,
		'login'		=> '',
		'password'	=> '',
		'replicaset'	=> '',
	];

	/**
	 * Direct connection with database
	 *
	 * @var mixed null | Mongo
	 * @access private
	 */
	private $connection = null;

	/**
	 * 
	 */
	public function __construct($config) {
		$this->_config = $config;
	}

	/**
	 * return configuration
	 * 
	 * @return array
	 * @access public
	 */
	public function config() {
		return $this->_config;
	}

	/**
	 * connect to the database
	 * 
	 * @return boolean
	 * @access public
	 */
	public function connect() {
		try {
			/****************************************************************************
			 *
			 * THIS IS THE SSH TUNNEL CODE WE WROTE. UNLESS IT'S BROKE, WE DON'T NEED TO
			 * PAY ATTENTION TO THIS!!!
			 *
			 ****************************************************************************/
			if (($this->config['ssh_user'] != '') && ($this->config['ssh_host'])) { // Because a user is required for all of the SSH authentication functions.
				if (intval($this->config['ssh_port']) != 0) {
					$port = $this->config['ssh_port'];
				} else {
					$port = 22; // The default SSH port.
				}
				$spongebob = ssh2_connect($this->config['ssh_host'], $port);
				if (!$spongebob) {
					trigger_error('Unable to establish a SSH connection to the host at '. $this->config['ssh_host'] .':'. $port);
				}
				if (($this->config['ssh_pubkey_path'] != null) && ($this->config['ssh_privatekey_path'] != null)) {
					if ($this->config['ssh_pubkey_passphrase'] != null) {
						if (!ssh2_auth_pubkey_file($spongebob, $this->config['ssh_user'], $this->config['ssh_pubkey_path'], $this->config['ssh_privatekey_path'], $this->config['ssh_pubkey_passphrase'])) {
							trigger_error('Unable to connect using the public keys specified at '. $this->config['ssh_pubkey_path'] .' (for the public key), '. $this->config['ssh_privatekey_path'] .' (for the private key) on '. $this->config['ssh_user'] .'@'. $this->config['ssh_host'] .':'. $port .' (Using a passphrase to decrypt the key)');
							return false;
						}
					} else {
						if (!ssh2_auth_pubkey_file($spongebob, $this->config['ssh_user'], $this->config['ssh_pubkey_path'], $this->config['ssh_privatekey_path'])) {
							trigger_error('Unable to connect using the public keys specified at '. $this->config['ssh_pubkey_path'] .' (for the public key), '. $this->config['ssh_privatekey_path'] .' (for the private key) on '. $this->config['ssh_user'] .'@'. $this->config['ssh_host'] .':'. $port .' (Not using a passphrase to decrypt the key)');
							return false;
						}
					}
				} elseif ($this->config['ssh_password'] != '') { // While some people *could* have blank passwords, it's a really stupid idea.
					if (!ssh2_auth_password($spongebob, $this->config['ssh_user'], $this->config['ssh_password'])) {
						trigger_error('Unable to connect using the username and password combination for '. $this->config['ssh_user'] .'@'. $this->config['ssh_host'] .':'. $port);
						return false;
					}
				} else {
					trigger_error('Neither a password or paths to public & private keys were specified in the configuration.');
					return false;
				}
				
				$tunnel = ssh_tunnel($spongebob, $this->config['host'], $this->config['port']);
				if (!$tunnel) {
					trigger_error('A SSH tunnel was unable to be created to access '. $this->config['host'] .':'. $this->config['port'] .' on '. $this->config['ssh_user'] .'@'. $this->config['ssh_host'] .':'. $port);
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
			$this->connection = new MongoDB\Driver\Manager($this->createConnectionName());
			
			// TODO: Write some code to test the connection to the server and apply error handling if this fails.
			
			// TODO: Figure out what is setting $this->_config['slaveok'], as I can't find it here or in /src/Database/connection.php, nor in the variables declared...
			if (isset($this->_config['slaveok'])) { 
				$rp = new MongoDB\Driver\ReadPreference($this->_config['slaveok']
					? MongoDB\Driver\ReadPreference::RP_SECONDARY_PREFERRED : MongoDB\Driver\ReadPreference::RP_PRIMARY);
			}
			$serverStatus = new MongoDB\Driver\Command("db.serverStatus()");

			// It returns MongoDB\Driver\Cursor on success, but what does it return on failure? O.o
			// Nothing? One can wonder because the function, when instantiated, returns MongoDB\Driver\Cursor... and I'm not sure you can
			// multitype when you define the value it does return.
			if ($this->connection->executeCommand($this->_config['database'], $serverStatus)) {
				$this->connected = true;
			}

		} catch ($e) { // The data in this catch() function is not only the wrong type for the new driver, MongoExceptions are divided in the new documentation.
			/****************************************************************************
			 *
			 * THIS CATCH CLAUSE...
			 * Well, plus side... once we know more about the types of errors caught in these
			 * exceptions, we can improve error handling.
			 *
			 ****************************************************************************/
			if (is_a($e, "MongoDB\Driver\Exception\AuthenticationException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\BulkWriteException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\ConnectionException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\ConnectionTimeoutException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\ExecutionTimeoutException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\InvalidArgumentException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\LogicException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\RuntimeException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\SSLConnectionException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\UnexpectedValueException")) {
				trigger_error($e->message);
			} elseif (is_a($e, "MongoDB\Driver\Exception\WriteException")) {
				trigger_error($e->message);
			} else {
				// Well, what the hell else could it be?
			}
		}
		return $this->isConnected();
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
	 * @param string $collectionName
	 * @return MongoDB\Driver\Cursor|bool If it can't select the collection, it SHOULD return false. If it can, it SHOULD return the MongoDB\Driver\Cursor object.
	 * @todo Determine whether this is necessary, as MongoDB\Driver\Manager does not have a means to select the collection and every collection is namespaced anyway.
	 * @access public
	 */
	public function getCollection($collectionName = '') {
		if (!empty($collectionName)) {
			if (!$this->isConnected()) { // TODO: Double check if this function needs to be rewritten. Probably does, but better safe than sorry.
				$this->connect();
			}
			// TODO: Seriously, write the query. It needs parameters.
			$query = new MongoDB\Driver\Query();

			return $this->connection->executeQuery($this->_config['database'].'.'.$collectionName, $query);
		}
		return false;
	}

	/**
	 * disconnect from the database
	 * 
	 * @return boolean
	 * @access public
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
	 * @return booelan
	 * @access public
	 * @todo Make this interactive and GET RID OF $this->connected as a variable.
	 */
	public function isConnected() {
		return $this->connected;
	}
}