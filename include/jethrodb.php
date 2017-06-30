<?php
/* Utility class for switching from MDB2 to PDO
 * Some of the routines in this class are derived/copied(and adapted) from the MDB2 source.
 * See https://pear.php.net/package/MDB2/ for the latest download of MDB2
 */
class JethroDB extends PDO {

  public function __construct ($dsn, $username, $password, $options=array()) {
    if ($options === NULL) { $options = array();}
    $options[PDO::ATTR_PERSISTENT] = true;
    $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
    $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
    try {
      $result =  parent::__construct($dsn, $username, $password, $options);
    } catch (PDOException $e) {
      trigger_error('Could not connect to database - please check for mistakes in your Database configuration in conf.php, and check in MySQL that the database exists and the specified user has been granted access.', E_USER_ERROR);
      exit();
    }
    return 	$result;
  }

  public function db_error($exception) {
        debug_print_backtrace();
    trigger_error('Database Error in query.<br>( ' . $exception->getMessage() . ')', E_USER_ERROR);
    exit();
  }
  
  public function quote($string, $paramtype=NULL) {
	if ($string === NULL) {
		return 'NULL';
	} else {
		return parent::quote($string, $paramtype);
	}
  }
    
  public function quoteIdentifier($field) {
    $parts = explode('.', $field);
    foreach (array_keys($parts) as $k) {
        $parts[$k] = "`" . str_replace("`", "``",$parts[$k]) . "`";
    }
    return implode('.', $parts);
  }

  public function prepare($statement, $driver_options=array()) {
    try {
      $result = parent::prepare($statement, $driver_options);
    }
    catch (PDOException $e) {
      self::db_error($e);
    }
    return $result;
  }
  
  public function query($sql) {
    try {
      $result = parent::query($sql);
    } catch (PDOException $e) {
      self::db_error($e);
    }
    return $result;
  }
  
  public function exec($sql) {
    try {
      $result = parent::exec($sql);
    } catch (PDOException $e) {
      self::db_error($e);
    }
    return $result;
  }
  public function queryRow($sql) {
    try {
      $stmnt = self::prepare($sql);
      $stmnt->execute();
      $result = $stmnt->fetch();
      $stmnt->closeCursor();
    } catch (PDOException $e) {
      self::db_error($e);
    }
    return $result;
  }
  /*
   * $colnum can be a column number or name
   */
  public function queryCol($sql, $colnum=0) {
    try {
      $stmnt = self::prepare($sql);
      $stmnt->execute();
      $result = $stmnt->fetchAll(PDO::FETCH_COLUMN, $colnum);
      $stmnt->closeCursor();
    } catch (PDOException $e) {
      self::db_error($e);
    }
    return $result;
  }

  public function queryAll($sql, $types=null, $fetchmode=null, $rekey=false, $force_array=false, $group=false,$emptyonerror=false) {
  $all = array();
  $stmnt = self::prepare($sql);
  try {
    $stmnt->execute();
  } catch (PDOException $e) {
    if ($emptyonerror) {
      return Array();
    } else {
      self::db_error($e);
    }
  }
  
  try {
    if (!$rekey) {
        $all = $stmnt->fetchAll();
      } else {
        $row = $stmnt->fetch();
        if ($row === false) { return $all; } // return an empty array if there is nothing here
        $shift_array = $rekey ? false: null;
        if (null !== $shift_array) {
          $colnum = count($row);
          if ($colnum < 2) {
            return new Exception('rekey feature requires at least 2 columns');
          }
          $shift_array = (!$force_array && $colnum == 2);
        }
        do {
          $key = reset($row);
          unset($row[key($row)]);
          if ($shift_array) {
            $row = array_shift($row);
          }
          if ($group) {
            $all[$key][] = $row;
          } else {
            $all[$key] = $row;
          }
        } while (($row = $stmnt->fetch()));
      }
    } catch (PDOException $e) {
      self::db_error($e);
    }
    return $all;
  }

  public function queryOne($query, $type=null, $colnum=0) {
    try {
      $result = false;
      $stmnt = self::prepare($query);
      $stmnt->execute();
      $row = $stmnt->fetch(PDO::FETCH_NUM);
      if ($row) {
        $result = $row[$colnum];
      }
      $stmnt->closeCursor();
    } catch (PDOException $e) {
      self::db_error($e);
    }
    return $result;
  }
  
  public function hasStaff() {
      $sql = 'SELECT count(*) FROM staff_member LIMIT 1';
      try {
        $stmnt = self::prepare($sql);
        $stmnt->execute();
        $result = (bool)$stmnt->fetch();
    } catch (PDOException $e) {
    	$result = false; // if there is an error, we have no users
    }
    return $result;
  }
  
  public function hasPersons() {
    $sql = 'SELECT count(*) FROM _persons LIMIT 1';
    $result = true;
    try {
      $stmnt = self::prepare($sql);
      $stmnt->execute();
      $row = $stmnt->fetch(PDO::FETCH_NUM);
      if ($row) {
        $result = (bool)$row[$colnum];
      }
    } catch (PDOException $e) {
      $result = false;
    }
    return $result;
  }
 
  public function isInstalled_Tables() {
    try {
      $stmnt = self::prepare('SHOW TABLES');
      $stmnt->execute();
      $result = $stmnt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
      self::db_error($e);
    }
    return !empty($result);
  }

  public function isInstalled_Functions() {
    try {
	  $result = true; 
      $stmnt = self::prepare('SHOW CREATE FUNCTION getCurrentUserID');
      $stmnt->execute();
      $res =$stmnt->fetchAll(PDO::FETCH_COLUMN, 0);
      $stmnt->closeCursor();
    } catch (PDOException $e) {
	  $result = false;
    } 
    return $result;

  }
  
 
  public function setCurrentUserID($userid) {
    try {
      $sql = 'SET @current_user_id = '. $userid;
      $result = parent::query($sql);
    } catch (PDOException $e) {
      trigger_error('Failed to set user id in database', E_USER_ERROR);
    }
  }
}
