#!/usr/bin/php
<?php

$script_path = dirname(__FILE__);
require_once($script_path . '/syobocal_config.php');
error_reporting(E_ALL|E_NOTICE);

class SyobocalRenamer {
	function help() {
		echo <<<EOT
Usage: {$this->application_name} [OPTION]... [PATH]...
Rename the specified PATHs

  -d, --debug      output more noisy information for debugger
  -e, --epgrec     enable epgrec path update feature
                   this requires epgrec's config.php is existing
		    in application dir
  -h, --help       display this help and exit
  -n, --get-path   get paths to rename the PATHs
		   output order is PATHs order
  -q, --quiet      suppress stderr output but error

This application caches SyoboiCalendar's data for less network traffic.
Cached data is found in syobocal_cache.db as sqlite3 database.
EOT;
	}

	const NONE = 0;
	const ERR = 1;
	const WARN = 2;
	const INFO = 3;
	const DEBUG = 4;

	private $application_name;
	private $cfg;
	private $log_level = self::INFO;
	private $cache_db;

	private $flag_epgrec = false;
	private $flag_no_action = false;
	private $epgrec_config = null;
	private $epgrec_dbconn = null;

	function __construct($cfg){
		global $script_path;
		$this->cfg = $cfg;

		$this->cache_db = new SQLite3($script_path . '/syobocal_cache.db');
	}

