#!/usr/bin/php
<?php

$script_path = dirname(__FILE__);
require_once($script_path . '/syobocal_config.php');
error_reporting(E_ALL|E_NOTICE);

class SyobocalRenamer {
	const NONE = 0;
	const ERR = 1;
	const WARN = 2;
	const INFO = 3;
	const DEBUG = 4;

	private $application_name;
	private $cfg;
	private $log_level = self::INFO;
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
			$this->_err("illegal configuration (\$cfg['user'])");
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
				$this->_err("illegal option: %s", $arg);
			} else {
				$file_path[] = $arg;
			}
		}

		if ($epgrec) {
			if(!file_exists(dirname(__FILE__).'/config.php')){
				$this->_err("epgrec's config.php is not found");
				exit(ERR_FATAL_CONFIG);
			}
			require_once(dirname(__FILE__).'/config.php');
			include_once( INSTALL_PATH . '/DBRecord.class.php' );
			include_once( INSTALL_PATH . '/Settings.class.php' );
			include_once( INSTALL_PATH . '/reclib.php' );
		}

		if (count($file_path) === 0) {
			$this->_err("missing argument");
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
			$this->_err("illegal regex syntax: %s", $cfg['path_regex']);
			exit(ERR_FATAL_CONFIG);
		}
		if ($result === 0) {
			$this->_warn("not match: %s (%s)", $cfg['path_regex'], $file_name);
			return false;
		}
		if(!(isset($matches['channel']) && isset($matches['date']) && isset($matches['title']) && isset($matches['extension']))){
			$this->_err("illegal regex: %s", $cfg['path_regex']);
			$this->_err(" check channel,date,title,extension named captures exist");
			exit(ERR_FATAL_CONFIG);
		}

		$channel = $matches['channel'];
		$date = DateTime::createFromFormat($cfg['date_format'], $matches['date']);
		if($date === false){
			$this->_err("Illegal date format: %s as %s", $matches['date'], $cfg['date_format']);
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

		$found = $this->search_program($start_date, $end_date, $channel, $title);

		$new_path = $cfg['new_path'];
		$pattern = array();
		$pattern['%Season%'] = $this->search_season($found['TID']);
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

	function search_program($start_date, $end_date, $channel, $title) {
		$url = sprintf("http://cal.syoboi.jp/rss2.php?start=%s&end=%s&usr=%s&alt=json",
			$start_date, $end_date, urlencode($this->cfg['user']));
		$json = json_decode(file_get_contents($url), true);

		$channelmap = $this->cfg['channel'];

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
				$this->_err("Specified program is not found. title=%s", $title);
			} else {
				$this->_err("Specified named program seems to be found, but channel is not matched.");
				$this->_err(" title=%s, channel=%s", $title, $channel);
				$this->_err(" progTitle=%s, progChName=%s", $program['Title'], $program['ChName']);
				$this->_err("Check your syobocal_channel.json");
			}
			exit(ERR_FATAL_CONFIG);
		}
		return $found;
	}

	function search_season($titleId) {
		$url = "http://cal.syoboi.jp/db.php?Command=TitleLookup&TID=$titleId";

		$title_data = simplexml_load_string(file_get_contents($url));
		$title_data = $title_data->TitleItems->TitleItem;

		return self::get_season($title_data->FirstYear, $title_data->FirstMonth);
	}

	function safe_move($src, $dst){
		$dstdir = dirname($dst);

		if(!file_exists($src)){
			$this->_err("source file is not exist: %d", $src);
			return false;
		}
		if(!is_dir($dstdir)) {
			mkdir($dstdir, 0775, true);
			chgrp($dstdir, $this->cfg['file_group']);
		}

		if(realpath($src)!==FALSE && realpath($src) === realpath($dst)){
			$this->_err("not have to move: %s", $src);
			return true;
		}

		link($src, "$src.bak");
		echo "".basename($src)." -> $dst\n";
		if(file_exists($dst)){
			$this->_info("Replacing...");
			unlink($dst);
		}

		$srcstat = stat($src);
		$dststat = stat($dstdir);
		if($srcstat['dev'] === $dststat['dev']){
			link($src, $dst);
		}else{
			$this->_info("cross-dev");
			copy($src, $dstfile);
		}

		if(!file_exists($dst)){
			$this->_err("%s is not created! Abort", $dst);
			if(!file_exists($src)){
				$this->_err("%s is deleted. recoverying", $src);
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
				$this->_err("failed to connect with mysql: (%d)%s", $conn->connect_errno, $conn->connect_error);
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
				$this->_err(" query error: (%d)%s", $stmt->errno, $stmt->error);
				exit(ERR_FATAL);
			} elseif ($stmt->affected_rows === 0) {
				$this->_warn(" specified path entry is not found (%s)", $old);
				return false;
			}
			return true;
		} else {
			$this->_err(" query error: (%d)%s", $conn->errno, $conn->error);
			exit(ERR_FATAL);
		}
	}

	function _log($level, $head, $format_args /*, ...*/) {
		if ($this->log_level >= $level) {
			$format = "%s: " . $format_args[0] . "\n";
			$format_args[0] = $head;
			vfprintf(STDERR, $format, $format_args);
		}
	}

	function _err($message) {
		$format = func_get_args();
		$this->_log(self::ERR, 'E', $format);
	}

	function _warn($message) {
		$format = func_get_args();
		$this->_log(self::WARN, 'W', $format);
	}

	function _info($message) {
		$format = func_get_args();
		$this->_log(self::INFO, 'I', $format);
	}

	function _dbg($message) {
		$format = func_get_args();
		$this->_log(self::DEBUG, 'D', $format);
	}
}

define('ERR_FATAL_CONFIG', 3);
define('ERR_FATAL', 2);

$syobo = new SyobocalRenamer($cfg);
$syobo->run($argv);
