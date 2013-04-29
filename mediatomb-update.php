<?php
if(!defined('STDIN')){
	echo 'stdin is not defined\n';
	die;
}


if($argc <= 2 || $argc >= 5){
	echo "Usage: " . basename($argv[0]) . " <orig_file> <new_file> [<title suffix>]";
}

$orig_filename = $argv[1];
$dest_filename = $argv[2];
#$orig_filename = trim(exec("readlink -f '{$argv[1]}'"));
#$dest_filename = trim(exec("readlink -f '{$argv[2]}'"));
$title_suffix = isset($argv[3]) ? $argv[3] : '';

$script_path = dirname(__FILE__);
chdir($script_path);
require_once($script_path . '/config.php');
include_once(INSTALL_PATH . '/DBRecord.class.php');
include_once(INSTALL_PATH . '/Settings.class.php');
include_once(INSTALL_PATH . '/recLog.inc.php');
include_once(INSTALL_PATH . '/reclib.php');
$settings = Settings::factory();
error_reporting(E_ALL);

if($settings->mediatomb_update == 1){
	$dbh = mysql_connect($settings->db_host, $settings->db_user, $settings->db_pass);
	if($dbh !== false){
		$sqlstr = 'use '.$settings->db_name;
		mysql_query($sqlstr);
		mysql_set_charset('utf8');
		$sqlstr = "SELECT `metadata`, `dc_title` FROM `mt_cds_object` WHERE `location` = 'F".mysql_real_escape_string($orig_filename)."'";
		$response = mysql_query($sqlstr);
		if(mysql_num_rows($response)<=0){
			echo "No data matched. Abort.";
			exit(1);
		}
		$mt_row = mysql_fetch_row($response);
		$sqlstr = "UPDATE `mt_cds_object` SET `metadata` = '".mysql_real_escape_string($mt_row[0])."', `dc_title` = '".mysql_real_escape_string($mt_row[1].$title_suffix)."' WHERE `location` = 'F".mysql_real_escape_string($dest_filename)."'";
		mysql_query($sqlstr);
	}
}