	function run($argv){
		$this->application_name = $argv[0];

		if($this->cfg['user'] == '<<UserID>>'){
			$this->_err("illegal configuration (\$cfg['user'])");
			exit(ERR_FATAL_CONFIG);
		}

		$no_action = false;
		$epgrec = false;

		$parser = new ArgParser('dehnq', array('--debug', '--epgrec', '--help', '--get-path', '--quiet'));
		$parser->parse($argv);
		$options = $parser->getopts();
		foreach ($options as $opt) {
			switch ($opt[0]) {
			case '-d':
			case '--debug':
				$this->log_level = self::DEBUG;
				break;
			case '-e':
			case '--epgrec':
				$epgrec = true;
				break;
			case '-h':
			case '--help':
				$this->help();
				exit(0);
			case '-n':
			case '--get-path':
				$no_action = true;
				break;
			case '-q':
			case '--quiet':
				$this->log_level = self::ERROR;
				break;
			default:
				$this->_err("illegal option: %s", $opt[0]);
			}
		}

		$file_path = $parser->getargs();

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

		$this->update_db();

		foreach ($file_path as $elem) {
			$this->_dbg("target: %s", $elem);
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
		$end_date = DateTime::createFromFormat($cfg['date_format'], $matches['date']);
		date_modify($end_date, '+75 min');
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
		$normTitle = mb_substr(Normalizer::normalize($title, Normalizer::FORM_KC), 0, 5);
		$channelmap = $this->cfg['channel'];

		$stmt = $this->cache_db->prepare(
			'SELECT subtitle AS SubTitle, channel AS ChName, title_tbl.title_id AS TID, title AS Title, season AS Season, count AS Count
			FROM program_tbl LEFT JOIN title_tbl ON title_tbl.title_id = program_tbl.title_id
			WHERE start_date >= :start_date AND end_date <= :end_date');
		$stTimestamp = $start_date->getTimestamp();
		$edTimestamp = $end_date->getTimestamp();
		$stmt->bindParam('start_date', $stTimestamp, SQLITE3_INTEGER);
		$stmt->bindParam('end_date', $edTimestamp, SQLITE3_INTEGER);
		$result = $stmt->execute();
		if ($program = $result->fetchArray(SQLITE3_ASSOC)) {
			$found = null;
			$found_without_channel = array();
			do {
				$progChId = isset($channelmap[$program['ChName']]) ? $channelmap[$program['ChName']] : null;
				$progTitle = Normalizer::normalize($program['Title'], Normalizer::FORM_KC);
				if (strpos($progTitle, $normTitle) !== false) {
					if($progChId == $channel){
						return $program;
					} else {
						$found_without_channel[] = $program;
					}
				}
			} while ($program = $result->fetchArray(SQLITE3_ASSOC));
		}

		// cache miss
		$url = sprintf("http://cal.syoboi.jp/rss2.php?start=%s&end=%s&usr=%s&alt=json",
			$start_date->format('YmdHi'), $end_date->format('YmdHi'), urlencode($this->cfg['user']));
		$json = json_decode(file_get_contents($url), true);

		$this->_dbg(' program cache miss: %s', $title);
		$found = null;
		$found_without_channel = array();
		foreach ($json['items'] as $program) {
			$stmt = $this->cache_db->prepare('INSERT OR REPLACE
				INTO program_tbl(program_id, start_date, end_date, title_id, channel, subtitle, count)
				VALUES (:program_id, :start_date, :end_date, :title_id, :channel, :subtitle, :count)');
			$stmt->bindParam('program_id', $program['PID']);
			$stmt->bindParam('start_date', $program['StTime']);
			$stmt->bindParam('end_date', $program['EdTime']);
			$stmt->bindParam('title_id', $program['TID']);
			$stmt->bindParam('channel', $program['ChName']);
			$stmt->bindParam('subtitle', $program['SubTitle']);
			$stmt->bindParam('count', $program['Count']);
			$stmt->execute();
			$stmt = $this->cache_db->prepare('INSERT OR REPLACE
				INTO title_tbl(title_id, title, season)
				VALUES (:title_id, :title, (SELECT season FROM title_tbl WHERE title_id = :title_id))');
			$stmt->bindParam('title_id', $program['TID']);
			$stmt->bindParam('title', $program['Title']);
			$stmt->execute();

			$progChId = isset($channelmap[$program['ChName']]) ? $channelmap[$program['ChName']] : null;
			$progTitle = Normalizer::normalize($program['Title'], Normalizer::FORM_KC);
			if (strpos($progTitle, $normTitle) !== false) {
				if($progChId == $channel){
					$found = $program;
				} else {
					$found_without_channel[] = $program;
				}
			}
		}

		if ($found === null) {
			if (count($found_without_channel) === 0) {
				$this->_err("Specified program is not found. title=%s", $title);
			} else {
				$this->_err("Specified named program seems to be found, but channel is not matched.");
				$this->_err(" title=%s, channel=%s", $title, $channel);
				foreach ($found_without_channel as $program) {
					$this->_err(" progTitle=%s, progChName=%s", $program['Title'], $program['ChName']);
				}
				$this->_err("Check your syobocal_channel.json");
			}
			exit(ERR_FATAL_CONFIG);
		}
		return $found;
	}

	function search_season($title_id) {
		$stmt = $this->cache_db->prepare('SELECT season FROM title_tbl WHERE title_id = :title_id');
		$stmt->bindParam('title_id', $title_id, SQLITE3_INTEGER);
		$result = $stmt->execute();
		if ($res_arr = $result->fetchArray(SQLITE3_NUM)) {
			$found_row = true;
			if($res_arr[0] !== null){
				return $res_arr[0];
			}
		} else {
			$found_row = false;
		}

		$this->_dbg(' title cache miss: %d', $title_id);
		$url = "http://cal.syoboi.jp/db.php?Command=TitleLookup&TID=$title_id";

		$title_data = simplexml_load_string(file_get_contents($url));
		$title_data = $title_data->TitleItems->TitleItem;

		if (empty($title_data->FirstYear) || empty($title_data->FirstMonth)) {
			return "unknown";
		} else {
			$season = self::get_season($title_data->FirstYear, $title_data->FirstMonth);
			if ($found_row) {
				$stmt = $this->cache_db->prepare('UPDATE title_tbl SET season = :season WHERE title_id = :title_id');
			} else {
				$stmt = $this->cache_db->prepare('INSERT INTO title_tbl (title_id, season) VALUES (:title_id, :season)');
			}
			$stmt->bindParam('title_id', $title_id, SQLITE3_INTEGER);
			$stmt->bindParam('season', $season, SQLITE3_TEXT);
			$result = $stmt->execute();
			return $season;
		}
	}

	function safe_move($src, $dst){
		$dstdir = dirname($dst);

		if(!file_exists($src)){
			$this->_err("source file is not exist: %s", $src);
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

	function update_db() {
		$cache_db = $this->cache_db;
		$stmt = $cache_db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = 'config_tbl'");
		$dbver = 0;
		$result = $stmt->execute();
		if ($result->fetchArray(SQLITE3_NUM)) {
			$this->_dbg('config_tbl is found');
			$stmt = $cache_db->prepare("SELECT value FROM config_tbl WHERE key = 'dbver'");
			$result = $stmt->execute();
			if ($res_arr = $result->fetchArray(SQLITE3_NUM)) {
				$dbver = $res_arr[0];
				$this->_dbg('dbver is found: %d', $dbver);
			}
		}

		if ($dbver <= 0) {
			$this->_dbg('Creating cache database...');
			$this->_sqlite_query('CREATE TABLE config_tbl(key TEXT, value INT)');
			$this->_sqlite_query('CREATE TABLE program_tbl (
				program_id INT PRIMARY KEY, start_date INT, end_date INT, title_id INT, channel TEXT,
				subtitle TEXT, count INT)');
			$this->_sqlite_query('CREATE INDEX prog_date_idx ON program_tbl(start_date)');
			$this->_sqlite_query('CREATE TABLE title_tbl (title_id INT PRIMARY KEY, title TEXT, season TEXT)');
			$this->_sqlite_query("INSERT OR REPLACE INTO config_tbl (key, value) VALUES ('dbver', 1)");
		}
	}

	function _sqlite_query($sql) {
		if (!$this->cache_db->exec($sql)) {
			$this->_err("Failed sql (%d:%s) %s", $this->cache_db->lastErrorCode(), $this->cache_db->lastErrorMsg(), $sql);
		}
	}
}

class ArgParser {
	// K: -a or --hoge / V: ''(no arg), ':'(required arg), '::'(optional arg)
	private $opts;
	private $options;
	private $arguments;

	function __construct($shortopt, $longopt) {
		$opts = array();
		preg_match_all('/([a-zA-Z0-9])(:{0,2})/', $shortopt, $shortopts, PREG_SET_ORDER);
		foreach ($shortopts as $opt) {
			$opts['-'.$opt[1]] = $opt[2];
		}
		foreach ($longopt as $opt) {
			$argtype = '';
			if (substr($opt, -2) === '::') {
				$opt = substr($opt, 0, -2);
				$argtype = '::';
			} elseif (substr($opt, -1) === ':') {
				$opt = substr($opt, 0, -1);
				$argtype = ':';
			}
			$opts['--'.$opt] = $argtype;
		}
		$this->opts = $opts;
	}

	function parse($argv) {
		reset($argv);
		next($argv); // skip $argv[0]: application name
		$this->options = array();
		$this->arguments = array();
		$saved_arg = null;
		while(current($argv) !== false) {
			$next_called = false;
			$cur = current($argv);
			if ($saved_arg !== null) {
				$opt = $saved_arg[0];
				$arg = substr($saved_arg, 1);
				$saved_arg = $arg;
			} elseif ($cur === '--') {
				while(next($argv) !== false) {
					$this->arguments[] = current($argv);
				}
				break;
			} elseif (strpos($cur, '--') === 0) {
				$argsep = strpos($cur, '=');
				if ($argsep === false) {
					$opt = $cur;
					$arg = next($argv);
					$next_called = true;
				} else {
					$opt = substr($cur, 0, $argsep);
					$arg = substr($cur, $argsep+1);
				}
			} elseif ($cur[0] === '-') {
				if (strlen($cur) === 2) {
					$opt = $cur;
					$arg = next($argv);
					$next_called = true;
				} else {
					$saved_arg = $cur;
					continue;
				}
			} else {
				$this->arguments[] = $cur;
				next($argv);
				continue;
			}

			// check opt is supported
			if (!isset($this->opts[$opt])) {
				// unknown option
				$this->options[] = array($opt, null);
			} else {
				$argtype = $this->opts[$opt];
				if (($argtype === ':' && $arg !== false) || ($argtype === '::' && ($opt === false || $opt[0] !== '-'))) {
					$this->options[] = array($opt, $arg);
					$saved_arg = null;
				} elseif ($argtype === '') {
					$arg_consumed = false;
					$this->options[] = array($opt, null);
					if ($next_called) {
						prev($argv);
					}
				} else {
					// arg is required
					fprintf(STDERR, "%s: %s requires argument\n", basename($argv[0]), $opt);
					exit(1);
				}
				if ($saved_arg === null) {
					next($argv);
				}
			}
		}
	}

	function getopts() {
		return $this->options;
	}

	function getargs() {
		return $this->arguments;
	}
}

define('ERR_FATAL_CONFIG', 3);
define('ERR_FATAL', 2);

$syobo = new SyobocalRenamer($cfg);
$syobo->run($argv);
