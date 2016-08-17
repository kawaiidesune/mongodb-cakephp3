<?php 

namespace Hayko\Mongodb\ORM;

use Cake\I18n\Time;
use Cake\ORM\Entity;

class Document {
	/**
	 * store the document
	 * 
	 * @var array $_document
	 * @access protected
	 */
	protected $_document;

	/**
	 * table model name
	 * 
	 * @var string $_registryAlias
	 * @access protected
	 */
	protected $_registryAlias;

	/**
	 * set document and table name
	 * 
	 * @param array $document
	 * @param string $table
	 * @access public
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
	 */
	public function cakefy() {
		// The thing is, we need to make sure that this code still works and, if it does, to 
		foreach ($this->_document as $field => $value) {
			$type = gettype($value);
			if ($type == 'object') {
				switch (get_class($value)) {
					case 'MongoId': // It would appear that this has been replaced by MongoDB\BSON\ObjectID
						$document[$field] = $value->__toString();
						break;
					case 'MongoDate': // It would appear that this has been replaced by MongoDB\BSON\UTCDateTime
						$document[$field] = new Time($value->sec);
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