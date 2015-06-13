<?php

$cfg = array();
$cfg['user'] = 'ture7';
$cfg['path_regex'] = "/^(?P<extra>(?:nocm-)?)(?P<channel>.+)_(?P<date>\\d+)00_(?P<title>.+)\\.(?P<extension>[^\\.]+)$/";
$cfg['new_path'] = "%Season%/%Title%/%Extra%%Channel%_%Title%#%Count%_%SubTitle%.%ext%";
$cfg['date_format'] = "YmdHi";
$cfg['file_group'] = "mediaprov";

{
$data = file_get_contents($script_path."/syobocal_replace.json");
$cfg['replace'] = json_decode($data, true);
}

date_default_timezone_set('Asia/Tokyo');
