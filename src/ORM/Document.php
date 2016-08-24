<?php
/**
 * @author Véronique Bellamy <v@vero.moe>
 * @license MIT
 *
 * @since 0.1-dev
 */
namespace Hayko\Mongodb\ORM;

use Cake\I18n\Time;
use Cake\ORM\Entity;

class Document {
	/**
	 * store the document
	 * 
	 * @var array $_document
	 * @access protected
	 * @used-by Document::__construct()
	 * @used-by Document::cakefy()
	 */
	protected $_document;

	/**
	 * Table model name
	 * 
	 * @var string $_registryAlias
	 * @access protected
	 * @used-by Document::__construct()
	 * @used-by Document::cakefy()
	 */
	protected $_registryAlias;

	/**
	 * set document and table name
	 * 
	 * @param array $document
	 * @param string $table
	 * @access public
	 * @uses Document::_document Sets Document::_document to the $document array passed to it.
	 * @uses Document::_registryAlias Apparently, it sets _registryAlias to the table model name that was supplied.
	 * @used-by ResultSet::toArray()
	 */
	public function __construct(Array $document, $table) {
		$this->_document = $document;
		$this->_registryAlias = $table;
	}

	/**
	 * convert mongo document into cake entity
	 * 
	 * @return Cake\ORM\Entity
	 * @access public
	 * @uses Document::_document
	 * @uses Document::cakefy() Almost recursively...
	 * @used-by ResultSet::toArray()
	 */
	public function cakefy() {
		// The thing is, we need to make sure that this code still works and, if it does, to 
		foreach ($this->_document as $field => $value) {
			$type = gettype($value);
			if ($type == 'object') {
				switch (get_class($value)) {
					case '\MongoDB\BSON\ObjectID': // If this fails, it might be due to the slash at the beginning to signal the global namespace.
						$document[$field] = $value->__toString();
						break;
					case '\MongoDB\BSON\UTCDateTime': // Same as above.
						$document[$field] = new Time($value->sec); // TODO: Might want to doublecheck and see if this needs to be converted to Unix epoch, already is or not.
						break;
					default:
						throw new Exception(get_class($value) . ' conversion not implemented.');								
						break;
				}
			} elseif ($type == 'array') {
				$document[$field] = $this->cakefy($value);
			} else {
				$document[$field] = $value;
			}
		}
		return new Entity($document, ['markClean' => true, 'markNew' => false, 'source' => $this->_registryAlias]);
	}
}