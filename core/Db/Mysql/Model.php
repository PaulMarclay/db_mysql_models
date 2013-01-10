<?php
    /*
    *   DB_MYSQL_MODELS3 version 1.0
    *
    *   Imagina - Plugin.
    *
    *
    *   Copyright (c) 2012 Dolem Labs
    *
    *   Authors:    Paul Marclay (paul.eduardo.marclay@gmail.com)
    *
    */

	class Db_Mysql_Model extends Ancestor {
        
        protected $_sql             = NULL;
        protected $_modelName       = '';
        protected $_connectionName  = '';
        protected $_tableName       = '';
        protected $_fields          = '';
        protected $_relationFields  = array();
        protected $_fieldOperations = array();
        protected $_fieldIndex      = '';
        protected $_lastErrors      = null;
        protected $_fieldCaption    = null;
        
        
        public function __construct($tableName, $modelName, $fieldIndex = null, $connectionName = 'default', $relationFields = array(), $fieldOperations = array(), $fieldCaption = null) {
            $this->setTableName($tableName);
            $this->setModelName($modelName);
            $this->setConnectionName($connectionName);
            $this->setRelationFields($relationFields);
            $this->setFieldOperations($fieldOperations);
            $this->setFieldIndex($fieldIndex);
            $this->setFieldCaption($fieldCaption);
            
            //@TODO: el ultimo parametro esta mal aca o en la clase, porque en la declaracion de la clase no esta.
            $this->setSql(new Db_Mysql_Sql_Interface($tableName, $fieldIndex, $connectionName));//, $relationFields));
            if ($fieldIndex == null) {
                $this->setFieldIndex($this->getSql()->getFieldIndex());
            }
            $this->_fields  = $this->getSql()->getFieldsArray();
		}

        // -- Dinamic methods 

        public function __call($method, $args) {
            if (substr($method, 0, 6) == 'findBy') {
                $key = Conversor::underscore(substr($method, 6));
                return $this->findBy($key, $args, false);
            }
            if (substr($method, 0, 9) == 'findAllBy') {
                $key = Conversor::underscore(substr($method, 9));
                return $this->findBy($key, $args, true);
            }
            if (substr($method, 0, 18) == 'findOrInitializeBy') {
                $key = Conversor::underscore(substr($method, 18));
                return $this->findOrInitializeOrCreate($key, $args, false);
            }
            if (substr($method, 0, 14) == 'findOrCreateBy') {
                $key = Conversor::underscore(substr($method, 14));
                return $this->findOrInitializeOrCreate($key, $args, true);
            }
            if (substr($method, 0, 8) == 'deleteBy') {
                $key = Conversor::underscore(substr($method, 8));
                return $this->deleteBy($key, $args);
            }
        }
        
        // -- Specific methods ( model actions )
		
		public function load($id = NULL) {
			if (!($res = $this->getSql()->load($id))) return false;
			
            $this->launchTrigger('beforeLoad', $id);
            
			if ($this->getSql()->getAffectedRows() == 0) {
				throw new Php_Exception("Record not found!.");
                return false;
			}
			
			$fields = mysql_fetch_assoc($res);
            $record = new Db_Record($this, $this->getSql()->getFieldIndex(), $id, $fields, $this->getRelationFieldNamesArray());
            
            if(!($result = $this->processProcessors($record, 'load'))) {
                return false;
            }

            $this->launchTrigger('afterLoad', $record);
            
			return $record;
		}

        public function save($record) {
            $this->launchTrigger('beforeValidate', $record);
            if (!($result = $this->validate($record))) {
                return false;
            }

            $this->launchTrigger('afterProcess', $record);
            if(!($result = $this->processProcessors($record, 'save'))) {
                return false;
            }

            $this->launchTrigger('beforeProcess', $record);
            
            $isNewRecord = ($record->{$this->getFieldIndex()} == null);

            if ($isNewRecord) $this->launchTrigger('beforeCreate', $record);
            $this->launchTrigger('beforeSave', $record);
			
            if (!($newId = $this->getSql()->save($record->getFieldIndexValue(), $record->updatedFieldsToArray()))) {
				return false;
			}
            
            if ($isNewRecord) $this->launchTrigger('afterCreate', $record);
            $this->launchTrigger('afterSave', $record);
            
			return $newId;
		}
		
        public function create($data = array()) {
            $fields = array();
            foreach($this->_fields as $field) {
                $fields[$field] = ((isset($data[$field])) ? $data[$field] : null);
            }
            
            $record = new Db_Record($this, $this->getSql()->getFieldIndex(), null, $fields, $this->getRelationFieldNamesArray());
            
            return $record;
        }
        
		public function delete($id) {
            $this->launchTrigger('beforeDelete', $id);
            
            $result = $this->getSql()->delete($id);
            
            $this->launchTrigger('afterDelete', $result);
            
			return $result;
		}

        // -- Sql Raw methods
        
        public function setSelect($select = '*') {
            $this->getSql()->setSelect($select);
            return $this;
        }
        
        public function setWhere($where = '1', $params = null) {
            $this->getSql()->setWhere($where, $params);
            return $this;
        }
        
        public function setOrderBy($orderBy = '') {
            $this->getSql()->setOrderBy($orderBy);
            return $this;
        }
        
        public function setGroupBy($groupBy = '') {
            $this->getSql()->setGroupBy($groupBy);
            return $this;
        }
        
        public function setHaving($having = '') {
            $this->getSql()->setHaving($having);
            return $this;
        }
        
        public function setLimit($limit = '') {
            $this->getSql()->setLimit($limit);
            return $this;
        }

        public function setFrom($from = '') {
            
        }
        
        // -- Specific methods ( model triggers )
		
        protected function launchTrigger($triggerName, &$data) {
            $modelInstance = Db::getModel($this->getModelName());
            if (method_exists($modelInstance, $triggerName)) {
                call_user_func_array(array($modelInstance, $triggerName), array($data));
            }
        }
        
        // -- Specific methods ( find )
        
        public function _find($limit = 0) {
            $collection = new Db_Collection($this->getModelName());
            
            if (!($res = $this->getSql()->find($limit))) return false;
			
            if ($this->getSql()->getAffectedRows() == 0) {
                return $collection;
			}
			
            while ($fields = mysql_fetch_assoc($res)) {
                $record = new Db_Record($this, $this->getFieldIndex(), $fields[$this->getFieldIndex()], $fields, $this->getRelationFieldNamesArray());
                
                if(!($result = $this->processProcessors($record, 'load'))) {
                    return false;
                }
                
                $collection->add($record);
            }
            
            return $collection;
		}

        public function find($id) {
            return $this->load($id);
        }
        
        public function findOne() {
            return $this->_find(1)->first();
        }
        
        public function findAll($load = false) {
            $collection = new Db_Collection($this->getModelName());
            
            if (!($res = $this->getSql()->findAll())) return false;
			
			if ($this->getSql()->getAffectedRows() == 0) {
                return $collection;
			}
			
            // if ($this->getSql()->getAffectedRows() == 1) {
            //     $fields = mysql_fetch_assoc($res);
            //     return new Db_Record($this, $this->getFieldIndex(), $fields[$this->getFieldIndex()], $fields, $this->getRelationFieldNamesArray());
            // }

            while ($fields = mysql_fetch_assoc($res)) {
                $record = new Db_Record($this, $this->getFieldIndex(), $fields[$this->getFieldIndex()], $fields, $this->getRelationFieldNamesArray());

                if(!($result = $this->processProcessors($record, 'load'))) {
                    return false;
                }
                
                $collection->add($record);
            }
            
            return $collection;
        }

        public function findInBatches($batchSize, $callbackFunction) {
            // buscar paginados.
            $cnt    = $this->count();
            $pages  = intval($cnt / $batchSize);

            for ($page = 1; $page <= $pages; $page++) {
                $block = $this->findAllPaginated($batchSize, $page);
                if ($block) {
                    $callbackFunction($block);
                }
            }
            
        }

        // -- leyendo ActiveRecord::Base de rails y sacando ideas.

        public function where($where = '1', $params = null) {
            $this->setWhere($where, $params);
            return $this->findAll();
        }

        public function joins($tables = array()) {
            // @TODO
            /*
                Post.joins(:category, :comments)

                SELECT posts.* FROM posts
                  INNER JOIN categories ON posts.category_id = categories.id
                  INNER JOIN comments ON comments.post_id = posts.id

                Post.joins(:comments => :guest)
                  SELECT posts.* FROM posts
                  INNER JOIN comments ON comments.post_id = posts.id
                  INNER JOIN guests ON guests.comment_id = comments.id
            */

                foreach ($tables as $table) {

                }

        }
        
        public function findOrInitializeOrCreate($key, $args, $create = false) {
            $ret = $this->findBy($key, $args, false);
            if (!$ret) {
                $ands   = explode('_and_', $key);
                $fields = array();
                $cnt    = 0;
                
                foreach ($ands as $and) {
                    $ors = explode('_or_', $and);
                    
                    foreach($ors as $or) {
                        $fields[$or] = $args[$cnt];
                        $cnt++;
                    }
                }

                $ret = $this->create();
                $ret->setDataArray($fields);

                if ($create) {
                    $ret->save();
                    $ret->reload();
                }
            }

            return $ret;
        }

        public function deleteBy($key, $args) {
            $collection = $this->findBy($key, $args, true);
            $cnt        = $collection->count();

            foreach($collection as $record) {
                $record->delete();
            }

            return $cnt;
        }

        // --

        public function findAllPaginated($pageSize, $pageNumber) {
            $begin = ($pageNumber - 1) * $pageSize; 
            $query = "$begin, $pageSize";
            
            return $this->setLimit($query)->findAll();
        }
        
        public function findBy($key, $args, $all = false) {
            $ands   = explode('_and_', $key);
            $sql    = '';
            $cnt    = 0;
            
            foreach ($ands as $and) {
                $ors = explode('_or_', $and);
                
                foreach($ors as $or) {
                    $sql .= "`$or`='{$args[$cnt]}'";
                    $sql .= ((end($ors) != $or) ? ' or ' : '');
                    
                    $cnt++;
                }
                $sql .= ((end($ands) != $and) ? ' and ' : '');
            }
            
            $this->setWhere($sql);
            if ($all) {
                return $this->findAll();
            } else {
                return $this->findOne();
            }
        }
        
        public function count($applyLimit = false) {
            if (!($res = $this->getSql()->count($applyLimit))) return false;
            
            if (!$fields = mysql_fetch_assoc($res)) return false;
            
            return $fields['count'];
        }
        
        // -- Getters
        
        public function getSql() {
            return $this->_sql;
        }
        
        public function getModelName() {
            return $this->_modelName;
        }
        
        public function getConnectionName() {
            return $this->_connectionName;
        }
        
        public function getTableName() {
            return $this->_tableName;
        }
        
        public function getRelationFields() {
            return $this->_relationFields;
        }
        
        public function getFieldIndex() {
            return $this->_fieldIndex;
        }
        
        public function getPageNumber() {
            return $this->_pageNumber;
        }
        
        public function getPageSize() {
            return $this->_pageSize;
        }

        public function getLastErrors() {
            return $this->_lastErrors;
        }

        // -- Setters
        
        public function setSql($sqlDriver) {
            $this->_sql = $sqlDriver;
        }
        
        public function setModelName($modelName) {
            $this->_modelName = $modelName;
        }
        
        public function setConnectionName($connectionName) {
            $this->_connectionName = $connectionName;
        }
        
        public function setTableName($tableName) {
            $this->_tableName = $tableName;
        }
        
        public function setRelationFields($relationFields) {
            $this->_relationFields = $relationFields;
        }

        public function setFieldOperations($fieldOperations) {
            $this->_fieldOperations = $fieldOperations;
        }

        public function setFieldIndex($fieldIndex = 'id') {
            $this->_fieldIndex = $fieldIndex;
        }
        
        public function setFieldCaption($fieldCaption = null) {
            $this->_fieldCaption = $fieldCaption;
        }
        
        public function setPageNumber($pageNumber = 1) {
            $this->_pageNumber = $pageNumber;
            return $this;
        }
        
        public function setPageSize($pageSize = 1) {
            $this->_pageSize = $pageSize;
            return $this;
        }

        public function setLastErrors($lastErrors) {
            $this->_lastErrors = $lastErrors;
        }

        public function addToLastErrors($lastErrors) {
            if (is_array($lastErrors)) {
                foreach ($lastErrors as $error) {
                    $this->_lastErrors[] = $error;
                }
            } else {
                $this->_lastErrors[] = $lastErrors;
            }
        }
        
        // -- Relation
        
        public function getRelationFieldsInfo() {
            $names = array();
            foreach ($this->getRelationFields() as $relation) {
                $arrRelation = explode(' ', $relation);
                $names[$arrRelation[1]] = array('type' => $arrRelation[0], 'foreign_key' => $arrRelation[2]);
            }
            
            return $names;
        }
        
        public function getRelationFieldInfo($relationField) {
            foreach ($this->getRelationFields() as $relation) {
                $arrRelation = explode(' ', $relation);
                if ($arrRelation[1] == $relationField) {
                    switch ($arrRelation[0]) {
                        case 'belongs_to':
                            return array('name' => $arrRelation[1], 'type' => $arrRelation[0], 'foreign_key' => $arrRelation[2]);
                            break;
                        
                        case 'has_many':
                            return array('name' => $arrRelation[1], 'type' => $arrRelation[0], 'foreign_key' => $arrRelation[2]);
                            break;

                        // case 'delegate':
                        //     Debugger::debug($arrRelation);
                        //     break;
                        default:
                            # code...
                            break;
                    }
                    
                    return array('name' => $arrRelation[1], 'type' => $arrRelation[0], 'foreign_key' => $arrRelation[2]);
                }
            }
            
            return false;
        }

        // public function getDelegatedFieldInfo($delegatedField) {
        //     foreach ($this->getRelationFields() as $relation) {
        //         $arrRelation = explode(' ', $relation);
        //         if ($arrRelation[0] != 'delegate') continue;
                
        //         if ($arrRelation[1] == $delegatedField) {
        //             return array('name' => $arrRelation[1], 'model' => $arrRelation[2]);
        //         }
        //     }
            
        //     return false;
        // }
        
        public function getRelationFieldNamesArray() {
            $names = array();
            foreach ($this->getRelationFields() as $relation) {
                $arrRelation = explode(' ', $relation);
                $names[] = $arrRelation[1];
            }
            
            return $names;
        }

        // public function getDelegatedFieldNamesArray() {
            
        // }

        public function getFieldOperations() {
            return $this->_fieldOperations;
        }
        
        public function getFieldCaption() {
            return $this->_fieldCaption;
        }

        public function createRelation($relationField, &$record) {
            $relationInfo = $this->getRelationFieldInfo($relationField);
            
            if ($relationInfo['type'] == 'has_many') {
                return $this->createRelationHasMany($relationInfo, $record);
            } elseif ($relationInfo['type'] == 'belongs_to') {
                return $this->createRelationBelongsTo($relationInfo, $record->_getData($relationInfo['foreign_key']));
            } else {
                throw new Php_Exception("Relation type not supported. [{$relationInfo['type']}]");
            }
        }
        
        public function createRelationHasMany($relationInfo, &$recordBase) {
            $modelResult    = Conversor::getModelNameFromControllerName($relationInfo['name']);
            $records        = Db::getModel($modelResult)->setWhere($relationInfo['foreign_key']." = {$recordBase->getFieldIndexValue()}")->_find();
            
            $relation       = new Db_Relation_Hasmany($this->getModelName(), $modelResult, $relationInfo['foreign_key'], $recordBase->getFieldIndex(), $recordBase->getFieldIndexValue());
            foreach ($records as $record) {
                $relation->add($record);
            }
            
            return $relation;
        }
        
        public function createRelationBelongsTo($relationInfo, $fieldIndexValue) {
            $modelResult    = Conversor::getModelNameFromControllerName($relationInfo['name']);
            $record         = Db::getModel($modelResult)->load($fieldIndexValue);
            
            return $record;
        }

        public function validate(&$record) {

            foreach ($this->getFieldOperations() as $validation) {
                $errors         = array();
                $arrValidation  = explode(' ', $validation);
                
                switch ($arrValidation[0]) {
                    case 'validator':
                        $validatorClass     = $arrValidation[1];
                        $validatorObject    = new $validatorClass;
                        $fieldsToCheck      = array_slice($arrValidation, 2);

                        foreach ($fieldsToCheck as $field) {
                            $result = $validatorObject->validate($record->_getData($field));
                            if (!$result) {
                                $errors[] = $validatorObject->getErrorMessage();
                            }
                        }

                        if (!empty($errors)) $this->addToLastErrors($errors);
                        break;
                    
                    case 'presence_of':
                        $fieldsToCheck      = array_slice($arrValidation, 1);

                        foreach ($fieldsToCheck as $field) {
                            if ($record->_getData($field) == null || $record->_getData($field) == '') {
                                $errors[] = "$field - Not present.";
                            }
                        }

                        if (!empty($errors)) $this->addToLastErrors($errors);
                        break;

                    case 'uniqueness':
                        $fieldsToCheck  = array_slice($arrValidation, 1);
                        $method         = 'findAllBy';
                        $keys           = array_keys($fieldsToCheck);
                        $lastKey        = end($keys);
                        $values         = array();
                        foreach ($fieldsToCheck as $key => $field) {
                            $method  .= Conversor::uc_words($field, '', '_').(($key != $lastKey) ? 'And' : '');
                            $values[] = $record->_getData($field);
                        }

                        $results = call_user_func_array(array($this, $method), $values);                        

                        if ($record->{$record->getModel()->getFieldIndex()}) {
                            foreach ($results as $result) {
                                if ($record->{$record->getModel()->getFieldIndex()} != $result->{$result->getModel()->getFieldIndex()}) {
                                    $errors[] = "Uniqueness broken.";
                                    break;
                                }
                            }
                        }

                        if (!empty($errors)) $this->addToLastErrors($errors);
                        break;

                    case 'associated_on':
                        $parameters     = array_slice($arrValidation, 1);
                        $foreignModel   = $parameters[0];
                        $foreignKey     = $parameters[1];
                        $relatedModel   = Db::getModel($foreignModel);
                        $methodFind     = 'findBy'.Conversor::uc_words($relatedModel->getFieldIndex(), '', '_');

                        $relatedRecord  = $relatedModel->$methodFind($record->_getData($foreignKey));
                        if (!$relatedRecord) {
                            $errors[] = "Related record in $foreignModel not found!.";
                        }

                        if (!empty($errors)) $this->addToLastErrors($errors);
                        break;

                    default:
                        # code...
                        break;
                }
            }
            
            if ($this->getLastErrors() != array()) {
                return false;
            }

            return true;
        }

        public function processProcessors(&$record, $trigger) {
            
            // mail('paul.eduardo.marclay@gmail.com', 'processProcessors - '.time(), Debugger::trace(false, true, false));

            foreach ($this->getFieldOperations() as $processor) {
                $errors         = array();
                $arrProcessors  = explode(' ', $processor);
                
                if (in_array($arrProcessors[0], array('process_on_load', 'process_on_save', 'process_on_both'))) {
                    $processorClass = new $arrProcessors[1];
                    $field = $arrProcessors[2];
                    if (!$record->hasChanged($field) && $trigger == 'save') {
                        continue;
                    }
                } else {
                    continue;
                }

                if ($trigger == 'load' && ($arrProcessors[0] == 'process_on_load' || $arrProcessors[0] == 'process_on_both')) {
                    $record->_setData($field, $processorClass->onLoad($record->_getData($field)));
                } elseif ($trigger == 'save' && ($arrProcessors[0] == 'process_on_save' || $arrProcessors[0] == 'process_on_both')) {
                    $record->_setData($field, $processorClass->onSave($record->_getData($field)));
                }
            }

            return true;
        }
        
        public function getFieldInfo($fieldName) {
            return $this->getSql()->getFieldInfo($fieldName);
        }
        
    }
