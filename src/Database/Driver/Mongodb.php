<?php
namespace Hayko\Mongodb\Database\Driver;
use MongoDB\Driver\Manager;
use MongoDB\Driver\ReadPreference;

class Mongodb {
	/**
	 * Config
	 * 
	 * @var array
	 * @access private
	 */
	private $_config;

	/**
	 * Are we connected to the DataSource?
	 *
	 * true - yes
	 * false - nope, and we can't connect
	 *
	 * @var boolean
	 * @access public
	 */
	public $connected = false;

	/**
	 * Database Instance
	 *
	 * @var resource
	 * @access protected
	 */
	protected $_db = null;

	/**
	 * Mongo Driver Version
	 *
	 * @var string
	 * @access protected
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
					if (method_exists($this->connection, 'setSlaveOkay')) {
						// $this->connection->setSlaveOkay($this->_config['slaveok']);
						// What the hell is the "setSlaveOkay" method and if so, has it been replicated in the new driver... and as what?
					} else {
						$rp = new MongoDB\Driver\ReadPreference($this->_config['slaveok']
							? MongoDB\Driver\ReadPreference::RP_SECONDARY_PREFERRED : MongoDB\Driver\ReadPreference::RP_PRIMARY);
					}
				}
				$serverStatus = new MongoDB\Driver\Command("db.serverStatus()");

				// It returns MongoDB\Driver\Cursor on success, but what does it return on failure? O.o
				// Nothing? One can wonder because the function, when instantiated, returns MongoDB\Driver\Cursor... and I'm not sure you can
				// multitype when you define the value it does return.
				if ($this->connection->executeCommand($this->_config['database'], $serverStatus)) {
					$this->connected = true;
				}

			} catch (MongoException $e) { // The data in this catch() function is not only the wrong type for the new driver, MongoExceptions are divided in the new documentation.
				/****************************************************************************
				 *
				 * THIS CATCH CLAUSE...
				 * This obviously needs to be rewritten, as MongoException has been expanded out
				 * to several different classes. Calling getMessage() on this variable may not
				 * even work.
				 *
				 * So, basically, one would have their work cut out for them, but it is necessary,
				 * as if something goes wrong in this function... we *do* need to know and apply
				 * appropriate error handling.
				 *
				 ****************************************************************************/
				trigger_error($e->getMessage());
				$this->connected = false;
			}
			return $this->connected;
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
			$host .= $this->_config['login'] . ':' . $this->_config['password'] . '@' . $hostname . '/' . $this->_config['database'];
		} else {
			$host .= $hostname;
		}
		return $host;
	}

	/**
	 * return MongoCollection object
	 * 
	 * @param string $collectionName
	 * @return \MongoCollection
	 * @access public
	 */
		public function getCollection($collectionName = '') {
			if (!empty($collectionName)) {
				if (!$this->isConnected()) {
					$this->connect();
				}

				return new \MongoCollection($this->_db, $collectionName);
			}
			return false;
		}

	/**
	 * disconnect from the database
	 * 
	 * @return boolean
	 * @access public
	 */
		public function disconnect() {
			if ($this->connected) {
				$this->connected = !$this->connection->close();
				unset($this->_db, $this->connection);
				return !$this->connected;
			}
			return true;
		}

	/**
	 * database connection status
	 * 
	 * @return booelan
	 * @access public
	 */
		public function isConnected() {
			return $this->connected;
		}

}