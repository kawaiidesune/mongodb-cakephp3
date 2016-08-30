<?php 
/**
 * @author Véronique Bellamy <v@vero.moe>
 * @license MIT
 *
 * @since 0.1-dev
 */
namespace Hayko\Mongodb\ORM;

use ArrayObject;
use BadMethodCallException;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\InvalidPrimaryKeyException;
use Cake\ORM\Exception\MissingEntityException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table as CakeTable;
use Hayko\Mongodb\ORM\Behavior\SchemalessBehavior; // Where is this declared? If this is somewhere else, he namespaced it POORLY.
use MongoDB\BSON\ObjectID;
use RuntimeException;

class Table extends CakeTable {
	/**
	 * return MongoCollection object
	 * 
	 * @access private
	 * @return MongoDB\Driver\Cursor
	 * @used-by Table::_update()
	 */
	private function __getCollection() {
		$driver = $this->connection()->driver();
        $collection = $driver->getCollection($this->table());
        return $collection;
	}

	/**
	 * always return true because mongo is schemaless
	 * 
	 * @access public
	 * @param string $field
	 * @return bool
	 */
	public function hasField($field) {
		return true;
	}

	/**
	 * find documents
	 * 
	 * @access public
	 * @param array $options
	 * @param string $type
	 * @return MongoQuery|Cake\ORM\Entity
	 * @throws BadMethodCallException If the method defined in $query and $method doesn't exist.
	 * @uses MongoFinder::__construct()
	 * @uses MongoQuery::__construct()
	 * @uses ResultSet::__construct()
	 * @uses ResultSet::toArray()
	 */
	public function find($type = 'all', $options = []) {
		$query = new MongoFinder($this->__getCollection(), $options);
		$method = 'find' . ucfirst($type);
		if (method_exists($query, $method)) {
			$mongoCursor = $query->{$method}();
			$results = new ResultSet($mongoCursor, $this->alias());
			if (isset($options['whitelist'])) {
				return new MongoQuery($results->toArray(), $query->count()); // Rewrite Query
			} else {
				return $results->toArray();
			}
		}
		throw new BadMethodCallException(
            sprintf('Unknown method "%s"', $method)
        );
	}

	/**
	 * get the document by _id
	 * 
	 * @access public
	 * @param string $primaryKey
	 * @param array $options
	 * @return Cake\ORM\Entity
	 * @uses Document::__construct()
	 * @uses Document::cakefy()
	 * @uses MongoFinder::__construct()
	 * @uses MongoFinder::get()
	 */
	public function get($primaryKey, $options = []) {
		$query = new MongoFinder($this->__getCollection(), $options);
		$mongoCursor = $query->get($primaryKey);

		//if find document, convert to cake entity
		if ($mongoCursor->count()) {
			$document = new Document(current(iterator_to_array($mongoCursor)), $this->alias()); // This obviously refers to Hayko's Document class in /src/ORM, because if not, I'm buying airfare to strangle someone.
			return $document->cakefy();
		}

		throw new InvalidPrimaryKeyException(sprintf(
            'Record not found in table "%s" with primary key [%s]',
            $this->_table->table(),
            $primaryKey
        ));
	}

	/**
	 * Remove a single document
	 * 
	 * @access public
	 * @param Cake\Datasource\EntityInterface $entity
	 * @param array $options
	 * @return bool If the function successfully removes the entity, it returns true. If not, it returns false.
	 * @since 0.1-dev The try catch function used to be typecast as MongoException, but I removed it given how many Mongo Exceptions were created in the new API. Not sure what the impact will be of NOT typecasting it, but it's important to note.
	 * @uses \MongoDB\BSON\ObjectId::__construct()
	 */
	public function delete(EntityInterface $entity, $options = []) {
		try {
			$collection = $this->__getCollection();
			$success = $collection->remove(['_id' => new \MongoDB\BSON\ObjectId($entity->_id)]);
		} catch (\MongoDB\Driver\Exception\Exception $e) {
			trigger_error($e->getMessage());
			return false;
		}
		return true;
	}

