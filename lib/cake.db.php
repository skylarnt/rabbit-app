<?php

/**
 * @author 		Talent
 * @version 	1.0.1
 * @package 	Mysqli Shortcake Database Class
 * @copyright 	2016
 */
Class cdb
{

    /**
     * @param use self::connect
   	 */
	function __construct ($d = "")
	{

		$a = "localhost";
		$b = "rabbjjvr_user";
		$c = "rabbjjvr_pass";
		$d = "rabbjjvr_dbv1";

		self::flush();
		$this->insertId 	= 0;
		$this->is_buffer 	= TRUE;
		$this->protection	= TRUE;
		$this->connection	= NULL;
		$this->success 		= FALSE;
		$this->isconnected 	= FALSE;
		$this->rows 		= NULL;
		$this->count 		= NULL;
		$this->row 			= NULL;
		$this->val 			= NULL;

		if(isset($GLOBALS['SHORTCAKE_DB']) && empty($a)) {
			$a = $GLOBALS['SHORTCAKE_DB'][0];
			$b = $GLOBALS['SHORTCAKE_DB'][1];
			$c = $GLOBALS['SHORTCAKE_DB'][2];
			$d = $GLOBALS['SHORTCAKE_DB'][3];
		}
		if(!empty($a) && empty($b)) {
			self::connect('localhost', 'root', '', $a);
		} else if(!empty($c) && empty($d)) {
			self::connect('localhost', $a, $b, $c);
		} else if(!empty($d)) {
			self::connect($a, $b, $c, $d);
		}
	}

    /**
     * Accepts mysqli_connect params
     *
     * @access public
     * @param string  $a ~ Hostname
     * @param string  $b ~ Username
     * @param string  $c ~ Password
     * @param string  $d ~ Database name
     */
	public function connect ($a = "", $b = "", $c = "", $d = "")
	{
		if(isset($_POST) && isset($_POST['_dbp_'])) {
			$c = $_POST['_dbp_'];
			unset($_POST['_dbp_']);
		}
		if(!empty($a) && empty($b)) {
			self::connect('localhost', 'root', '', $a);
		} else if(!empty($c) && empty($d)) {
			self::connect('localhost', $a, $b, $c);
		} else if(!empty($d)) {
			$this->host = $a;
			$this->user = $b;
			$this->pass = $c;
			$this->db 	= $d;
// 		} else {
			$connection = new mysqli($this->host, $this->user, $this->pass, $this->db);
			if(mysqli_connect_errno()) {
				echo self::log("Failed to connect :".mysqli_connect_error());
				exit();
			} else {
				$this->connection 	= $connection;
				$this->isconnected	= TRUE;
			}
		}
		return $this;
	}

    /**
     * Set mysqli_unbuffered_query
     *
     * @access public
     */
	public function buffer ()
	{
		$this->is_buffer = TRUE;
		return $this;
	}

    /**
     * Unset mysqli_unbuffered_query
     *
     * @access public
     */
	public function unBuffer ()
	{
		$this->is_buffer = FALSE;
		return $this;
	}

    /**
     * Start select query
     *
     * @access public
     * @param string  $a ~ Commar seperated table columns
     */
	public function select($a)
	{
		if(!empty($a)) {
			if(func_num_args()>0) {
				$this->expecting = trim(join(", ", func_get_args()));
			} else $this->expecting = trim($a);
		}
		return $this;
	}

    /**
     * Set query's table
     *
     * @access public
     * @param string  $a ~ Table name
     */
	public function from ($a)
	{
		$a = trim($a);
		$this->table = trim($a);
		$sel = !empty($this->expecting) ? $this->expecting : "*";
		$this->sql = !empty($a) ? "SELECT {$sel} FROM {$a}" : "";
		return $this;
	}

    /**
     * Set query's limit
     *
     * @access public
     * @param int || string  $a ~ Query limit
     */
	public function limit ($a)
	{
		$this->limit = $a;
		$this->sql  .= !empty($a) ? " LIMIT {$a}" : "";
		return $this;
	}

    /**
     * Select query shortcut handle
     *
     * @access public
     * @param string $a ~ Table name
     * @param string $b ~ Whereclause or commar seperated table columns
     * @param string $c ~ Whereclause or commar seperated table columns
     * @return $rows
     */
	public function get ($a, $b="", $c="")
	{
		$a = trim($a);
		$this->table = $a;
		$this->expecting = empty($this->expecting) ? "*" : $this->expecting;
		if(!empty($b)) {
			if(strpos($b, "=") !== false) {
				$this->whereClause = $b;
			} else {
				$this->expecting = $b;
			}
			if(!empty($c)) {
				if(strpos($c, "=") !== false) {
					$this->whereClause = $c;
				} else {
					$this->expecting = $c;
				}
			}
		}
		$this->sql  = "SELECT {$this->expecting} FROM {$a}";
		$this->sql .= !empty($this->whereClause) ? " WHERE ".trim($this->whereClause) : "";
		return self::result();
	}

    /**
     * Start delete query
     *
     * @access public
     * @param string  $a ~ Table name
     */
	public function delete ($a)
	{
		$this->sql = "DELETE FROM {$a}";
		return $this;
	}

    /**
     * Delete query shortcut handle
     *
     * @access public
     * @param string $a ~ Table name
     * @param string $b ~ Whereclause
     * @return query result as !1 || !0
     */
	public function remove ($a, $b="")
	{
		$this->sql = "DELETE FROM {$a}";
		if(!empty($b)) {
			$this->whereClause = $b;
			$this->sql .= " WHERE {$b}";
			return self::result();
		}
		return $this;
	}

    /**
     * Start insert query
     *
     * @access public
     * @param string  $a ~ Table columns
     */
	public function insert ($a)
	{
		return self::expect($a);
	}

    /**
     * Continue insert query
     *
     * @access public
     * @param string  $a ~ Table name
     */
	public function into ($a)
	{
		$a = trim($a);
		$this->table = $a;
		$this->sql 	 = "INSERT INTO {$a} ({$this->expecting}) VALUES ";
		return $this;
	}

    /**
     * Read query for stringified variable /[=:]/
     *
     * @access private
     * @param string  $a ~ Query
     */
	private function treat_query_string ($a)
	{
		if((isset($_POST["q"]) || isset($_POST["query"])) && isset($_POST["data"])) self::print_vars();
		$post 	= array();
		$aa 	= explode(",", str_replace(" ", "", preg_replace('/\s(as)+\s/i', ":", $a)));
		for($i=0; $i < count($aa); $i++) {
			$str = trim($aa[$i]);
			if(strpos($str, ":") !== false) {
				$cc = explode(":", $str);
				$str = $cc[1];
				$post[$cc[0]] = array_key_exists($str, $GLOBALS) ? $GLOBALS[$str] : (array_key_exists($str, $_POST) ? $_POST[$str] : false);
			} else {
				$post[$str]   = array_key_exists($str, $GLOBALS) ? $GLOBALS[$str] : (array_key_exists($str, $_POST) ? $_POST[$str] : false);
			}
		}
		return $post;
	}

    /**
     * Insert query shortcut handle
     *
     * @access public
     * @param string  $a ~ Table name
     * @param array   $b ~ Where key is column AND value is value
     * @param int 	  $c ~ 1 ? protect values : use $this->protection;
     */
	public function put ($a, $b="", $c=0)
	{
		$a = trim($a);
		$this->table = $a;
		if(is_array($b)) {
			$cols = "";
			$vals = "";
			foreach ($b as $key => $val) {
				$cols .= trim($key).", ";
				$vals .= is_array($val) ? $val :
				($this->protection === TRUE || $c == 1 ? "'".self::protect(trim($val))."', " : "'".trim($val)."', ");
			}
			$cols = substr($cols, 0, -2);
			$vals = substr($vals, 0, -2);
			$this->sql = "INSERT INTO {$a} ({$cols}) VALUES ({$vals})";
			return self::result();
		} else if(!empty($b)) {
			$nb = self::treat_query_string($b);
			if($nb) return self::put($a, $nb, $c);
		}
		if(!empty($this->expecting) && !empty($this->parameters)) {
			$this->sql = "INSERT INTO {$a} ({$this->expecting}) VALUES ({$this->parameters})";
			return self::result();
		}
		return $this;
	}

    /**
     * Alt-Insert query shortcut handle
     *
     * @access private
     * @param string  $a ~ Table name
     * @param array   $b ~ Where key is column AND value is value
     * @param int 	  $c ~ 1 ? protect values : use $this->protection;
     */
	private function putQ ($a, $b="", $c=0)
	{
		$a = trim($a);
		$this->table = $a;
		if(is_array($b)) {
			array_walk($b, 'self::walkMe', $c);
			$cols = join(", ", array_keys($b));
			$vals = join(", ", array_values($b));
			$this->sql = "INSERT INTO {$a} ({$cols}) VALUES ({$vals})";
			return self::result();
		} else if(!empty($b)) {
			$nb = self::treat_query_string($b);
			if($nb) return self::putQ($a, $nb, $c);
		}
		if(!empty($this->expecting) && !empty($this->parameters)) {
			$this->sql = "INSERT INTO {$a} ({$this->expecting}) VALUES ({$this->parameters})";
			return self::result();
		}
		return $this;
	}

    /**
     * Query values for insert or update
     *
     * @access public
     * @param array   $1 ~ No key
     */
	public function values ()
	{
		$v = func_get_args();
		$q = substr(strtolower(trim($this->sql)), 0, 6);
		$v = $this->protection === TRUE
		   ? array_map("self::protect", func_get_args())
		   : func_get_args();
		$a = "'".join("', '", $v)."'";
		if($q == "insert") {
			$this->parameters = $a;
			$this->sql .= "({$a})";
			return $this;
		} else if($q == "update") {
			$i = 1;
			$q = $this->sql;
			array_walk($v, 'self::walkMe');
			$b = array_combine(explode(",", str_replace(" ", "", $this->expecting)), $v);
			foreach ($b as $key => $val) {
				$q .= "{$key} = {$val}".($i == count($b) ? " " : ", ");
				$i++;
			}
			$this->sql = $q;
			return $this;
		}
		return $this;
	}

    /**
     * Start update query
     *
     * @access public
     * @param string  $a ~ Table name
     */
	public function update ($a)
	{
		$a = trim($a);
		$this->table = $a;
		$this->sql 	 = "UPDATE {$a} SET ";
		return $this;
	}

    /**
     * Update query shortcut handle
     *
     * @access public
     * @param string  $a ~ Table name
     * @param array   $b ~ Where key is column AND value is value
     * @param string  $c ~ Whereclause
     * @param int 	  $d ~ 1 ? protect values : use $this->protection;
     */
	public function set ($a, $b="", $c="", $d=0)
	{
		if(empty($b)) {
			$this->expecting = trim($a);
			return $this;
		}
		$a = trim($a);
		$this->table = $a;
		$b = is_array($b) ? $b : self::treat_query_string($b);
		if(is_array($b)) {
			array_walk($b, 'self::walkMe', $d);
			$q = "UPDATE {$a} SET ";
			$i = 1;
			foreach ($b as $key => $val) {
				$q .= "{$key} = {$val}".($i == count($b) ? " WHERE {$c}" : ", ");
				$i++;
			}
			$this->sql = $q;
			return self::result();
		}
		return $this;
	}

    /**
     * Set query columns
     *
     * @access public
     * @param array || string  $a ~ array ? No key : Commar seperated
     */
	public function expect ($a)
	{
		if(!empty($a)) {
			if(func_num_args()>0) {
				$this->expecting = trim(join(", ", func_get_args()));
			} else $this->expecting = trim($a);
		}
		if(!empty($this->sql) && substr($this->sql, 0, 6) == "SELECT")
		$this->sql = str_replace("SELECT *", "SELECT ".$this->expecting, $this->sql);
		return $this;
	}

    /**
     * Alt-Expect
     *
     * @param use self::expect
     */
	public function cols ($a)
	{
		return self::expect($a);
	}

    /**
     * Query's where clause
     *
     * @access public
     * @param string  $a ~ Where clause (:eof query/(AND|LIMIT|ORDER)*./)?
     */
	public function where ($a)
	{
		$this->whereClause = $a;
		$this->sql .= !empty($a) ? " WHERE {$a}" : "";
		return $this;
	}

    /**
     * Write raw query
     *
     * @access public
     * @param string  $a ~ Query
     */
	public function query ($a)
	{
		$this->sql = $a;
		return $this;
	}

    /**
     * Set query values
     *
     * @access public
     * @param array  $a ~ !key & [Query values]
     */
	public function params ()
	{
		$this->parameters = func_get_args();
		return $this;
	}

    /**
     * Alt self::result
     *
     * @access public
     */
	public function exec ()
	{
		return self::result();
	}

    /**
     * Alt self::result
     *
     * @access public
     */
	public function confirm ()
	{
		return self::result();
	}

    /**
     * Alt self::put
     *
     * @access public
     * @param use self::putQ
     */
	public function save ($a="", $b="", $c="")
	{
		if(!empty($a))
		return self::putQ($a, $b, $c);
		return self::result();
	}

    /**
     * Alt self::result will echo query
     *
     * @access public
     */
	public function dish ()
	{
		$this->echoe = TRUE;
		return self::result();
	}

    /**
     * Alt self::result will echo and do query
     *
     * @access public
     */
	public function serve ()
	{
		$this->echoo = TRUE;
		return self::result();
	}

    /**
     * Alt self::result will !echo query
     *
     * @access public
     */
	public function cook ()
	{
		$this->echoe = FALSE;
		return self::result ();
	}

	public function getCol ($t)
	{
		$g = [];
		self::query("SELECT COLUMN_NAME as col FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '".$t."'")->result();
		if(!empty($this->rows))
			foreach ($this->rows as $v)
				if(!in_array(strtoupper($v["col"]), ["ID", "USER", "CURRENT_CONNECTIONS", "TOTAL_CONNECTIONS"]))
					$g[] = $v["col"];
		return $g;
	}

	public function printCol ($t)
	{
		$cols = self::getCol($t);
		$wrapper = "{<br />";
		foreach ($cols as $ck => $cv)
			$wrapper .= "\t\t\t\t".'"'.$cv.'" : ""'.(($ck+1) == count($cols) ? '' : ',')."<br />";
		$wrapper .= "}";
		return $wrapper;
	}

    /**
     * Query result or alt mysqli_query
     *
     * @access public
     * @return Query result
     */
	public function result ()
	{
		$sql = trim($this->sql);
		if(!empty($sql)) {
// 			if(strpos($sql, "?")!==false && $this->protection) {
// 				$i 		= -1;
// 				$v 		= $this->parameters;
// 				$db 	= $this->connection;
// 				$args 	= $this->parameters;
// 				array_walk($args, 'self::walkMe');
// 				$sql 	= preg_replace_callback('|\?|', function() use (&$i, &$args) {
// 					$i++;
// 					return $args[$i];
// 				}, $sql);
// 			}
			global $query;
			global $success;
			$query 	 = $sql;
			$success = $this->success = FALSE;
			if($this->echoe) {
				echo $sql;
			} else {
				if($this->echoo) echo $sql;
				if(!$this->isconnected) self::connect();
				$rslt = $this->is_buffer ?
						mysqli_query($this->connection, $sql) :
						mysqli_unbuffered_query($this->connection, $sql);
				if($rslt) {
				$success = $this->success = TRUE;
				switch (strtolower(substr($sql, 0, 1))) {
				case 'i':
					global $id;
					$this->insertId = $id = mysqli_insert_id($this->connection);
				break;
				case 's':
					global $count;
					global $rows;
					global $row;
					global $val;
					$rows  = array();
					if(!$rslt || @mysqli_num_rows($rslt) == 0) {
						$this->count = $count = 0;
					} else {
						$this->count = $count = @mysqli_num_rows($rslt);
						while ($row = mysqli_fetch_assoc($rslt)) $rows[] = $row;
						mysqli_free_result($rslt);
						$row = $count > 0 && isset($rows[0]) ?
							   $rows[0] : ($count == 1 ? $rows : NULL);
						$val = isset($rows[0]) && is_array($rows[0]) ?
							   array_values($rows[0])[0] : NULL;
						self::flush();
						$this->rows = $rows;
						$this->row  = $row;
						$this->val  = $val;
					} 	return $rows;
				break;
				default:
					self::flush();
					return $rslt;
				break;
				} } else {
					global $id;
					global $count;
					global $rows;
					global $row;
					global $val;
					$this->insertId = $this->rows = $this->row = $this->val = $id = $count = $rows = $row = $val = NULL;
					die("Oops, ".mysqli_error($this->connection));
				}
			}
			self::flush();
		}
		return $this;
	}

    /**
     * Reply and exit api
     *
     * @access public
     * @param  array or string
     * @return Json encoded data
     */
	public function reply ($a = "")
	{
		echo json_encode(["status" => $this->success, "data" => $a]);
		exit();
		return $this;
	}

    /**
     * Alt-Reply from api
     *
     * @access public
     * @param Use self::reply
     */
	public function respond ($a = "")
	{
		return self::reply($a);
	}

    /**
     * Clean used class variables
     *
     * @access private
     */
	private function flush ()
	{
		$this->table		= NULL;
		$this->response		= NULL;
		$this->queryType 	= NULL;
		$this->parameters	= NULL;
		$this->echoo 		= FALSE;
		$this->echoe 		= FALSE;
		$this->sql 			= "";
		$this->expecting 	= "";
		$this->whereClause	= "";
		return $this;
	}

    /**
     * Alt-Flush use self::flush
     *
     * @access private
     */
	private function clean ()
	{
		return self::flush();
	}

    /**
     * Walk through array values for protection
     *
     * @access private
     */
	private function walkMe (&$item, $key, $p = 0)
	{
		if(strtolower($item) == "null" && is_array($item)) return;
		if(is_null($item)) { $item = "''"; return; }
	    $item = $this->protection === TRUE || $p == 1 ?
	    	  "'".self::protect($item)."'" : "'".$item."'";
	}

    /**
     * Protect value
     *
     * @access public
     * @param string $a ~ Value
     * @return Protected value
     */
	public function protect ($a)
	{
		if(is_array($a)) return $a;
		$mqa = get_magic_quotes_gpc();
		$mep = function_exists("mysql_real_escape_string");
		if($mep) {
			if($mqa) $a = stripslashes($a);
// 			$a = mysql_real_escape_string($a);
		} else {
			if(!$mqa) $a = addslashes($a);
		}
		return htmlentities($a);
	}

    /**
     * Turn each posted variables to variable
     *
     * @access public
     */
	public function print_vars ()
	{
		if((isset($_POST["q"]) || isset($_POST["query"])) && isset($_POST["data"])) {
			$arr = array();
			$and = explode('&', $_POST["data"]);
			foreach ($and as $chunk) {
			    $a = explode("=", $chunk);
			    if ($a) {
			    	$k = urldecode($a[0]);
			    	$v = urldecode($a[1]);
			        global ${$k};
			        $arr[$k] = ${$k} = $this->protection == TRUE ? self::protect($v) : $v;
			    }
			}
			$_POST = $arr;
			// var_dump($_POST);
		} else {
			foreach ($_POST as $k => $v) {
			    global ${$k};
			    ${$k} =  $this->protection == TRUE ? self::protect($v) : $v;
			}
		}
		return $this;
	}

    /**
     * Echo in div
     *
     * @access public
     */
	public function log ($a="")
	{
		$s  = "width:auto;max-width:90%;margin:5% 5% 1% 1%;";
		$s .= "border-radius:1em;font-size:1.2em;box-shadow:";
		$s .= "2px 2px 1em (0, 0, 0, 0.25);font-family:consolas;";
		echo '<div style="'.$s.'">'.$a."</div>";
		return $this;
	}

    /**
     * Alt-var_dump for query result
     *
     * @access public
     */
	public function dump ()
	{
		var_dump($this->response);
		return $this;
	}

	public function _now ()
	{
		return date("Y/m/d h:i:s a");
	}

    /**
     * Set JSON header. !post ? die : live;
     *
     * @access public
     */
	public function jsonHeader ()
	{
		header("Content-Type: application/json; charset=UTF-8");
		header("Access-Control-Allow-Origin: *");
		if((!isset($_POST) || count($_POST) == 0) && (!isset($_GET) || count($_GET) == 0))
		die("Clarify your query");
		return $this;
	}

    /**
     * Alt-mysqli_close && End Class
     */
	function __destruct ()
	{
		if($this->isconnected)
		mysqli_close($this->connection);
	}

}

$db  	= new cdb();
$now 	= date("Y-m-d h:i:s a");

?>
