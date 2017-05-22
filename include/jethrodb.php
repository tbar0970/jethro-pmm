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
    $options[PDO::NULL_EMPTY_STRING] = true;
    try {
      $result =  parent::__construct($dsn, $username, $password, $options);
    } catch (PDOException $e) {
      trigger_error('Could not connect to database - please check for mistakes in your Database configuration in conf.php, and check in MySQL that the database exists and the specified user has been granted access.', E_USER_ERROR);
      exit();
    }
    return 	$result;
  }

  public function quoteIdentifier($field) {
    $parts = explode('.', $field);
    foreach (array_keys($parts) as $k) {
        $parts[$k] = "`" . str_replace("`", "``",$parts[$k]) . "`";
    }
    return implode('.', $parts);
  }

  public function queryRow($sql) {
    $stmnt = self::prepare($sql);
    $stmnt->execute();
    self::check_db_statement_and_exit($stmnt);
    $result = $stmnt->fetch();
    $stmnt->closeCursor();
    return $result;
  }
  /*
   * $colnum can be a column number or name
   */
  public function queryCol($sql, $colnum=0) {
    $stmnt = self::prepare($sql);
    $stmnt->execute();
    self::check_db_statement_and_exit($stmnt);
    $result = $stmnt->fetchAll(PDO::FETCH_COLUMN, $colnum);
    $stmnt->closeCursor();
    return $result;
  }

  public function queryAll($sql, $types=null, $fetchmode=null, $rekey=false, $force_array=false, $group=false,$emptyonerror=false) {
    $all = array();
    $stmnt = self::prepare($sql);
    $stmnt->execute();
	if (($stmnt->errorCode() !== NULL) && ($stmnt->errorCode() > 0)) {
		if ($emptyonerror) {
			return Array();
		} else {
            trigger_error("Database Error: " . $statement->errorCode(), E_USER_ERROR);
		}
	}
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

    $stmnt->closeCursor();
    return $all;
  }

  public function queryOne($query, $type=null, $colnum=0) {
    $result = false;
    $stmnt = self::prepare($query);
    $stmnt->execute();
    self::check_db_statement($stmnt);
    $row = $stmnt->fetch(PDO::FETCH_NUM);
    if ($row) {
      $result = $row[$colnum];
    }
    $stmnt->closeCursor();
    return $result;
  }


  public function check_db_statement($statement) {
    if (($statement->errorCode() !== NULL) && ($statement->errorCode() > 0)) {
			print "Database Error: " . $statement->errorCode();
    }
  }

  public function check_db_statement_and_exit($statement) {
    if (($statement->errorCode() !== NULL) && ($statement->errorCode() > 0)) {
		var_dump(debug_backtrace());
	    trigger_error("Database Error: " . $statement->errorCode(), E_USER_ERROR);
        exit();
    }
  }
  /*
   * Returns true if there is an error, false if there is no error
   */
  public function check_db_error() {
    return ((self::errorCode() !== '00000') || (self::errorCode() !== NULL));
  }
}
