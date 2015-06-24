Functions:
	-queued encode with avconv
	-auto commercials removal with comskip
	-auto rename files

	%% removed drop report function. You may get recomplete.php and
	   check-drop.sh from https://github.com/turenar/epgrec

Requirements:
	libav-10(dev) with libfaac, libx264
	atd
	epgrec/epgrecUNA/asuha
	tsselect (ex. https://github.com/shesee/tsselect-linux)
	php (>5.3)
	comskip (https://github.com/Hiroyuki-Nagata/comskip)

	>1GB free memory
	very large storage
	faster cpu

Install:
	-Copy files into epgrec dir
	-Configure (Update const in do_encode.sh, etc)

Notice:
	-for individual use.

	DO NOT PUBLISH ANY RECORDED FILE YOU DON'T HAVE COPYRIGHT!
	DO NOT PUBLISH ANY RECORDED FILE YOU DON'T HAVE COPYRIGHT!
	DO NOT PUBLISH ANY RECORDED FILE YOU DON'T HAVE COPYRIGHT!

Info:
	comskip.ini is from http://rndomhack.com/2012/12/11/autoconvert/
	comskip_wrapper.sh is originated from https://github.com/Hiroyuki-Nagata/comskip
