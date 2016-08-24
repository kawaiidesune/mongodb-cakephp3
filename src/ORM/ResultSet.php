<?php
/**
 * @author Véronique Bellamy <v@vero.moe>
 * @license MIT
 *
 * @since 0.1-dev
 */
namespace Hayko\Mongodb\ORM;

class ResultSet {
	/**
	 * Store the conversion of mongo cursor into array
	 * 
	 * @access protected
	 * @used-by ResultSet::__construct()
	 * @var array $_results
	 */
	protected $_results;

	/**
	 * Table name
	 * 
	 * @access protected
	 * @used-by ResultSet::__construct()
	 * @var string $_table
	 */
	protected $_table;

	/**
	 * Set results and table name
	 * 
	 * @access public
	 * @param \MongoDB\Driver\Cursor $cursor
	 * @param string $table
	 * @used-by Table::find()
	 * @uses ResultSet::_results
	 * @uses ResultSet::_table
	 */
	public function __construct(\MongoDB\Driver\Cursor $cursor, string $table) {
		$this->_results = $cursor->toArray();
		$this->_table = $table;
	}

	/**
	 * Convert mongo documents in cake entities
	 * 
	 * @access public
	 * @return []Cake\ORM\Entity $results
	 * @todo Check to see if this toArray function is used elsewhere. It's really not necessary.
	 * @used-by Table::find()
	 * @uses Document::__construct()
	 * @uses Document::cakefy()
	 */
	public function toArray() {
		$results = [];
		foreach ($this->_results as $result) {
			$document = new Document($result, $this->_table);
			$results[] = $document->cakefy();
		}
		return $results;
	}
}