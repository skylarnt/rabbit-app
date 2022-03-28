<?php

/**
 * query("add") i.e if(query("add"))
 *
 * @param 	string
 * @return 	$_REQUEST["query"] == param
 */
function query ($a) {
	return trim($a) == $_REQUEST["query"];
}

/**
 * q("add") i.e if(q("add"))
 *
 * @param 	string
 * @return 	$_REQUEST["q"] == param
 */
// function q ($a) {
// 	if($_REQUEST["q"] == trim($a)) {
// 		if(isset($_POST['token']))  return true;
// 		else 						die("Where is my token?");
// 	}
// 	return false;
// }

function q ($a) {
    if(empty($_REQUEST)) {
        return false;
    } else {
    	$req 		= $_REQUEST["q"] == trim($a) ? true : false;
    	$_prm 		= explode("&", $_GET['q']);
		$_GET['q'] 	= isset($_GET['q']) ? strtolower($_prm[0]) : '';
		if(count($_prm) > 1) {
			foreach ($_prm as $_prv) {
				$_prp = explode("=", $_prv);
				$_GET[$_prp[0]] = $_prp[1];
			}
		}
		
	    return $req;
    }
}

/**
 * protect(<string>)
 *
 * @param 	string
 * @return 	Protected string
 */
function protect ($a) {
	if(is_array($a)) return $a;
	$mqa = get_magic_quotes_gpc();
	$mep = function_exists("mysql_real_escape_string");
	if($mep) {
		if($mqa) $a = stripslashes($a);
		// $a = mysql_real_escape_string($a);
	} else {
		if(!$mqa) $a = addslashes($a);
	}
	return htmlentities($a);
}

/**
 * isset_post("add")
 *
 * @param 	string
 * @param 	string ~ function name
 * @param 	int
 * @return 	v_print($_POST) + isset($_POST["add"])
 */
function isset_post ($a = "", $b = 0, $c = 0) {
	v_print($_POST, $b, $c);
	return !empty($a) ? isset($_POST[$a]) : count($_POST) > 0;
}

/**
 * isset_get("add")
 *
 * @param 	string
 * @param 	string ~ function name
 * @param 	int
 * @return 	v_print($_GET) + isset($_GET["add"])
 */
function isset_get ($a = "", $b = 0, $c = 0) {
	v_print($_GET, $b, $c);
	return !empty($a) ? isset($_GET[$a]) : count($_GET) > 0;
}

/**
 * Turn array to variables
 *
 * @param 	array
 */
// function _print_v ($a = 0, $p = 0) {
// 	$arr = is_array($a) ? $a : $_POST;
// 	foreach ($arr as $k => $v) {
// 	    global ${$k};
// 	    $arr[$k] = ${$k} = $p == 0 ? $v : protect($v);
// 	}
// 	return $arr;
// }

/**
 * Turn array to variables
 *
 * @param 	array
 * @param 	string 		~ function name
 * @param 	int 		~ if > 1 will echo array keys for debuging purpose
 * @var 	$is_empty 	~ list of empty variables
 */
function print_v ($a = 0, $b = 0, $c = 0) {
	$arr = is_array($a) ? $a : $_POST;
	$emp = array();
	$rea = array();
	foreach ($arr as $k => $v) {
	  if($c > 1) echo $k." | ";
		if(is_callable($b))	$v = $b($v, $k);
	    global ${$k};
	    ${$k} 		= $v;
	    $rea[$k] 	= $v;
			if(!is_array($v)) {
				$nv = trim($v);
				if(empty($nv)) {
					array_push($emp, $k);
				}
			}
	}
	global $is_empty;
	$is_empty = $emp;
	return $rea;
}

/**
 * Iterate through a given array
 *
 * @param 	array 		~ 	Array of data
 * @param 	function 	~	function(key, value)
 * @param 	else 		~	Works if array is empty
 */
$each = function ($arr, $func, $else = "") {
	if(is_int($arr)) {
		for ($i=0; $i < $arr; $i++) $func($i);
		return;
	} else if (is_array($arr)) {
		if(!empty($else) && count($arr) == 0) {
			echo $else;
			return;
		}
		foreach ($arr as $k => $v) $func($k, $v);
	} else {
		echo $else;
		return;
	}
};


function saveDP ($index, $name) {
	$size = $_FILES[$index]['size'];
	$extn = explode(".", $_FILES[$index]['name']);
	$extn = trim($extn[count($extn)-1]);
	$temp = $_FILES[$index]['tmp_name'];
	$type = explode("/", $_FILES[$index]["type"])[0];
	$acpt = ["JPEG", "JPG", "GIF", "PNG"];
	if((in_array(strtoupper($extn), $acpt) || $type == "image") && $size < (20 * 1024 * 1024)) {
		$writePath = "./assets/dp/".$name.".".$extn;
		if(file_exists($writePath)){
	        unlink($writePath);
	    }
		return move_uploaded_file($temp, $writePath);
	}
	return false;
}


function saveCover ($index, $name) {
	$size = $_FILES[$index]['size'];
	$extn = explode(".", $_FILES[$index]['name']);
	$extn = trim($extn[count($extn)-1]);
	$temp = $_FILES[$index]['tmp_name'];
	$type = explode("/", $_FILES[$index]["type"])[0];
	$acpt = ["JPEG", "JPG", "GIF", "PNG"];
	if((in_array(strtoupper($extn), $acpt) || $type == "image") && $size < (20 * 1024 * 1024)) {
		$writePath = "./assets/covers/".$name.".".$extn;
		if(file_exists($writePath)){
	        unlink($writePath);
	    }
		return move_uploaded_file($temp, $writePath);
	}
	return false;
}


function is_uploading ($name) {
	return !empty($_FILES[$name]["name"]);
}

function i_ext ($name) {
	$ext = $_FILES[$name]["name"];
	$ext = explode(".", $ext);
	$ext = $ext[count($ext)-1];
	return empty($ext) ? "" : $ext;
}

function amt ($a) {
    return "&#8358;".number_format((float)$a, 2, '.', '');
}

function api ($q) {
    return "https://api.rabbit.africa/v1/?q=".$q;
}

?>
