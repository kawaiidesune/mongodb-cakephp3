<?php
/**
 * @author Véronique Bellamy <v@vero.moe>
 * @license MIT
 *
 * @since 0.1-dev
 */
namespace Hayko\Mongodb\ORM;

use Cake\Datasource\EntityInterface;

class MongoQuery {
	/**
	 * set results
	 * 
	 * @access protected
	 * @used-by MongoQuery::__construct()
	 * @used-by MongoQuery::all()
	 * @var array $_results
	 */
	protected $_results;

	/**
	 * set number of rows
	 * 
	 * @access protected
	 * @used-by MongoQuery::__construct()
	 * @used-by MongoQuery::count()
	 * @var int $_rows
	 */
	protected $_rows;

	/**
	 * set the results and number of rows
	 * 
	 * @access public
	 * @param array $results
	 * @param int $rows
	 * @used-by Table::find()
	 * @uses MongoQuery::_results
	 * @uses MongoQuery::_rows
	 */
	public function __construct(array $results, int $rows) {
		$this->_results = $results;
		$this->_rows = $rows;
	}

	/**
	 * return array with results
	 * 
	 * @access public
	 * @return array
	 * @uses MongoQuery::_results
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
	 * @access public
	 * @return int
	 * @uses MongoQuery::_rows Again, as I said, this function appears to be a proxy to publicly access $this->_rows, but why bother?
	 */
	public function count() {
		return $this->_rows;
	}
}