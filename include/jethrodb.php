<?php

/* Utility class for switching from MDB2 to PDO
 * Some of the routines in this class are derived/copied(and adapted) from the MDB2 source.
 * See https://pear.php.net/package/MDB2/ for the latest download of MDB2
 * The following disclaimer applies to code from MDB2:
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL
 * THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

class JethroDB extends PDO
{
	/**
	 * Create a JethroDB object from the details in conf.php and make it a global variable.
	 * @param type $mode
	 */
	public static function init($mode = '')
	{
		$mode = strtoupper($mode);
		if ($oldDsn = ifdef($mode . '_DSN')) {
			// legacy config
			preg_match('|([a-z]+)://([^:]*)(:(.*))?@([A-Za-z0-9\.-]*)(/([0-9a-zA-Z_/\.]*))|', $oldDsn, $matches);
			if (!defined('DB_TYPE'))
				define('DB_TYPE', $matches[1]);
			if (!defined('DB_HOST'))
				define('DB_HOST', $matches[5]);
			if (!defined('DB_DATABASE'))
				define('DB_DATABASE', $matches[7]);
			$username = ifdef('DB_USERNAME', $matches[2]);
			$password = ifdef('DB_PASSWORD', $matches[4]);
		} else {
			$username = DB_USERNAME;
			$password = DB_PASSWORD;
			if ($mode) {
				if (strlen($x = ifdef('DB_'.$mode.'_USERNAME'))) {
					$username = $x;
				}
				if (strlen($y = ifdef('DB_'.$mode.'_PASSWORD'))) {
					$password = $y;
				}
			}
		}
		$port = ifdef('DB_PORT', '');
		$type = ifdef('DB_TYPE', 'mysql');
		$host = ifdef('DB_HOST', 'localhost');
		$dsn = $type . ':host=' . $host . (strlen($port) ? (';port=' . $port) : '') . ';dbname=' . DB_DATABASE . ';charset=utf8';
		$GLOBALS['db'] = new JethroDB($dsn, $username, $password);
	}

	public static function get()
	{
		if (empty($GLOBALS['db'])) self::init();
		return $GLOBALS['db'];
	}


	/**
	 * Construct a JethroDB object
	 * @param string $dsn		PDO dsn
	 * @param string $username	Username
	 * @param string $password	Password
	 * @param array $options	PDO options
	 */
	public function __construct($dsn, $username, $password, $options = array())
	{
		if ($options === NULL) {
			$options = array();
		}
		$options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
		$options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
		try {
			$result = parent::__construct($dsn, $username, $password, $options);
		} catch (PDOException $e) {
			error_log((string)$e);
			trigger_error('Could not connect to database - please check for mistakes in your Database configuration in conf.php, and check in MySQL that the database exists and the specified user has been granted access.', E_USER_ERROR);
			exit();
		}
		return $result;
	}

	/**
	 * Quote and escape a value ready for use in SQL
	 * @param string$string
	 * @param mixed $paramtype
	 * @return string|false
	 */
	public function quote($string, $paramtype = NULL)
	{
		if ($string === NULL) {
			return 'NULL';
		} else {
			if ($paramtype === NULL) {
				return parent::quote($string);
			} else {
				return parent::quote($string, $paramtype);
			}
		}
	}

	/**
	 * Quote an identifier (eg table or column name) ready for use in SQL
	 * @param string $field
	 * @return string
	 */
	public function quoteIdentifier($field)
	{
		$parts = explode('.', $field);
		foreach (array_keys($parts) as $k) {
			$parts[$k] = "`" . str_replace("`", "``", $parts[$k]) . "`";
		}
		return implode('.', $parts);
	}

	/**
	 * Execute the specified query and fetch the values from the first row
	 * of the result set into an array
	 *
	 * @param string $sql	Query to execute
	 * @return array
	 */
	public function queryRow($sql)
	{
		$stmnt = self::prepare($sql);
		$stmnt->execute();
		$result = $stmnt->fetch();
		$stmnt->closeCursor();
		return $result;
	}

	/**
	 * Execute the specified query and fetch the value from a single column of
	 * each row of the result set into an array
	 *
	 * @param $sql	Query to execute
	 * @param $colnum	Column index OR column name to include in the result array
	 * @return array
	 */
	public function queryCol($sql, $colnum = 0)
	{
		$stmnt = self::prepare($sql);
		$stmnt->execute();
		$result = $stmnt->fetchAll(PDO::FETCH_COLUMN, $colnum);
		return $result;
	}

	/**
	 * Execute the specified query and
	 * fetch all the rows of the result set into a two dimensional array
	 *
	 * @param string $sql		Query to execute
	 * @param array $types		Legacy parameter, not used.
	 * @param int $fetchmode	Legacy parameter, not used.
	 * @param boolean $rekey	Whether to use the first col of the query result as the keys of the result array
	 * @param boolean $force_array	 	Used only when the query returns exactly two columns.
	 *									If true, the values of the returned array will be one-element arrays instead of scalars.
	 * @param boolean $group			If true, the values of the returned array is wrapped in another array.
	 *									If the same key value (in the first column) repeats itself, the values will be appended to this array instead of overwriting the existing values.
	 * @return array
	 */
	public function queryAll($sql, $types = null, $fetchmode = null, $rekey = false, $force_array = false, $group = false)
	{
		$all = array();
		$stmnt = self::prepare($sql);
		$stmnt->execute();

		if (!$rekey) {
			$all = $stmnt->fetchAll();
		} else {
			$row = $stmnt->fetch();
			if ($row === false) {
				return $all;
			} // return an empty array if there is nothing here
			$shift_array = $rekey ? false : null;
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
		return $all;
	}

	/**
	 * Execute the specified query,
	 * fetch the value from the first column of the first row of the result set
	 * and then frees the result set.
	 *
	 * @param string $query	SQL query to run
	 * @param string  $type
	 * @param mixed $colnum
	 * @return mixed field value
	 */
	public function queryOne($query, $type = null, $colnum = 0)
	{
		$result = false;
		$stmnt = self::prepare($query);
		$stmnt->execute();
		$row = $stmnt->fetch(PDO::FETCH_NUM);
		if ($row) {
			$result = $row[$colnum];
		}
		$stmnt->closeCursor();
		return $result;
	}

	/**
	 * Return true if the database has any tables in it
	 * @return boolean
	 */
	public function hasTables()
	{
		$stmnt = self::prepare('SHOW TABLES');
		$stmnt->execute();
		$result = $stmnt->fetchAll(PDO::FETCH_COLUMN, 0);
		return !empty($result);
	}

	/**
	 * Return true if the database has the getCurrentUserID function in it
	 * @return boolean
	 */
	public function hasFunctions()
	{
		try {
			$result = true;
			$stmnt = self::prepare('SHOW CREATE FUNCTION getCurrentUserID');
			$stmnt->execute();
			$res = $stmnt->fetchAll(PDO::FETCH_COLUMN, 0);
			$stmnt->closeCursor();
		} catch (PDOException $e) {
			$result = false;
		}
		return $result;
	}

	/**
	 * Set the current User ID variable in the DB
	 * @param int	$userid
	 */
	public function setCurrentUserID($userid)
	{
		try {
			$sql = 'SET @current_user_id = ' . $userid;
			$result = parent::query($sql);
		} catch (PDOException $e) {
			trigger_error('Failed to set user id in database', E_USER_ERROR);
		}
	}

}
