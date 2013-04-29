<?php
$expr = $argv[1];
$haystack = $argv[2];

if(preg_match($expr, $haystack)){
	exit(0);
}else{
	exit(1);
}
