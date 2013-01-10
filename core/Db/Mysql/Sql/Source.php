<?php
    /*
    *    DB_MYSQL_MODELS3 version 1.0
    *
    *    Imagina - Plugin.
    *
    *
    *    Copyright (c) 2012 Dolem Labs
    *
    *    Authors:    Paul Marclay (paul.eduardo.marclay@gmail.com)
    *
    */

    class Db_Mysql_Sql_Source extends Db_Mysql_Sql_Connection {
        protected $_tableName       = '';
        protected $_fieldIndex      = '';
        
        protected $_fields          = array();         // Real table fields.
        protected $_fieldsInfo      = array();
        
        protected $_select          = '*';
        protected $_where           = '1';
        protected $_orderBy         = null;
        protected $_groupBy         = null;
        protected $_having          = null;
        protected $_limit           = null;
        
        public function __construct($tableName, $fieldIndex = null, $connectionName = 'default') {
            $this->setConnectionName($connectionName);
            $this->setTableName($tableName);
            $this->getFieldsFromModel();
            
            if ($fieldIndex == null) {
                $fieldIndex = $this->getIndexPrimary();
            }
            $this->setFieldIndex($fieldIndex);
            
            parent::__construct();
            
        }
        
        public function __destruct() {
            foreach ($this as $index => $value){
                 unset($this->$index);
            }
        }
        
        public function getIndexPrimary() {
            $query = $this->getSqlIndexes();
            $res = $this->query($query);
            if (!$res) {
                throw new Php_Exception("Model '{$this->_tableName}' does not have index as a primary!.");
            }
            
            $fields = mysql_fetch_assoc($res);
            
            return $fields['Column_name'];
        }
        
        public function getFieldsFromModel() {
            $query = 'describe `'.$this->_tableName.'`;';
            $res = $this->query($query);
            if (!$res) {
                throw new Php_Exception("Model '{$this->_tableName}' not exists!.");
            }
            
            while ($field = mysql_fetch_assoc($res)) {
                $this->_fields[] = $field['Field'];
                $this->_fieldsInfo[$field['Field']] = $field;
            }
        }
        
         public function getFieldsArray() {
            return $this->_fields;
        }
        
         public function getTableName() {
            return stripslashes($this->_tableName);
        }
        
         public function getFieldIndex() {
            return stripslashes($this->_fieldIndex);
        }
        
        public function getFieldsToSave($fieldsAndValues = array()) {
            $ret = '';
            if (empty($fieldsAndValues)) return $ret;
            
            $keys = array_keys($fieldsAndValues);    
            $end  = end($keys);

            foreach ($fieldsAndValues as $key => $value) {
                $ret .= $this->addFieldNameDelimiters(addslashes($key)).' = '.$this->addFieldValueDelimiters(addslashes($value)).(($end == $key) ? ' ' : ', ');
            }
            
            return $ret;
        }
        
        public function setTableName($value) {
            $this->_tableName = addslashes($value);
            return $this;
        }
        
        public function setFieldIndex($value) {
            if (!$this->fieldInModel($value)) {
                throw new Php_Exception("Field index '$value' not exist in Model!.");
            }
            
            $this->_fieldIndex = addslashes($value);
            return $this;
        }
        
        public function sqlExec($sql) {
            $exception = new Exception;
            $trace = $exception->getTrace();
            
            if (isset($trace[2])) {
                if (isset($trace[2]['line']))
                    $line       = $trace[2]['line'];
                if (isset($trace[2]['file']))
                    $file       = $trace[2]['file'];
                $function   = $trace[2]['function'];

                Api::getLog()->put("Executing: ".Api::getLog()->getSqlQueryes(), 4, 'black');
                Api::getLog()->put("$sql", 8, 'black');
                Api::getLog()->put("From: ", 8, 'black');
                if (isset($trace[2]['file']))
                    Api::getLog()->put("File: $file", 12, 'black');
                Api::getLog()->put("Method: $function", 12, 'black');
                if (isset($trace[2]['line']))
                    Api::getLog()->put("Line: $line", 12, 'black');
            }
            
            $result = $this->query($sql);
            
            Api::getLog()->put("Delay: {$this->getDelay()} Seg.", 8, 'black');
            
            return $result;
        }
        
        public function getSqlSave($id = NULL, $fieldsAndValues = array()) {
            $queryDate = '';
            if ($id == null) {
                $queryDate = '';// agregar el campo created_at y ponerle el varlor de la fecha y hora actual, lo mismo con updated_at.
            }
            
            $query  = (($id == NULL) ? 'insert into ' : 'update ');
            $query .= $this->addFieldNameDelimiters($this->getTableName()).' set ';
            $query .= $this->getFieldsToSave($fieldsAndValues);
            $query .= (($id == NULL) ? '' : 'where '.$this->addFieldNameDelimiters($this->getFieldIndex()).' = '.$this->addFieldValueDelimiters(addslashes($id)).' limit 1');
            $query .= ';';
            
            return $query;
        }
        
        public function getSqlLoad($id = NULL) {
            
            $query  = 'select ';
            $query .= $this->getSelect();
            $query .= ' from '.$this->addFieldNameDelimiters($this->_tableName);
            $query .= ' where ';
            $query .= $this->addFieldNameDelimiters($this->_fieldIndex).' = '.$this->addFieldValueDelimiters(addslashes($id));
            $query .= ' limit 1 ';
            $query .= ';';
            
            return $query;
        }
        
        public function getSqlDelete($id = NULL) {
            $query  = 'delete ';
            $query .= ' from '.$this->addFieldNameDelimiters($this->_tableName);
            $query .= ' where ';
            $query .= $this->addFieldNameDelimiters($this->_fieldIndex).' = '.$this->addFieldValueDelimiters(addslashes($id));
            $query .= ' limit 1 ';
            $query .= ';';
            
            return $query;
        }
        
        public function getSqlClean() {
            $query  = 'delete ';
            $query .= ' from '.$this->addFieldNameDelimiters($this->_tableName);
            $query .= ' where ';
            $query .= ' 1 ';
            $query .= ';';
            
            return $query;
        }
        
        public function getSqlFind($limit = 0, $all = false) { // @todo: terminar $all = false;
            $query  = 'select ';
            $query .= $this->getSelect();
            $query .= ' from '.$this->addFieldNameDelimiters($this->_tableName);
            $query .= ' where ';
            $query .= $this->getWhere();
            $query .= (($this->getGroupBy()) ? ' group by '.$this->getGroupBy().' ' : '' );
            $query .= (($this->getHaving()) ? ' having '.$this->getHaving().' ' : '' );
            $query .= (($this->getOrderBy()) ? ' order by '.$this->getOrderBy().' ' : '' );
            $query .= (($limit <= 0) ? (($this->getLimit()) ? ' limit '.$this->getLimit() : '') : ' limit '.addslashes($limit));
            $query .= ';';
            
            return $query;
        }
        
        public function getSqlFindAll() {
            $query  = 'select ';
            $query .= $this->getSelect();
            $query .= ' from '.$this->addFieldNameDelimiters($this->_tableName);
            $query .= ' where ';
            $query .= (($this->getWhere()) ? $this->getWhere() : ' 1 ');
            $query .= (($this->getGroupBy()) ? ' group by '.$this->getGroupBy().' ' : '' );
            $query .= (($this->getHaving()) ? ' having '.$this->getHaving().' ' : '' );
            $query .= (($this->getOrderBy()) ? ' order by '.$this->getOrderBy().' ' : '' );
            $query .= (($this->getLimit()) ? ' limit '.$this->getLimit() : '');
            $query .= ';';
            
            return $query;
        }
        
        public function getSqlTransactionStart() {
            return 'start transaction;';
        }
        
        public function getSqlTransactionCommit() {
            return 'commit;';
        }
        
        public function getSqlTransactionRollback() {
            return 'rollback;';
        }
        
        public function getSqlCount($applyLimit = false) {
            $query  = 'select ';
            $query .= " count({$this->getFieldIndex()}) as count ";
            $query .= ' from '.$this->addFieldNameDelimiters($this->_tableName);
            $query .= ' where ';
            $query .= (($this->getWhere()) ? $this->getWhere() : ' 1 ');
            $query .= (($this->getGroupBy()) ? ' group by '.$this->getGroupBy().' ' : '' );
            $query .= (($this->getHaving()) ? ' having '.$this->getHaving().' ' : '' );
            if ($applyLimit) {
                $query .= (($this->getLimit()) ? ' limit '.$this->getLimit() : '');
            }
            $query .= ';';
            
            return $query;
        }

        public function getSqlIndexes() {
            $query = 'show indexes from '.$this->addFieldNameDelimiters($this->_tableName).' where key_name="PRIMARY";';
            return $query;
        }
        
        public function fieldInModel($fieldName) {
             return in_array($fieldName, $this->getFieldsArray());
        }
        
        public function addFieldNameDelimiters($fieldName) {
            return (($fieldName != '*') ? '`'.$fieldName.'`' : $fieldName);
        }
        
        public function addFieldValueDelimiters($fieldValue) {
            return '\''.$fieldValue.'\'';
        }
        
        public function setSelect($select = '*') {
            if (!in_array($this->_fieldIndex, explode(',', $select))) {
                $select = $this->_fieldIndex.','.$select;
            }

            $this->_select = addslashes($select);
        }
        
        public function setWhere($where = '1', $params = null) {
            if (is_array($params)) {
                foreach ($params as $key => $value) {
                    $where = str_replace(":$key", addslashes($value), $where);
                }
            }

            $this->_where = ($where);
        }
        
        public function setOrderBy($orderBy = '') {
            $this->_orderBy = addslashes($orderBy);
        }
        
        public function setGroupBy($groupBy) {
            $this->_groupBy = addslashes($groupBy);
        }
        
        public function setHaving($having = '') {
            $this->_having = addslashes($having);
        }
        
        public function setLimit($limit = '') {
            $this->_limit = addslashes($limit);
            
            return $this;
        }
        
        // --
        
        public function getSelect() {
            return $this->_select;
        }
        
        public function getWhere() {
            return $this->_where;
        }
        
        public function getOrderBy() {
            return $this->_orderBy;
        }
        
        public function getGroupBy() {
            return $this->_groupBy;
        }
        
        public function getHaving() {
            return $this->_having;
        }
        
        public function getLimit() {
            return $this->_limit;
        }
        
        // --
        
        public function getFieldInfo($fieldName) {
            return $this->_fieldsInfo[$fieldName];
        }
    }