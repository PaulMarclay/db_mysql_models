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

    class Db_Mysql_Sql_Interface extends Db_Mysql_Sql_Source {
        
        public function __construct($tableName, $fieldIndex = null, $connectionName = '') {
            parent::__construct($tableName, $fieldIndex, $connectionName);
        }
        
        public function save($id = NULL, $fieldsAndValues = array()) {
            
            if ($id == null) {
                if (in_array('created_at', $this->getFieldsArray())) {
                    if (!isset($fieldsAndValues['created_at'])) {
                        $fieldsAndValues['created_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            if (in_array('updated_at', $this->getFieldsArray())) {
                $fieldsAndValues['updated_at'] = date('Y-m-d H:i:s');
            }

            return ($this->sqlExec($this->getSqlSave($id, $fieldsAndValues)) ? (($id) ? $id : $this->getInsertId()) : false);
        }
        
        public function load($id = NULL) {
            return $this->sqlExec($this->getSqlLoad($id));
        }
        
        public function delete($id = NULL) {
            return $this->sqlExec($this->getSqlDelete($id));
        }
        
        public function clean() {
            return $this->sqlExec($this->getSqlClean());
        }
        
        public function find($limit = 0) {
            return $this->sqlExec($this->getSqlFind($limit));
        }
        
        public function findAll() {
            return $this->sqlExec($this->getSqlFindAll());
        }
        
        public function transactionStart() {
            return $this->sqlExec($this->getSqlTransactionStart());
        }
        
        public function transactionCommit() {
            return $this->sqlExec($this->getSqlTransactionCommit());
        }
        
        public function transactionRollback() {
            return $this->sqlExec($this->getSqlTransactionRollback());
        }
        
        public function count($applyLimit = false) {
            return $this->sqlExec($this->getSqlCount($applyLimit));
        }
        
        
    }