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

More Description:
	syobocal_rename.php:
		しょぼいカレンダーから情報を持ってきてファイル名を変えるやつ。
		機能としては、
			+ データキャッシュ
			+ 複数ファイル同時リネーム対応
			+ epgrecとの連携
			+ 他プログラムへのフォールバック
			+ インタラクティブなチャンネル設定
			+ 正規表現によるパス切り出し
		https://github.com/henry0312/SyoboiRenamer にインスパイアされて書いたけど、
		処理の内容はほとんど一致してないので……
		動作に必要なのは syobocal* です。epgrecと連携させるならばepgrecの
		config.php が syobocal_rename.php の親ディレクトリ内に存在
		(ハードリンク不可) しているのが条件です。
	update-filepath.php:
		正規表現を駆使してリネームできるやつ。syobocal_rename.phpと組み合わせると
		たぶんかなり強くなれるんじゃないかな。
