#!/usr/bin/env php
<?php
define("EPGRDIR", "/var/www/localhost/htdocs/epgrec-una/");
define("F_GROUP", "mediaprov");

function test_re($expr, $haystack){
	$haystack = Normalizer::normalize($haystack, Normalizer::FORM_KC);
	return !!preg_match("@$expr@u", "$haystack");
}

function move_into($src, $dst){
	$dstfile="$dst/".basename($src);

	if(!is_dir($dst)) {
		mkdir($dst, 0775, true);
		chgrp($dst, F_GROUP);
	}
	
	if(realpath($src)!==FALSE && realpath($src) === realpath($dst)."/".basename($src)){
		echo "E: not have to move: $src\n";
		return true;
	}

	link($src, "$src.bak");
	echo "Moving '".basename($src)."' into '$dst'\n";
	if(file_exists($dstfile)){
		echo "Replacing...\n";
		unlink($dstfile);
	}

	$srcstat = stat($src);
	$dststat = stat($dst);
	if($srcstat['dev'] === $dststat['dev']){
		link($src, $dstfile);
	}else{
		echo "D:cross-dev\n";
		copy($src, $dstfile);
	}

	if(!file_exists($dstfile)){
		echo("${dstfile} is not created! Abort\n");
		if(!file_exists($src)){
			echo("${src} is deleted. recoverying\n");
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

function copy_if($regex, $src, $dst){
	if(empty($regex) || empty($dst)){
		echo "[copy_if] Missing operand.\n";
		return false;
	}
	return test_re($regex, basename($src)) && move_into($src, $dst);
}

function init_tsv(){
	global $tsv_data;
	$fp = fopen(__DIR__ . '/update-filepath.tsv', 'r');
	if($fp === FALSE){
		echo "open failed tsv. abort";
		exit;
	}
	while(!feof($fp)){
		$line = trim(fgets($fp));
		if(empty($line)){
			continue;
		}
		list($season, $name, $regex) = explode("\t", $line);
		$tsv_data[$regex] = "$season/{$season}_$name";
	}
	fclose($fp);
}

function add_tsv(array $arr){
	$season = isset($arr[2]) ? trim($arr[2]) : null;
	$name = isset($arr[3]) ? trim($arr[3]) : null;
	$regex = isset($arr[4]) ? trim($arr[4]) : null;
	$stdin = fopen('php://stdin', 'r');
	do{
		echo "Name [$name] > ";
		$temp = trim(fgets($stdin));
		if(!empty($temp)){
			$name = $temp;
		}
		if(empty($regex)){
			$regex = Normalizer::normalize($name, Normalizer::FORM_KC);
		}

		echo "Regex [$regex] >";
		$temp = trim(fgets($stdin));
		if(!empty($temp)){
			$regex = $temp;
		}

		if(empty($season)){
			$year = strftime("%Y");
			$quarter = (int)(strftime("%m") / 3);
			if($quarter === 0){
				$year--;
				$quarter = 4;
			}
			$season = "{$year}Y{$quarter}Q";
		}
		echo "Season [$season] >";
		$temp = trim(fgets($stdin));
		if(!empty($temp)){
			$season = $temp;
		}

		echo "\tName: $name\n\tRegex: $regex\n\tSeason: $season\n";
		echo "OK? [Y/n]>";
		$temp = trim(fgets($stdin));
		if(!empty($temp)){
			$answer = $temp == "y";
		}else{
			$answer = true;
		}
	}while(!$answer);
	$fp = fopen(__DIR__ . '/update-filepath.tsv', 'a');
	if($fp === FALSE){
		echo "open failed tsv. abort";
		exit;
	}
	fputs($fp, "$season\t$name\t$regex\n");
	fclose($fp);
}

if(!empty($argv[1]) && $argv[1] == '-a'){
	add_tsv($argv);
	exit;
}

init_tsv();

$filelist = array_slice($argv, 1);
foreach($filelist as $src){
	if(!file_exists($src)){
		echo "$src: not found.\n";
		continue;
	}else if(is_dir($src)){
		echo "$src is directory! Abort.\n";
		exit;
	}
	foreach($tsv_data as $regex => $dir){
		if(copy_if($regex, $src, $dir)){
			continue 2;
		}
	}
	echo "$src: unknown name\n";
}

