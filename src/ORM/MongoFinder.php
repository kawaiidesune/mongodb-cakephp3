<?php
/**
 * @author Véronique Bellamy <v@vero.moe>
 * @license MIT
 *
 * @since 0.1-dev
 */
namespace Hayko\Mongodb\ORM;

use Cake\Datasource\EntityInterface;

class MongoFinder {
	/**
	 * connection with db
	 * 
	 * @var Mongo $_connection
	 * @access protected
	 * @used-by MongoFinder::connection()
	 */
	protected $_connection;

	/**
	 * default options for find
	 * 
	 * @var array $_options
	 * @access protected
	 * @used-by MongoFinder::__construct()
	 * @used-by MongoFinder::find()
	 * @used-by MongoFinder::get()
	 */
	protected $_options = [
		'fields' => [],
		'where' => [],
	];

	/**
	 * total number of rows
	 * 
	 * @var int $_totalRows
	 * @access protected
	 * @used-by MongoFinder::count()
	 * @used-by MongoFinder::find()
	 */
	protected $_totalRows;

	/**
	 * set connection and options to find
	 * 
	 * @param Mongo $connection
	 * @param array $options
	 * @access public
	 * @uses MongoFinder::_options
	 */
	public function __construct($connection, $options = []) {
		$this->connection($connection);
		$this->_options = array_merge_recursive($this->_options, $options);

		if (isset($options['conditions']) && !empty($options['conditions'])) {
			$this->_options['where'] += $options['conditions'];
			unset($this->_options['conditions']);
		}

		$this->__normalizeFieldsName($this->_options);
		if (!empty($this->_options['where'])) {				
			$this->__translateConditions($this->_options['where']);
		}
	}

	/**
	 * connection
	 * 
	 * @param Mongo $connection
	 * @return Mongo
	 * @access public
	 * @uses MongoFinder::_connection
	 */
	public function connection($connection = null) {
		if ($connection === null) {
			return $this->_connection;
		}

		$this->_connection = $connection;
	}

	/**
	 * remove model name from the key
	 * 
	 * example: Categories.name -> name
	 * @param array $data
	 * @access private
	 */
	private function __normalizeFieldsName(&$data) {
		foreach ($data as $key => &$value) {
			if (is_array($value)) {
				$this->__normalizeFieldsName($value);
			}
			if (strpos($key, '.') !== false) {
				list($collection, $field) = explode('.', $key);
				$data[$field] = $value;
				unset($data[$key]);
			}
		}
	}

