<?php

$cfg = array();
$cfg['user'] = 'ture7';
$cfg['path_regex'] = "/^(?P<extra>(?:nocm-)?)(?P<channel>.+)_(?P<date>\\d+)00_(?P<title>.+)\\.(?P<extension>[^\\.]+)$/";
$cfg['new_path'] = "%Season%/%Title%/%Extra%%Channel%_%Date%00_%Title%_#%Count%_%SubTitle%.%ext%";
$cfg['date_format'] = "YmdHi";
$cfg['file_group'] = "mediaprov";
$cfg['media_path'] = array(
	'/data/epgrec' => $script_path.'/video',
	'/data/encoded/full' => $script_path.'/video/mp4'
);

{
$cfg['replace'] = json_decode(file_get_contents($script_path . "/syobocal_replace.json"), true);
$cfg['channel'] = json_decode(file_get_contents($script_path . '/syobocal_channel.json'), true);
}

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');
