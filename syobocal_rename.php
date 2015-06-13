#!/usr/bin/php
<?php

$script_path = dirname(__FILE__);
require_once($script_path . '/syobocal_config.php');

function main($argv){
	global $cfg, $script_path;

	$application_name = array_shift($argv);

	if($cfg['user'] == '<<UserID>>'){
		fprintf(STDERR, "$application_name: illegal configuration (\$cfg['user'])\n");
		exit(2);
	}

	$no_action = false;
	$file_path = null;
	while(true){
		$arg = array_shift($argv);
		if($arg === null){
			break;
		} elseif ($arg == '-n' || $arg == '--get-path') {
			$no_action = true;
		} elseif ($arg[0] == '-') {
			fprintf(STDERR, "$application_name: illegal option: $arg\n");
		} else {
			if ($file_path !== null) {
				fprintf(STDERR, "$application_name: specify single file path\n");
				exit(1);
			}
			$file_path = $arg;
		}
	}

	if ($file_path === null) {
		fprintf(STDERR, "missing argument\n");
		fprintf(STDERR, "usage: $application_name [-n|--get-path] <path>\n");
		exit(1);
	}

	$file_name = basename($file_path);
	$result = preg_match($cfg['path_regex'], basename($file_name), $matches);
	if ($result === false) {
		fprintf(STDERR, "illegal regex syntax: {$cfg['path_regex']}\n");
		exit(1);
	}
	if ($result === 0) {
		fprintf(STDERR, "not match: {$cfg['path_regex']}\n");
		exit(1);
	}
	if(!(isset($matches['channel']) && isset($matches['date']) && isset($matches['title']) && isset($matches['extension']))){
		fprintf(STDERR, "illegal regex: {$cfg['path_regex']}\ncheck channel,date,title,extension named captures exist\n");
		exit(1);
	}

	$channel = $matches['channel'];
	$date = DateTime::createFromFormat($cfg['date_format'], $matches['date']);
	if($date === false){
		fprintf(STDERR, "Illegal date format: %s as %s\n", $matches['date'], $cfg['date_format']);
		exit(1);
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

	$channelmap = json_decode(file_get_contents($script_path . '/syobocal_channel.json'), true);

	$found = null;
	$found_without_channel = null;
	foreach ($json['items'] as $program) {
		$progChId = isset($channelmap[$program['ChName']]) ? $channelmap[$program['ChName']] : null;
		$progTitle = Normalizer::normalize($program['Title'], Normalizer::FORM_KC);
		$normTitle = substr(Normalizer::normalize($title, Normalizer::FORM_KC), 0, 5);
		if (strpos($progTitle, $normTitle) !== false) {
			if($progChId == $channel){
				$found = $program;
			} else {
				$found_without_channel = $program;
			}
			break;
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
		exit(1);
	}

	$url = "http://cal.syoboi.jp/db.php?Command=TitleLookup&TID={$program['TID']}";

	$title_data = simplexml_load_string(file_get_contents($url));
	$title_data = $title_data->TitleItems->TitleItem;

	$new_path = $cfg['new_path'];
	$pattern = array();
	$pattern['%Season%'] = get_season($title_data->FirstYear, $title_data->FirstMonth);
	$pattern['%Title%'] = $found['Title'];
	$pattern['%Channel%'] = $channel;
	$pattern['%Extra%'] = isset($matches['extra']) ? $matches['extra'] : '';
	$pattern['%Count%'] = $found['Count'];
	$pattern['%SubTitle%'] = empty($found['SubTitle']) ? '' : $found['SubTitle'];
	$pattern['%ext%'] = $extension;
	$new_path = strtr($new_path, $pattern);
	if ($no_action) {
		echo $new_path;
	} else {
		safe_move($file_path, $new_path);
	}
}

function safe_move($src, $dst){
	global $cfg;

	$dstdir = dirname($dst);

	if(!file_exists($src)){
		echo "E: source file is not exist: $src\n";
		return false;
	}
	if(!is_dir($dstdir)) {
		mkdir($dstdir, 0775, true);
		chgrp($dstdir, $cfg['file_group']);
	}

	if(realpath($src)!==FALSE && realpath($src) === realpath($dst)){
		echo "E: not have to move: $src\n";
		return true;
	}

	link($src, "$src.bak");
	echo "Moving '".basename($src)."' as '$dst'\n";
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

function get_season($year, $month) {
	$year = intval($year);
	$month = intval($month);
	$quarter = (int)(($month-1) / 3);
	if($quarter === 0){
		$year--;
		$quarter = 4;
	}
	return sprintf("%dY%dQ", $year, $quarter);
}

main($argv);