	/**
	 * convert sql conditions into mongodb conditions
	 * 
	 * '!=' => '$ne',
	 * '>' => '$gt',
	 * '>=' => '$gte',
	 * '<' => '$lt',
	 * '<=' => '$lte',
	 * 'IN' => '$in',
	 * 'NOT' => '$not',
	 * 'NOT IN' => '$nin'
	 * 
	 * @param array $conditions
	 * @access private
	 * @uses \MongoDB\BSON\Regex::__construct()
	 */
	private function __translateConditions(&$conditions) {
		foreach ($conditions as $key => &$value) {
			$uKey = strtoupper($key);
			if (substr($uKey, -6) === 'NOT IN') {
				// 'Special' case because it has a space in it, and it's the whole key
				$field = trim(substr($key, 0, -6));

				$conditions[$field]['$nin'] = $value;
				unset($conditions[$key]);
				continue;
			}
			if ($uKey === 'OR') {
				unset($conditions[$key]);
				foreach($value as $key => $part) {
					$part = array($key => $part);
					$this->__translateConditions($Model, $part);
					$conditions['$or'][] = $part;
				}
				continue;
			}
			if ($key === '_id' && is_array($value)) {
				//_id=>array(1,2,3) pattern, set  $in operator
				$isMongoOperator = false;
				foreach($value as $idKey => $idValue) {
					//check a mongo operator exists
					if(substr($idKey,0,1) === '$') {
						$isMongoOperator = true;
						continue;
					}
				}
				unset($idKey, $idValue);
				if($isMongoOperator === false) {
					$conditions[$key] = array('$in' => $value);
				}
				continue;
			}
			if (is_numeric($key) && is_array($value)) {
				if ($this->_translateConditions($Model, $value)) {
					continue;
				}
			}
			if (substr($uKey, -3) === 'NOT') {
				// 'Special' case because it's awkward
				$childKey = key($value);
				$childValue = current($value);

				if (in_array(substr($childKey, -1), array('>', '<', '='))) {
					$parts = explode(' ', $childKey);
					$operator = array_pop($parts);
					if ($operator = $this->_translateOperator($Model, $operator)) {
						$childKey = implode(' ', $parts);
					}
				} else {
					$conditions[$childKey]['$nin'] = (array)$childValue;
					unset($conditions['NOT']);
					continue;
				}

				$conditions[$childKey]['$not'][$operator] = $childValue;
				unset($conditions['NOT']);
				continue;
			}
			if (substr($uKey, -4) == 'LIKE') {
				if ($value[0] === '%') {
					$value = substr($value, 1);
				} else {
					$value = '^' . $value;
				}
				if (substr($value, -1) === '%') {
					$value = substr($value, 0, -1);
				} else {
					$value .= '$';
				}
				$value = str_replace('%', '.*', $value);

				$conditions[substr($key, 0, -5)] = new \MongoDB\BSON\Regex("/$value/i"); // TODO: Unlike MongoRegex in the old driver, MongoDB\BSON\Regex actually requires two parameters in the constructor, $pattern and $flags. We're passing the patterns but I wonder if we need to modify this to take flags into account... (Relevant PHP Documentation: http://php.net/manual/en/mongodb-bson-regex.construct.php )
				unset($conditions[$key]);
			}
			if (!in_array(substr($key, -1), array('>', '<', '='))) {
				continue;
			}
			$parts = explode(' ', $key);
			$operator = array_pop($parts);
			if ($operator = $this->_translateOperator($Model, $operator)) {
				$newKey = implode(' ', $parts);
				$conditions[$newKey][$operator] = $value;
				unset($conditions[$key]);
			}
			if (is_array($value)) {
				if ($this->_translateConditions($Model, $value)) {
					continue;
				}
			}
		}
		return $conditions;
	}

	/**
	 * try to find documents
	 * 
	 * @return MongoCursor $cursor
	 * @access public
	 * @uses MongoFinder::_options
	 * @uses MongoFinder::_totalRows
	 * @used-by MongoFinder::findAll()
	 * @used-by MongoFinder::findList()
	 * @used-by MongoFinder::get()
	 */
	public function find() {
		$cursor = $this->connection()->find($this->_options['where'], $this->_options['fields']);
		$this->_totalRows = $cursor->count();

		if ($this->_totalRows > 0) {
			if (!empty($this->_options['order'])) {
				foreach ($this->_options['order'] as $field => $direction) {
					$sort[$field] = $direction == 'asc' ? 1 : -1;
				}
				$cursor->sort($sort);
			}

			if (!empty($this->_options['page']) && $this->_options['page'] > 1) {
				$skip = $this->_options['limit'] * ($this->_options['page'] - 1);
				$cursor->skip($skip);
			}

			if (!empty($this->_options['limit']))
				$cursor->limit($this->_options['limit']);
		}

		return $cursor;
	}

	/**
	 * return all documents
	 * 
	 * @return MongoCursor
	 * @access public
	 * @uses MongoFinder::find() ... But, why bother? It looks like it is just a proxy for calling MongoFinder::find() with no parameters...
	 */
	public function findAll() {
		return $this->find();
	}

	/**
	 * return all documents
	 * 
	 * @return MongoCursor
	 * @access public
	 * @uses MongoFinder::find() ... But, why bother? It looks like it is just a proxy for calling MongoFinder::find() with no parameters...
	 */
	public function findList() {
		return $this->find();
	}

	/**
	 * return document with _id = $primaKey
	 * 
	 * @param string $primaryKey
	 * @return MongoCursor
	 * @access public
	 * @uses \MongoDB\BSON\ObjectId::__construct()
	 * @uses MongoFinder::_options
	 * @uses MongoFinder::find()
	 */
	public function get($primaryKey) {
		$this->_options['where']['_id'] = new \MongoDB\BSON\ObjectId($primaryKey);
		return $this->find();
	}

	/**
	 * return number of rows finded
	 * 
	 * @return int
	 * @access public
	 * @uses MongoFinder::_totalRows
	 */
	public function count() {
		return $this->_totalRows;
	}
}