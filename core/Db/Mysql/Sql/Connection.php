<?php
	/*
	*	DB_MYSQL_MODELS3 version 1.0
	*
	*	Imagina - Plugin.
	*
	*
	*	Copyright (c) 2012 Dolem Labs
	*
	*	Authors:	Paul Marclay (paul.eduardo.marclay@gmail.com)
	*
	*/

	class Db_Mysql_Sql_Connection {
		protected $_connectionName  = 'default';
		protected $_dbError 		= NULL;
		protected $_dbErrno 		= NULL;
	
		protected $_res 			= NULL;
		protected $_conn 			= NULL;
		protected $_numRows 		= 0;
		protected $_affectedRows 	= 0;
		protected $_insertId 		= 0;
        protected $_delay           = 0;
	
        
		public function __construct() {
            
        }
		
        public function connect() {
        	$config         = Api::getDatabase();
            $environment    = $this->getConnectionName();
            $port           = (($config[$environment]['port']) ? $config[$environment]['port'] : 3306);
        	
			$this->_conn = mysql_connect($config[$environment]['host'].':'.$port, $config[$environment]['username'], $config[$environment]['password']);
				
			if($this->_conn == false) {
				$this->_dbErrno = mysql_errno();
				$this->_dbError = mysql_error();
					
				return false;
			}
			
			// $session 					= Api::getSession();
			// $activeConns 				= $session->getActiveConns();
			// $activeConns[$environment] 	= $this->_conn;
			// $session->setActiveConns($activeConns);

			// Api::getLog()->put('Connected with MySql');
			Api::getLog()->incrementSqlConnections(1);
	
			$rc = mysql_select_db($config[$environment]['database'], $this->_conn);
	
			if($rc == false) {
				$this->_dbErrno = mysql_errno();
				$this->_dbError = mysql_error();
					
				return false;
			}
	
			return true;
		}
	
		public function close() {
			if (!$this->_conn) return true;
			
			$rc = @mysql_close($this->_conn);
			if($rc == false) {
				$this->_dbErrno = mysql_errno();
				$this->_dbError = mysql_error();
	
				return false;
			}
	
			return true;
		}
	
		public function query($sql) {
			
			if (!$this->_conn) {
				$session = Api::getSession();
				$activeConns = $session->getActiveConns();
				$myConn = $activeConns[$this->getConnectionName()];
				if ($myConn && is_resource($myConn) && get_resource_type($myConn) == 'mysql link') {
					$this->_conn = $activeConns[$this->getConnectionName()];
				} else {
					if(!$this->connect()) {
						$this->_dbErrno = mysql_errno();
						$this->_dbError = mysql_error();
							
						return false;
					}
				}
			}
			
            $queryTimeBegin     = time() + microtime();
            $this->_res         = mysql_query($sql, $this->_conn);
            $this->_delay       = (time() + microtime()) - $queryTimeBegin;
            Api::getLog()->incrementSqlQueryes(1);
            
            if($this->_res == false) {
				$this->_dbErrno = mysql_errno();
				$this->_dbError = mysql_error();
				
                throw new Php_Exception("Error: {$this->_dbErrno} - {$this->_dbError}");
                
				return false;
			}
            
			if (!is_bool($this->_res)) {
                $this->_numRows 		= @mysql_num_rows($this->_res);
            }
            $this->_affectedRows 	= @mysql_affected_rows();
			$this->_insertId 		= @mysql_insert_id();
			
			return $this->_res;
		}
	
		function getNumRows() {
	    	return $this->_numRows;
	    }
	    
		function getInsertId() {
	    	return $this->_insertId;
	    }
	    
		function getAffectedRows() {
	    	return $this->_affectedRows;
	    }
	
	    function getDbError() {
	    	return $this->_dbError;
	    }
	    
	    function getDbErrNo() {
	    	return $this->_dbErrno;
	    }
	    
	    function getConn() {
	    	return $this->_conn;
	    }
	    
	    function getRes() {
	    	return $this->_res;
	    }
        
        public function getDelay() {
            return $this->_delay;
        }
        
        public function getConnectionName() {
            return $this->_connectionName;
        }
        
        public function setConnectionName($connectionName) {
            $this->_connectionName = $connectionName;
        }
        
	}


