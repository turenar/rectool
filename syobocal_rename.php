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

  -c, --cache-only           use only cached data: do not retrieve network data
  -d, --debug                output more noisy information for debugger
  -e, --epgrec               enable epgrec path update feature
                             this requires epgrec's config.php is existing
                              in application dir
  -f, --fallback[=PROG]      fallback to exec external process
                              if syobocal has no info for specified file
                             this option conflicts with --get-path
                             PROG must be runnable
                              (default: \$APPDIR/update-filepath.php)
      --fallback-type=TYPE   set fallback type
                             TYPE is either of 'each' or 'last'
                              (if no arg passed with --fallback, default: last)
                              (otherwise, default: each)
  -i, --interactive          prompt the user whether to modify channel config
                             this can modify syobocal_channel.json
  -l, --loose                in epgrec mode, match only older name
                              instead of full path
                             this increases mysql query loads
  -n, --get-path             get renamed path in specified PATHs order
                             this conflicts with --fallback
  -q, --quiet                suppress stderr output but error
                             -qq makes no stderr output
                             but --fallback may output to stderr

  -h, --help                 display this help and exit
  -v, --version              display version information and exit

Configuration:
  \$APPDIR:
     This is not editable from user. This var is extracted as \$(basename "$0")

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
	private $stream_contexts;

	private $flag_cache_only = false;
	private $flag_epgrec = false;
	private $flag_fallback_prog = null;
	private $flag_fallback_type = null;
	private $flag_interactive = false;
	private $flag_loose = false;
	private $flag_no_action = false;
	private $epgrec_config = null;
	private $epgrec_dbconn = null;

	private $fallbacks = array();

	function __construct($cfg){
		global $script_path;
		$this->cfg = $cfg;

		$channel_json = $script_path.'/syobocal_channel.json';
		if (file_exists($channel_json)) {
			$this->cfg['channel'] = json_decode(file_get_contents($script_path . '/syobocal_channel.json'), true);
		} else {
			$this->cfg['channel'] = array();
		}

		$this->cache_db = new SQLite3($script_path . '/syobocal_cache.db');

		$stream_options = array(
			 'http' => array(
				'method' => 'GET',
				'header' => 'User-Agent: SyobocalRenamer/0 (https://github.com/turenar/rectool/blob/master/syobocal_rename.php)',
			),
		);
		$this->stream_context = stream_context_create($stream_options);
	}

	function run($argv){
		global $script_path;
		$this->application_name = $argv[0];

		if($this->cfg['user'] == '<<UserID>>'){
			$this->_err("illegal configuration (\$cfg['user'])");
			exit(ERR_FATAL_CONFIG);
		}

		$parser = new ArgParser('cdef::hilnq',
			array('--cache-only', '--debug', '--epgrec', '--fallback::', '--fallback-type:', '--help',
				'--interactive', '--loose', '--get-path', '--quiet'));
		$parser->parse($argv);
		$opterr = false;
		$options = $parser->getopts();
		foreach ($options as $opt) {
			switch ($opt[0]) {
			case '-c':
			case '--cache-only':
				$this->flag_cache_only = true;
				break;
			case '-d':
			case '--debug':
				$this->log_level = self::DEBUG;
				break;
			case '-e':
			case '--epgrec':
				$this->flag_epgrec = true;
				break;
			case '-f':
			case '--fallback':
				if ($opt[1] !== null) {
					$this->flag_fallback_prog = $opt[1];
					if ($this->flag_fallback_type === null) {
						$this->flag_fallback_type = 'each';
					}
				} elseif ($this->flag_fallback_prog === null) {
					$this->flag_fallback_prog = $script_path.'/update-filepath.php';
				}
				break;
			case '--fallback-type':
				switch ($opt[1]) {
				case null:
					$this->_err('specify option for --fallback-type');
					$opterr = true;
					break;
				case 'each':
				case 'last':
					$this->flag_fallback_type = $opt[1];
					break;
				default:
					$this->_err('unknown --fallback-type: %s', $opt[1]);
					$opterr = true;
					break;
				}
				break;
			case '-h':
			case '--help':
				$this->help();
				exit(0);
			case '-i':
			case '--interactive':
				$this->flag_interactive = true;
				break;
			case '-l':
			case '--loose':
				$this->flag_loose = true;
				break;
			case '-n':
			case '--get-path':
				$this->flag_no_action = true;
				break;
			case '-q':
			case '--quiet':
				$this->log_level = $this->log_level<=self::ERR ? self::NONE : self::ERR;
				break;
			case '-v':
			case '--version':
				$this->show_version();
				exit(0);
			default:
				$this->_err("illegal option: %s", $opt[0]);
				$opterr = true;
			}
		}

		$file_path = $parser->getargs();

		if (count($file_path) === 0) {
			$this->_err("missing file argument(s)");
			$opterr = true;
		}

		if ($this->flag_no_action && $this->flag_fallback_prog !== null) {
			$this->_err('options: --get-path and --fallback are incompatible');
			$opterr = true;
		}
		if ($this->flag_loose && !$this->flag_epgrec) {
			$this->_err('options: --loose requires --epgrec');
			$opterr = true;
		}
		if ($this->flag_fallback_type !== null && $this->flag_fallback_prog === null) {
			$this->_err('options: --fallback-type requires --fallback');
			$opterr = true;
		}
		if ($opterr) {
			fprintf(STDERR, "see `%s --help'\n", $this->application_name);
			exit(1);
		}

		if ($this->flag_fallback_prog !== null && $this->flag_fallback_type === null) {
			$this->flag_fallback_type = 'last';
		}

		$this->_dbg("syobocal user: %s", $this->cfg['user']);
		if ($this->flag_epgrec) {
			if(!file_exists(dirname(__FILE__).'/config.php')){
				$this->_err("epgrec's config.php is not found");
				exit(ERR_FATAL_CONFIG);
			}
			require_once(dirname(__FILE__).'/config.php');
			include_once( INSTALL_PATH . '/DBRecord.class.php' );
			include_once( INSTALL_PATH . '/Settings.class.php' );
			include_once( INSTALL_PATH . '/reclib.php' );
		}

		$this->update_db();

		foreach ($file_path as $elem) {
			$this->_dbg("target: %s", $elem);
			$this->process($elem);
		}

		if (count($this->fallbacks) > 0) {
			$this->exec_fallback_prog($this->fallbacks);
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
		$title = $matches['title'];
		$extension = $matches['extension'];

		$found = $this->search_program($date, $channel, $title);

		if ($found === null) {
			if ($this->flag_fallback_prog !== null) {
				if ($this->flag_fallback_type === 'each') {
					$this->exec_fallback_prog($file_path);
				} else /* fallback_type === last */ {
					$this->fallbacks[] = $file_path;
				}
			}
			return false;
		}

		$new_path = $cfg['new_path'];
		$pattern = array();
		$pattern['%Season%'] = $this->search_season($found['TID']);
		$pattern['%Title%'] = $this->escape_filename($found['Title']);
		$pattern['%Channel%'] = $channel;
		$pattern['%Date%'] = $matches['date'];
		$pattern['%Extra%'] = isset($matches['extra']) ? $matches['extra'] : '';
		$pattern['%Count%'] = $found['Count'];
		$pattern['%SubTitle%'] = empty($found['SubTitle']) ? '' : $this->escape_filename($found['SubTitle']);
		$pattern['%ShortTitle%'] = $this->escape_filename(empty($found['ShortTitle']) ? $found['Title'] : $found['ShortTitle']);
		$pattern['%OrigName%'] = $file_name;
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

	function escape_filename($str) {
		$pattern = '/['.preg_quote(substr($this->cfg['safe_filename'], 1), '/').']/u';
		$replacement = substr($this->cfg['safe_filename'], 0, 1);
		return preg_replace($pattern, $replacement, $str);
	}

	function exec_fallback_prog($file_path) {
		$this->_info(' fallbacking...');
		$command = escapeshellcmd($this->flag_fallback_prog);
		if (is_array($file_path)) {
			foreach ($file_path as $path) {
				$command.= ' '.escapeshellarg($path);
			}
		} else {
			$command.= ' '.escapeshellarg($file_path);
		}
		$this->_dbg('  command=%s', $command);
		passthru($command, $exit_status);
		$this->_info('  exit status: %d', $exit_status);
	}

	function search_program($date, $channel, $title) {
		$useOldMatch = $this->cfg['title_cmp_traditional'];
		$channelmap = $this->cfg['channel'];

		$normTitle = $this->normalize_file_title($useOldMatch, $title);

		$stmt = $this->cache_db->prepare(
			'SELECT subtitle AS SubTitle, channel AS ChName, title_tbl.title_id AS TID, title AS Title,
				season AS Season, count AS Count, start_date AS StTime, end_date AS EdTime, short_title AS ShortTitle
			FROM program_tbl LEFT JOIN title_tbl ON title_tbl.title_id = program_tbl.title_id
			WHERE start_date BETWEEN :date - 60*60*4 AND :date + 30 AND end_date > :date');
		$timestamp = $date->getTimestamp();
		$stmt->bindParam('date', $timestamp, SQLITE3_INTEGER);
		$result = $stmt->execute();
		$found = array();
		if ($program = $result->fetchArray(SQLITE3_ASSOC)) {
			$found = null;
			$found_without_channel = array();
			do {
				$progChId = isset($channelmap[$program['ChName']]) ? $channelmap[$program['ChName']] : array();
				$progTitle = $this->normalize_prog_title($useOldMatch, $program['Title']);
				$progShortTitle = $this->normalize_prog_title($useOldMatch, $program['ShortTitle']);
				if (/*$program['StTime'] <= $timestamp && $timestamp < $program['EdTime']
					&&*/ $this->compare_title($useOldMatch, $normTitle, $progTitle, $progShortTitle)) {
					if(in_array($channel, $progChId, true)){
						$found[] = $program;
					} else {
						$found_without_channel[] = $program;
					}
				}
			} while ($program = $result->fetchArray(SQLITE3_ASSOC));
		}

		if (count($found) >= 1) {
			if (count($found) > 1) {
				$this->_info(" multiple matched: %s", $title);
				foreach ($found as $prog) {
					$this->_dbg('  progTitle=%s, progChannel=%s', $prog['Title'], $prog['ChName']);
				}
			}
			return $found[0];
		}

		// cache miss
		if ($this->flag_cache_only) {
			$this->_err("Specified program is not found. title=%s", $normTitle);
			return null;
		}
		$this->_dbg(' program cache miss: %s', $title);
		$start_date = clone $date;
		//$start_date->modify('-15 min');
		date_modify($start_date, '-15 min');
		$end_date = clone $date;
		date_modify($end_date, '+75 min');

		$url = sprintf("http://cal.syoboi.jp/rss2.php?start=%s&end=%s&usr=%s&alt=json",
			$start_date->format('YmdHi'), $end_date->format('YmdHi'), urlencode($this->cfg['user']));
		$json = json_decode(file_get_contents($url, false, $this->stream_context), true);

		$found = array();
		$found_without_channel = array();
		foreach ($json['items'] as $program) {
			$stmt = $this->cache_db->prepare('INSERT OR REPLACE
				INTO program_tbl(program_id, start_date, end_date, title_id, channel, subtitle, count, short_title)
				VALUES (:program_id, :start_date, :end_date, :title_id, :channel, :subtitle, :count, :short_title)');
			$stmt->bindParam('program_id', $program['PID']);
			$stmt->bindParam('start_date', $program['StTime']);
			$stmt->bindParam('end_date', $program['EdTime']);
			$stmt->bindParam('title_id', $program['TID']);
			$stmt->bindParam('channel', $program['ChName']);
			$stmt->bindParam('subtitle', $program['SubTitle']);
			$stmt->bindParam('count', $program['Count']);
			$paramShortTitle = empty($program['ShortTitle']) ? null : $program['ShortTitle'];
			$stmt->bindParam('short_title', $paramShortTitle);
			$stmt->execute();
			$stmt = $this->cache_db->prepare('INSERT OR REPLACE
				INTO title_tbl(title_id, title, season)
				VALUES (:title_id, :title, (SELECT season FROM title_tbl WHERE title_id = :title_id))');
			$stmt->bindParam('title_id', $program['TID']);
			$stmt->bindParam('title', $program['Title']);
			$stmt->execute();

			$progChId = isset($channelmap[$program['ChName']]) ? $channelmap[$program['ChName']] : array();
			$progTitle = $this->normalize_prog_title($useOldMatch, $program['Title']);
			$progShortTitle = $this->normalize_prog_title($useOldMatch, $program['ShortTitle']);
			if ($program['StTime']-30 <= $date->getTimestamp() && $date->getTimestamp() < $program['EdTime']
			  && $this->compare_title($useOldMatch, $normTitle, $progTitle, $progShortTitle)) {
				if(in_array($channel, $progChId, true)){
					$found[] = $program;
				} else {
					$found_without_channel[] = $program;
				}
			}
		}

		if (count($found) === 0) {
			if (count($found_without_channel) === 0) {
				$this->_err("Specified program is not found. title=%s", $normTitle);
				return null;
			} elseif ($this->flag_interactive) {
				$this->_info("Specified named program seems to be found, but channel is not matched.");
				$this->_info(" Target: '%s' (%s) %s", $title, $channel, $date->format('Y-m-d H:i:s'));
				foreach ($found_without_channel as $key => $program) {
					$prog_start = strftime('%Y-%m-%d %H:%M:%S', $program['StTime']);
					$prog_end   = strftime('%Y-%m-%d %H:%M:%S', $program['EdTime']);
					$this->_info(" [%d] '%s' (%s) %s-%s", $key, $program['Title'], $program['ChName'],
						$prog_start, $prog_end);
				}
				$this->_info("Select your action or enter 'q' to exit");

				while(true) {
					$answer = trim(fgets(STDIN));
					if ($answer === false || $answer === 'q') {
						exit(0);
					} elseif (isset($found_without_channel[$answer])) {
						$program = $found_without_channel[$answer];
						$this->add_channel($program['ChName'], $channel);
						return $this->search_program($date, $channel, $title);
					} else {
						$this->_warn('unknown operation');
					}
				}
			} else {
				$this->_err("Specified named program seems to be found, but channel is not matched.");
				$this->_err(" title=%s, channel=%s", $title, $channel);
				foreach ($found_without_channel as $program) {
					$this->_err(" progTitle=%s, progChName=%s", $program['Title'], $program['ChName']);
				}
				$this->_err("Check your syobocal_channel.json");
				return null;
			}
		} elseif (count($found) > 1) {
			$this->_info(" multiple matched: %s", $title);
			foreach ($found as $prog) {
				$this->_dbg('  progTitle=%s, progChannel=%s', $prog['Title'], $prog['ChName']);
			}
		}
		return $found[0];
	}

	function compare_title($useOldMatch, $normTitle, $progTitle, $shortTitle) {
		$matchLength = $this->cfg['match_length'];
		if ($useOldMatch) {
			return strpos($progTitle, mb_substr($normTitle, 0, $matchLength)) !== false;
		}
		return $this->compare_title_internal($matchLength, $normTitle, $progTitle)
			|| $this->compare_title_internal($matchLength, $normTitle, $shortTitle);
	}

	function compare_title_internal($matchLength, $normTitle, $progTitle) {
		if (empty($progTitle)) { // probably short name
			return false;
		}
		$len = mb_strlen($progTitle);
		$tokmax = $len <= $matchLength ? 0 : $len-$matchLength;
		for ($i=0; $i <= $tokmax; $i++) {
			if (strpos($normTitle, mb_substr($progTitle, $i, $matchLength)) !== false) {
				$i==0 || $this->_dbg('Matched with offset: %d', $i);
				return true;
			}
		}
		return false;
	}

	function normalize_file_title($useOldMatch, $title) {
		$title = Normalizer::normalize($title, Normalizer::FORM_KC);
		if ($useOldMatch) {
			$title = strtr($title, $this->cfg['replace']['pre']);
		} else {
			$title = mb_strtolower($title);
		}
		$title = preg_replace('/['.preg_quote($this->cfg['symbols'], '/').']/u', '', $title);
		return $title;
	}

	function normalize_prog_title($useOldMatch, $title) {
		if (empty($title)) {
			return null;
		}
		$title = Normalizer::normalize($title, Normalizer::FORM_KC);
		if (!$useOldMatch) {
			$title = mb_strtolower(strtr($title, $this->cfg['replace']['newpre']));
		}
		$title = preg_replace('/['.preg_quote($this->cfg['symbols'], '/').']/u', '', $title);
		return $title;
	}

	function add_channel($name, $channel) {
		global $script_path;
		if (!isset($this->cfg['channel'][$name])) {
			$this->cfg['channel'][$name] = array();
		}
		$this->cfg['channel'][$name][] = $channel;
		file_put_contents($script_path . '/syobocal_channel.json', json_encode($this->cfg['channel'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
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

		if ($this->flag_cache_only) {
			$this->_info(' season data is not cached');
			return 'unknown';
		}
		$this->_dbg(' title cache miss: %d', $title_id);
		$url = "http://cal.syoboi.jp/db.php?Command=TitleLookup&TID=$title_id";

		$title_data = simplexml_load_string(file_get_contents($url, false, $this->stream_context));
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
			$this->mkdir_p($dstdir);
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
			copy($src, $dst);
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

	function mkdir_p($dir) {
		$parent = dirname($dir);
		if (!is_dir($parent)) {
			$this->mkdir_p($parent);
		}
		mkdir($dir, $this->cfg['dir_perm']);
		chgrp($dir, $this->cfg['file_group']);
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
			$conn->set_charset('utf-8');
			$this->epgrec_dbconn = $conn;
		}
		$conn = $this->epgrec_dbconn;

		$media_paths = $this->cfg['media_path'];

		$old = strtr(realpath(dirname($old)).'/'.basename($old), $media_paths);
		$new = strtr(realpath($new), $media_paths);

		$sql = sprintf('UPDATE `%s` SET `path` = ? WHERE `path` %s ?', $setting->tbl_prefix.TRANSCODE_TBL, $this->flag_loose ? 'LIKE' : '=');
		if($stmt = $conn->prepare($sql)){
			$file_pattern = $this->flag_loose ? '%/'.basename($old) : $old;
			$stmt->bind_param('ss', $new, $file_pattern);
			$stmt->execute();
			if ($stmt->affected_rows === -1) {
				$this->_err(" query error: (%d)%s", $stmt->errno, $stmt->error);
				exit(ERR_FATAL);
			} elseif ($stmt->affected_rows === 0) {
				$this->_warn(" specified path entry is not found or updated (%s)", $file_pattern);
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
			$format = "[syobocal] %s: " . $format_args[0] . "\n";
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
		$result->finalize();
		switch ($dbver) {
		case 0:
			$this->_dbg('Creating cache database...');
			$this->_sqlite_query('CREATE TABLE config_tbl(key TEXT PRIMARY KEY, value INT)');
			$this->_sqlite_query('CREATE TABLE program_tbl (
				program_id INT PRIMARY KEY, start_date INT, end_date INT, title_id INT, channel TEXT,
				subtitle TEXT, count INT)');
			$this->_sqlite_query('CREATE INDEX prog_date_idx ON program_tbl(start_date)');
			$this->_sqlite_query('CREATE TABLE title_tbl (title_id INT PRIMARY KEY, title TEXT, season TEXT)');
			$this->_sqlite_query("INSERT OR REPLACE INTO config_tbl (key, value) VALUES ('dbver', 1)");
		case 1:
			$this->_dbg('Updating database format (1->2)...');
			$this->_sqlite_query('ALTER TABLE program_tbl ADD short_title TEXT');
			$this->_sqlite_query('DROP INDEX prog_date_idx');
			$this->_sqlite_query('CREATE INDEX prog_date_idx ON program_tbl(start_date, end_date)');
			$this->_sqlite_query("INSERT OR REPLACE INTO config_tbl (key, value) VALUES ('dbver', 2)");
		}
	}

	function _sqlite_query($sql) {
		if (!$this->cache_db->exec($sql)) {
			$this->_err("Failed sql (%d:%s) %s", $this->cache_db->lastErrorCode(), $this->cache_db->lastErrorMsg(), $sql);
		}
	}

	function show_version() {
		global $script_path;
		chdir($script_path);
		echo 'Syobocal Renamer (';
		echo basename($this->application_name);
		echo ') rev:';
		passthru('git rev-parse --short HEAD');
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
			$opts[$opt] = $argtype;
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
				// -??? style arguments (pass 2)
				$opt = '-'.$saved_arg[0];
				$arg = strlen($saved_arg) <= 1 ? null : substr($saved_arg, 1);
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
					$saved_arg = substr($cur, 1);
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
				next($argv);
			} else {
				$argtype = $this->opts[$opt];
				if (($argtype === ':' && $arg !== false) || ($argtype === '::' && !$next_called)) {
					$this->options[] = array($opt, $arg);
					$saved_arg = null;
				} elseif ($argtype === '' || $argtype === '::') {
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
