<?php
define("EPGRDIR", "/var/www/localhost/htdocs/epgrec-una/");

function test_re($expr, $haystack){
	return !!preg_match("@$expr@u", "$haystack");
}

function move_into($src, $dst){
	$dstfile="$dst/".basename($src);

	is_dir($dst) || mkdir($dst);

	echo "Moving ".basename($src)." into $dst\n";
	if(file_exists($dstfile)){
		echo "Replacing...\n";
		unlink($dstfile);
	}
	link($src, $dstfile);
	if(!file_exists($dstfile)){
		echo("${dstfile} is not created! Abort\n");
		exit(1);
	}
	sleep(1);
	system("php '".EPGRDIR."/mediatomb-update.php' '".realpath($src)."' '".realpath($dstfile)."'", $retval);
	while($retval) {
		echo "Wait...";
		sleep(1);
		system("php '".EPGRDIR."/mediatomb-update.php' '".realpath($src)."' '".realpath($dstfile)."'", $retval);
	}
	unlink($src);
	exit(0);
}

function copy_if($regex, $dst){
	global $src;
	if(empty($regex) || empty($dst)){
		echo "[copy_if] Missing operand.\n";
		return true;
	}

	test_re($regex, $src) && move_into($src, $dst);
}


$src = $argv[1];
if(!file_exists($src)){
	echo "Not found.\n";
	exit(1);
}

# 2013/10-
copy_if("境界の彼方", "2013Y3Q_境界の彼方");
copy_if("メガネブ", "2013Y3Q_メガネブ");
copy_if("ファイヤーレオン", "2013Y3Q_ファイヤーレオン");
copy_if("リトルバスターズ.[RＲ][eｅ]", "2013Y3Q_リトルバスターズRefrain");
copy_if("俺の脳内選択肢が", "2013Y3Q_俺の脳内選択肢が、学園ラブコメを全力で邪魔している");
copy_if("キルラキル", "2013Y3Q_キルラキル");
copy_if("アウトブレイク.カンパニー", "2013Y3Q_アウトブレイク・カンパニー");
copy_if("インフィニットストラトス[２2]", "2013Y3Q_インフィニットストラトス2");
copy_if("京騒戯画", "2013Y3Q_京騒戯画");
copy_if("フリージング.*ヴァイブレーション", "2013Y3Q_フリージングヴァイブレーション");

# 2013/7-
copy_if("ロウきゅーぶ！[ＳS]{2}", "2013Y2Q_ロウきゅーぶ！ＳＳ");
copy_if("プリズマ.イリヤ", "2013Y2Q_Fate_kaleid_liner_プリズマ_イリヤ");
copy_if("犬とハサミは使いよう", "2013Y2Q_犬とハサミは使いよう");
copy_if("サーバント×サービス", "2013Y2Q_サーバント×サービス");
copy_if("戦姫絶唱シンフォギア[ＧG]", "2013Y2Q_戦姫絶唱シンフォギアＧ");
copy_if("たまゆら[　 ]〜もあぐれっしぶ〜", "2013Y2Q_たまゆら　〜もあぐれっしぶ〜");
copy_if("ダンガンロンパ", "2013Y2Q_ダンガンロンパ");
copy_if("恋愛ラボ", "2013Y2Q_恋愛ラボ");
copy_if("物語.シリーズ.*セカンドシーズン", "2013Y2Q_物語シリーズ　セカンドシーズン");
copy_if("Free！", "2013Y2Q_Free！");
copy_if("ふたりはミルキィホームズ", "2013Y2Q_ふたりはミルキィホームズ");
copy_if("げんしけん", "2013Y2Q_げんしけん");
copy_if("魔界王子", "2013Y2Q_魔界王子");
copy_if("有頂天家族", "2013Y2Q_有頂天家族");
copy_if("きんいろモザイク", "2013Y2Q_きんいろモザイク");
copy_if("私がモテないのはどう考えてもお前らが悪い", "2013Y2Q_私がモテないのはどう考えてもお前らが悪い！");
copy_if("神さまのいない日曜日", "2013Y2Q_神さまのいない日曜日");
copy_if("ハイスクール.+(ＮＥＷ|NEW)", "2013Y2Q_ハイスクールDxD_NEW");
copy_if("ブラッドラッド", "2013Y2Q_ブラッドラッド");
copy_if("幻影ヲ駆ケル太陽", "2013Y2Q_幻影ヲ駆ケル太陽");
copy_if("神のみぞ知るセカイ[　 ]女神篇", "2013Y2Q_神のみぞ知るセカイ　女神篇");
copy_if("君のいる町", "2013Y2Q_君のいる町");
copy_if("ステラ女学院高等科", "2013Y2Q_ステラ女学院高等科C3部");
copy_if("ローゼンメイデン", "2013Y2Q_ローゼンメイデン");
copy_if("超次元ゲイム ネプテュ.ヌ", "2013Y2Q_超次元ゲイム ネプテューヌ");

