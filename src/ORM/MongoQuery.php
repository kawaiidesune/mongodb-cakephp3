<?php
namespace Hayko\Mongodb\ORM;

use Cake\Datasource\EntityInterface;

class MongoQuery {
	/**
	 * set results
	 * 
	 * @var array $_results
	 * @access protected
	 */
	protected $_results;

	/**
	 * set number of rows
	 * 
	 * @var int $_rows
	 * @access protected
	 */
	protected $_rows;

	/**
	 * set the results and number of rows
	 * 
	 * @param array $results
	 * @param int $rows
	 * @access public
	 */
	public function __construct($results, $rows) {
		$this->_results = $results;
		$this->_rows = $rows;
	}

	/**
	 * return array with results
	 * 
	 * @return array
	 * @access public
	 */
	public function all() {
		return $this->_results;
	}

	/**
	 * return number of rows
	 *
	 * Okay, so what does the counting? Why do we need to pass the number of rows to this function?
	 * It seems to defy DRY principles to not have the code in this class already, either in the 
	 * constructor or in here...
	 * 
	 * @return int
	 * @access public
	 */
	public function count() {
		return $this->_rows;
	}
}