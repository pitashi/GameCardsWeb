<?php
session_name("bbb");
session_start();

$Conf=array();
	
/** GLOBAL SETTINGS HERE, for administrator to modify */
//Point to dev



if($_SERVER["SERVER_NAME"]=="localhost"){
	$Conf["database"]["host"]="localhost";
	$Conf["database"]["databasename"]="sarugmhnuf_db1";
	$Conf["database"]["username"]="root";
	$Conf["database"]["password"]="";
	$Conf["database"]["table_prefix"]="mytcg";
}else{
	$Conf["database"]["host"]="localhost";
	$Conf["database"]["databasename"]="sarugmhnuf_db1";
	$Conf["database"]["username"]="sarugmhnuf_1";
	$Conf["database"]["password"]="j2gcuH88";
	$Conf["database"]["table_prefix"]="mytcg";
}

$Conf["files"]["directory_root"]="";
$Conf["files"]["directory_var"]="/usr/www/users/mytcga/var/";
$Conf["files"]["file_sql_log"]="sqlq.log";

$Conf["system"]["session_expire_in_minutes"]="20";
$Conf["system"]["ssl_is_on"]="0";
$Conf["system"]["rootdir"]="public_html";
/** NO MORE CONFIGURABLE SETTINGS BELOW THIS LINE */

if ($Conf["system"]["ssl_is_on"]=="1"){
	$sHTTP="https://";
} else {
	$sHTTP="http://";
}
$Conf["system"]["web_url"]=$sHTTP.$_SERVER["HTTP_HOST"];
$Conf["user"]["logged_in"]="0";
$Conf["user"]["ip"]=$_SERVER["REMOTE_ADDR"];
$Conf["user"]["user_agent"]=$_SERVER["HTTP_USER_AGENT"];

ini_set("session.gc_maxlifetime"
	,intval($Conf["system"]["session_expire_in_minutes"])*60);

?>