# 2013/4-
copy_if("ＤＥＶＩＬ　ＳＵＲＶＩＶＯＲ", "2013Y1Q_DEVIL_SURVIVOR2");
copy_if("デビルサバイバー", "2013Y1Q_DEVIL_SURVIVOR2");
copy_if("波打際のむろみさん", "2013Y1Q_波打際のむろみさん");
copy_if("やはり俺の青春ラ", "2013Y1Q_やはり俺の青春ラブコメはまちがっている");
copy_if("レッドデータガール", "2013Y1Q_レッドデータガール");
copy_if("這いよれ！ニャル子", "2013Y1Q_這いよれ！ニャル子さんＷ");
copy_if("ゆゆ式", "2013Y1Q_ゆゆ式");
copy_if("アラタカンガタリ", "2013Y1Q_アラタカンガタリ");
copy_if("カーニヴァル", "2013Y1Q_カーニヴァル");
copy_if("クライムエッジ", "2013Y1Q_断裁分離のクライムエッジ");
copy_if("はたらく魔王さま", "2013Y1Q_はたらく魔王さま");
copy_if("フォトカノ", "2013Y1Q_フォトカノ");
copy_if("デート.ア.ライブ", "2013Y1Q_デート・ア・ライブ");
copy_if("翠星のガルガンディア", "2013Y1Q_翠星のガルガンディア");
copy_if("変態王子と笑わない猫", "2013Y1Q_変態王子と笑わない猫。");
copy_if("とある科学の超電磁砲[SＳ]", "2013Y1Q_とある科学の超電磁砲S");
copy_if("絶対防衛レヴィアタン", "2013Y1Q_絶対防衛レヴィアタン");
copy_if("翠星のガルガンティア", "2013Y1Q_翠星のガルガンティア");
copy_if("ハヤテのごとく！Ｃｕｔｉｅｓ", "2013Y1Q_ハヤテのごとく！Ｃｕｔｉｅｓ");
copy_if("百花繚乱", "2013Y1Q_百花繚乱サムライブライド");
copy_if("俺の妹がこんなに可愛いわけがない", "2013Y1Q_俺の妹がこんなに可愛いわけがない");
copy_if("マジェスティックプリンス", "2013Y1Q_銀河機攻隊マジェスティックプリンス");
copy_if("鷹の爪団の楽しいテレビ", "2013Y1Q_鷹の爪団の楽しいテレビ");

# 2013/1-
copy_if("中二病でも恋がしたい", "2012Y4Q_中二病でも恋がしたい");
copy_if("リトルバスターズ", "2012Y4Q_リトルバスターズ");
copy_if("ラブライブ", "2012Y4Q_ラブライブ");

# older
copy_if("フリージング", "2010Y4Q_フリージング");
copy_if("あの日見た花の名前を", "2011Y1Q_あの日見た花の名前を僕達はまだ知らない");
copy_if("ハイスクールD×D", "2012Y1Q_ハイスクールDxD");
copy_if("ロウきゅーぶ", "2012Y3Q_ロウきゅーぶ");
copy_if("魔法少女まどか", "2010Y4Q_魔法少女まどか☆マギカ");
copy_if("ペルソナ[4４]", "2011Y3Q_ペルソナ4");




exit(1);