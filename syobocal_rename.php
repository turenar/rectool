#!/usr/bin/php
<?php

$script_path = dirname(__FILE__);
require_once($script_path . '/syobocal_config.php');
error_reporting(E_ALL|E_NOTICE);

class SyobocalRenamer {

	private $application_name;
	private $cfg;
	private $flag_epgrec = false;
	private $flag_no_action = false;
	private $epgrec_config = null;
	private $epgrec_dbconn = null;

	function __construct($cfg){
		$this->cfg = $cfg;
	}

	function run($argv){
		$this->application_name = array_shift($argv);

		if($this->cfg['user'] == '<<UserID>>'){
			fprintf(STDERR, "%s: illegal configuration (\$cfg['user'])\n", $this->application_name);
			exit(ERR_FATAL_CONFIG);
		}

		$no_action = false;
		$epgrec = false;
		$file_path = array();
		while(true){
			$arg = array_shift($argv);
			if($arg === null){
				break;
			} elseif ($arg == '-n' || $arg == '--get-path') {
				$no_action = true;
			} elseif ($arg == '-e' || $arg == '--epgrec') {
				$epgrec = true;
			} elseif ($arg[0] == '-') {
				fprintf(STDERR, "%s: illegal option: %s\n", $this->application_name, $arg);
			} else {
				$file_path[] = $arg;
			}
		}

		if ($epgrec) {
			if(!file_exists(dirname(__FILE__).'/config.php')){
				fprintf(STDERR, "$application_name: epgrec's config.php is not found\n");
				exit(ERR_FATAL_CONFIG);
			}
			require_once(dirname(__FILE__).'/config.php');
			include_once( INSTALL_PATH . '/DBRecord.class.php' );
			include_once( INSTALL_PATH . '/Settings.class.php' );
			include_once( INSTALL_PATH . '/reclib.php' );
		}

		if (count($file_path) === 0) {
			fprintf(STDERR, "missing argument\n");
			fprintf(STDERR, "usage: %s [-n|--get-path] [-e|--epgrec] <path>\n", $this->application_name);
			exit(ERR_FATAL);
		}

		$this->flag_epgrec = $epgrec;
		$this->flag_no_action = $no_action;

		foreach ($file_path as $elem) {
			$this->process($elem);
		}
	}
	function process($file_path) {
		$cfg = $this->cfg;

		$file_name = basename($file_path);
		$result = preg_match($cfg['path_regex'], basename($file_name), $matches);
		if ($result === false) {
			fprintf(STDERR, "illegal regex syntax: %s\n", $cfg['path_regex']);
			exit(ERR_FATAL_CONFIG);
		}
		if ($result === 0) {
			fprintf(STDERR, "not match: %s (%s)\n", $cfg['path_regex'], $file_name);
			return false;
		}
		if(!(isset($matches['channel']) && isset($matches['date']) && isset($matches['title']) && isset($matches['extension']))){
			fprintf(STDERR, "illegal regex: {$cfg['path_regex']}\ncheck channel,date,title,extension named captures exist\n");
			exit(ERR_FATAL_CONFIG);
		}

		$channel = $matches['channel'];
		$date = DateTime::createFromFormat($cfg['date_format'], $matches['date']);
		if($date === false){
			fprintf(STDERR, "Illegal date format: %s as %s\n", $matches['date'], $cfg['date_format']);
			exit(ERR_FATAL_CONFIG);
		}
		$start_date = DateTime::createFromFormat($cfg['date_format'], $matches['date']);
		//$start_date->modify('-15 min');
		date_modify($start_date, '-15 min');
		$start_date = $start_date->format('YmdHi');
		$end_date = DateTime::createFromFormat($cfg['date_format'], $matches['date']);
		date_modify($end_date, '+75 min');
		$end_date = $end_date->format('YmdHi');
		$title = $matches['title'];
		$extension = $matches['extension'];

		$title = strtr($title, $cfg['replace']['pre']);

		$url = "http://cal.syoboi.jp/rss2.php?start=$start_date&end=$end_date&usr={$cfg['user']}&alt=json";
		$json = json_decode(file_get_contents($url), true);

		$channelmap = $cfg['channel'];

		$found = null;
		$found_without_channel = null;
		foreach ($json['items'] as $program) {
			$progChId = isset($channelmap[$program['ChName']]) ? $channelmap[$program['ChName']] : null;
			$progTitle = Normalizer::normalize($program['Title'], Normalizer::FORM_KC);
			$normTitle = mb_substr(Normalizer::normalize($title, Normalizer::FORM_KC), 0, 5);
			if (strpos($progTitle, $normTitle) !== false) {
				if($progChId == $channel){
					$found = $program;
					break;
				} else {
					$found_without_channel = $program;
				}
			}
		}

		if ($found === null) {
			if ($found_without_channel === null) {
				fprintf(STDERR, "Specified program is not found. title=%s\n", $title);
			} else {
				fprintf(STDERR, "Specified named program seems to be found, but channel is not matched.\n");
				fprintf(STDERR, " title=%s, channel=%s\n", $title, $channel);
				fprintf(STDERR, " progTitle=%s, progChName=%s\n", $program['Title'], $program['ChName']);
				fprintf(STDERR, "Check your syobocal_channel.json\n");
			}
			exit(ERR_FATAL_CONFIG);
		}

		$url = "http://cal.syoboi.jp/db.php?Command=TitleLookup&TID={$program['TID']}";

		$title_data = simplexml_load_string(file_get_contents($url));
		$title_data = $title_data->TitleItems->TitleItem;

		$new_path = $cfg['new_path'];
		$pattern = array();
		$pattern['%Season%'] = self::get_season($title_data->FirstYear, $title_data->FirstMonth);
		$pattern['%Title%'] = $found['Title'];
		$pattern['%Channel%'] = $channel;
		$pattern['%Date%'] = $matches['date'];
		$pattern['%Extra%'] = isset($matches['extra']) ? $matches['extra'] : '';
		$pattern['%Count%'] = $found['Count'];
		$pattern['%SubTitle%'] = empty($found['SubTitle']) ? '' : $found['SubTitle'];
		$pattern['%ext%'] = $extension;
		$new_path = strtr($new_path, $pattern);
		if ($this->flag_no_action) {
			echo $new_path."\n";
		} else {
			$this->safe_move($file_path, $new_path);
			if ($this->flag_epgrec) {
				$this->epgrec_update_path($file_path, $new_path);
			}
		}
	}

