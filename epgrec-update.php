<?php
include_once('config.php');
include_once( INSTALL_PATH . '/DBRecord.class.php' );
include_once( INSTALL_PATH . '/Smarty/Smarty.class.php' );
include_once( INSTALL_PATH . '/Settings.class.php' );
include_once( INSTALL_PATH . '/reclib.php' );
include_once( INSTALL_PATH . '/settings/menu_list.php' );

$settings = Settings::factory();

if($argc<2){
	echo 'Illegal args';
	exit(1);
}


// mysql_real_escape_stringより先に接続しておく必要がある
$dbh = @mysql_connect( $settings->db_host, $settings->db_user, $settings->db_pass );
mysql_set_charset('utf8');
try{
	$r = new DBRecord(RESERVE_TBL, 'path', $argv[1]);
}catch(exception $e){
	echo "No record found\n";
	exit(1);
}

if($argc==2){
	var_dump($r);
}else{
	$r->path = $argv[2];
	$r->update();
}