	/**
	 * save the document
	 * 
	 * @access public
	 * @param \Cake\ORM\Entity $entity
	 * @param array $options
	 * @return mixed $success
	 * @since 0.1-dev This was typecast as an EntityInterface in the function but declared to be \Cake\ORM\Entity in the PHPDoc. Not sure which is valid.
	 * @uses ArrayObject::__construct()
	 * @uses \MongoDB\BSON\UTCDateTime()
	 */
	public function save(EntityInterface $entity, $options = []) {
		$options = new ArrayObject($options + [
            'checkRules' => true,
            'checkExisting' => true,
            '_primary' => true
        ]);

		if ($entity->errors()) {
			return false;
		}

		if ($entity->isNew() === false && !$entity->dirty()) {
			return $entity;
		}

		$success = $this->_processSave($entity, $options); // What does this return? A boolean? Or is it a mixed value?
		if ($success) {
			if ($options['_primary']) {
				$this->dispatchEvent('Model.afterSaveCommit', compact('entity', 'options'));
				$entity->isNew(false);
				$entity->source($this->registryAlias());
			}
		}
		return $success;
	}

	/**
	 * Insert or update the document
	 * 
	 * @access protected
	 * @param \Cake\ORM\Entity $entity
	 * @param array $options
	 * @return mixed $success
	 */
	protected function _processSave($entity, $options) {
		$mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;
        if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
            return false;
        }

        $event = $this->dispatchEvent('Model.beforeSave', compact('entity', 'options'));
        if ($event->isStopped()) {
            return $event->result;
        }

        $data = $entity->toArray();
        $isNew = $entity->isNew();

        if (isset($data['created'])) {
        	$data['created']  = new \MongoDB\BSON\UTCDateTime(strtotime($data['created']->toDateTimeString())); // TODO: Convert to Unix epoch, if necessary.
        }
        if (isset($data['modified'])) {
        	$data['modified'] = new \MongoDB\BSON\UTCDateTime(strtotime($data['modified']->toDateTimeString())); // TODO: Convert to Unix epoch, if necessary.
        }

        if ($isNew) {
            $success = $this->_insert($entity, $data);
        } else {
            $success = $this->_update($entity, $data);
        }
		
		if ($success) {
            $this->dispatchEvent('Model.afterSave', compact('entity', 'options'));
            $entity->clean();
            if (!$options['_primary']) {
                $entity->isNew(false);
                $entity->source($this->registryAlias());
            }
            $success = true;
        }

		if (!$success && $isNew) {
            $entity->unsetProperty($this->primaryKey());
            $entity->isNew(true);
        }

        if ($success) {
            return $entity;
        }
		return false;
	}

	/**
	 * insert new document
	 * 
	 * @param \Cake\ORM\Entity $entity
	 * @param array $data
	 * @return mixed $success
	 * @access protected
	 * @throws RunTimeException if $this->primaryKey() as assigned to $primary is an empty array.
	 * @uses Table::_newId()
	 */
	protected function _insert($entity, $data) {
		$primary = (array)$this->primaryKey();
		if (empty($primary)) {
            $msg = sprintf(
                'Cannot insert row in "%s" table, it has no primary key.',
                $this->table()
            );
            throw new RuntimeException($msg);
        }
		$primary = ['_id' => $this->_newId($primary)];

        $filteredKeys = array_filter($primary, 'strlen');
        $data = $data + $filteredKeys;

        $success = false;
        if (empty($data)) {
        	return $success;
        }

        $success = $entity; // TODO: Shouldn't we be returning a BSON\ObjectId instead?
        $collection = $this->__getCollection();

        if (is_object($collection)) {
	        $r = new \MongoDB\Driver\BulkWrite();
        	if (!$r->insert($data)) {
        		$success = false;
        	}
        }
		return $success;
	}

	/**
	 * update one document
	 * 
	 * @param \Cake\ORM\Entity $entity
	 * @param array $data
	 * @return mixed $success
	 * @access protected
	 * @uses \MongoDB\BSON\ObjectId::__construct()
	 * @uses Table::__getCollection()
	 */
	protected function _update($entity, $data) {
		unset($data['_id']);

		$success = $entity;
        $collection = $this->__getCollection();
        if (is_object($collection)) {
        	$r = $collection->update(['_id' => new \MongoDB\BSON\ObjectId($entity->_id)], $data); // TODO: Check to see if this is taking the correct parameters in the ObjectId constructor.
        	if ($r['ok'] == false) {
        		$success = false;
        	}
        }
		return $success;
	}

	/**
	 * create new MongoId
	 * 
	 * @access protected
	 * @param mixed $primary
	 * @return \MongoDB\BSON\ObjectID()
	 * @used-by Table::_insert()
	 * @uses MongoDB\BSON\ObjectID::__construct()
	 */
	protected function _newId($primary) {
		if (!$primary || count((array)$primary) > 1) {
            return null;
        }
        return new \MongoDB\BSON\ObjectID();
	}
}