	function safe_move($src, $dst){
		$dstdir = dirname($dst);

		if(!file_exists($src)){
			echo "E: source file is not exist: $src\n";
			return false;
		}
		if(!is_dir($dstdir)) {
			mkdir($dstdir, 0775, true);
			chgrp($dstdir, $this->cfg['file_group']);
		}

		if(realpath($src)!==FALSE && realpath($src) === realpath($dst)){
			echo "E: not have to move: $src\n";
			return true;
		}

		link($src, "$src.bak");
		echo "".basename($src)." -> $dst\n";
		if(file_exists($dst)){
			echo "Replacing...\n";
			unlink($dst);
		}

		$srcstat = stat($src);
		$dststat = stat($dstdir);
		if($srcstat['dev'] === $dststat['dev']){
			link($src, $dst);
		}else{
			echo "D:cross-dev\n";
			copy($src, $dstfile);
		}

		if(!file_exists($dst)){
			echo("$dst is not created! Abort\n");
			if(!file_exists($src)){
				echo("$src is deleted. recoverying\n");
				rename("$src.bak", $src);
			}
			return true;
		}
		unlink("$src.bak");

		//sleep(1);
		//system("php '".EPGRDIR."/mediatomb-update.php' '".realpath($src)."' '".realpath($dstfile)."'", $retval);
		/*while($retval) {
			echo "Wait...";
			sleep(1);
			system("php '".EPGRDIR."/mediatomb-update.php' '".realpath($src)."' '".realpath($dstfile)."'", $retval);
		}*/
		unlink($src);
		return true;
	}

	static function get_season($year, $month) {
		$year = intval($year);
		$month = intval($month);
		$quarter = (int)(($month-1) / 3);
		if($quarter === 0){
			$year--;
			$quarter = 4;
		}
		return sprintf("%dY%dQ", $year, $quarter);
	}

	function epgrec_update_path($old, $new) {
		if ($this->epgrec_config === null) {
			$this->epgrec_config = Settings::factory();
		}
		$setting = $this->epgrec_config;
		if ($this->epgrec_dbconn === null) {
			$conn = @new mysqli( $setting->db_host, $setting->db_user, $setting->db_pass , $setting->db_name);
			if($conn->connect_error){
				fprintf(STDERR, "failed to connect with mysql: (%d)%s\n", $conn->connect_errno, $conn->connect_error);
				exit(ERR_FATAL);
			}
			$this->epgrec_dbconn = $conn;
		}
		$conn = $this->epgrec_dbconn;

		$media_paths = $this->cfg['media_path'];

		$old = strtr(realpath(dirname($old)).'/'.basename($old), $media_paths);
		$new = strtr(realpath($new), $media_paths);

		if($stmt = $conn->prepare(sprintf('UPDATE `%s` SET `path` = ? WHERE `path` = ?', $setting->tbl_prefix.TRANSCODE_TBL))){
			$stmt->bind_param('ss', $new, $old);
			$stmt->execute();
			if ($stmt->affected_rows === -1) {
				fprintf(STDERR, " query error: (%d)%s\n", $stmt->errno, $stmt->error);
				exit(ERR_FATAL);
			} elseif ($stmt->affected_rows === 0) {
				fprintf(STDERR, " specified path entry is not found (%s)\n", $old);
				return false;
			}
			return true;
		} else {
			fprintf(STDERR, " query error: (%d)%s\n", $conn->errno, $conn->error);
			exit(ERR_FATAL);
		}
	}
}

define('ERR_FATAL_CONFIG', 3);
define('ERR_FATAL', 2);

$syobo = new SyobocalRenamer($cfg);
$syobo->run($argv);
