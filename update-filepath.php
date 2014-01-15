<?php
define("EPGRDIR", "/var/www/localhost/htdocs/epgrec-una/");
define("F_GROUP", "mediaprov");

function test_re($expr, $haystack){
	return !!preg_match("@$expr@u", "$haystack");
}

function move_into($src, $dst){
	$dstfile="$dst/".basename($src);

	if(!is_dir($dst)) {
		mkdir($dst, 0775, true);
		chgrp($dst, F_GROUP);
	}
	
	if(realpath($src)!==FALSE && realpath($src) === realpath($dst)."/".basename($src)){
		echo "E: not have to move\n";
		exit(0);
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
		exit(1);
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
}else if(is_dir($src)){
	echo "Target is directory! Abort.\n";
	exit(1);
}

# 2014/1-
copy_if("コッペリオン", "2013Y4Q/2014Y4Q_コッペリオン");
copy_if("銀の匙", "2013Y4Q/2013Y4Q_銀の匙_silver_spoon");
copy_if("咲.(Ｓａｋｉ|Saki).全国編", "2013Y4Q/2013Y4Q_咲-Saki-全国編");
copy_if("バディコンプレックス", "2013Y4Q/2013Y4Q_バディコンプレックス");
copy_if("そにアニ", "2013Y4Q/2013Y4Q_そにアニ");
copy_if("ノブナガ・ザ・フール", "2013Y4Q/2013Y4Q_ノブナガ・ザ・フール");
copy_if("ウィッチクラフトワークス", "2013Y4Q/2013Y4Q_ウィッチクラフトワークス");
copy_if("ディーふらぐ！", "2013Y4Q/2013Y4Q_ディーふらぐ！");
copy_if("生徒会役員共[*＊2２]", "2013Y4Q/2013Y4Q_生徒会役員共＊");
copy_if("ハマトラ", "2013Y4Q/2013Y4Q_ハマトラ");
copy_if("スペース.ダンディ", "2013Y4Q/2013Y4Q_スペース_ダンディ");
copy_if("Ｚ／Ｘ　ＩＧＮＩＴＩＯＮ", "2013Y4Q/2013Y4Q_Z／X_IGNITION");
copy_if("Ｗａｋｅ　Ｕｐ，Ｇｉｒｌｓ！", "2013Y4Q/2013Y4Q_Wake_Up,_Girls");
copy_if("ニセコイ", "2013Y4Q/2013Y4Q_ニセコイ");
copy_if("のうりん", "2013Y4Q/2013Y4Q_のうりん");
copy_if("とある飛空士への恋歌", "2013Y4Q/2013Y4Q_とある飛空士への恋歌");
copy_if("鬼灯の冷徹", "2013Y4Q/2013Y4Q_鬼灯の冷徹");
copy_if("中二病でも恋がしたい！戀", "2013Y4Q/2013Y4Q_中二病でも恋がしたい！戀");
# 2013/10-
copy_if("境界の彼方", "2013Y3Q/2013Y3Q_境界の彼方");
copy_if("メガネブ", "2013Y3Q/2013Y3Q_メガネブ");
copy_if("ファイヤーレオン", "2013Y3Q/2013Y3Q_ファイヤーレオン");
copy_if("リトルバスターズ.*[RＲ][eｅ]", "2013Y3Q/2013Y3Q_リトルバスターズRefrain");
copy_if("俺の脳内選択肢が", "2013Y3Q/2013Y3Q_俺の脳内選択肢が、学園ラブコメを全力で邪魔している");
copy_if("キルラキル", "2013Y3Q/2013Y3Q_キルラキル");
copy_if("アウトブレイク.カンパニー", "2013Y3Q/2013Y3Q_アウトブレイク・カンパニー");
copy_if("インフィニットストラトス[２2]", "2013Y3Q/2013Y3Q_インフィニットストラトス2");
copy_if("京騒戯画", "2013Y3Q/2013Y3Q_京騒戯画");
copy_if("フリージング.*ヴァイブレーション", "2013Y3Q/2013Y3Q_フリージングヴァイブレーション");
copy_if("のんのんびより", "2013Y3Q/2013Y3Q_のんのんびより");
copy_if("機巧少女は傷つかない", "2013Y3Q/2013Y3Q_機巧少女は傷つかない");
copy_if("ワルキューレロマンツェ", "2013Y3Q/2013Y3Q_ワルキューレロマンツェ");
copy_if("弱虫ペダル", "2013Y3Q/2013Y3Q_弱虫ペダル");
copy_if("ぎんぎつね", "2013Y3Q/2013Y3Q_ぎんぎつね");
copy_if("夜桜四重奏.*ハナノウタ", "2013Y3Q/2013Y3Q_夜桜四重奏～ハナノウタ～");
copy_if("(ＷＨＩＴＥ.ＡＬＢＵＭ２|WHITE.ALBUM2)", "2013Y3Q/2013Y3Q_WHITE_ALBUM2");
copy_if("凪のあすから", "2013Y3Q/2013Y3Q_凪のあすから");
copy_if("ＢＬＡＺ.?ＢＬＵＥ.?ＡＬＴＥＲ.?ＭＥＭＯＲＹ", "2013Y3Q/2013Y3Q_BLAZ_BLUE_ALTER_MEMORY");
copy_if("勇者になれなかった俺はしぶしぶ", "2013Y3Q/2013Y3Q_勇者になれなかった俺はしぶしぶ就職を決意しました。");
copy_if("東京レイヴンズ", "2013Y3Q/2013Y3Q_東京レイヴンズ");
copy_if("蒼き鋼のアルペジオ", "2013Y3Q/2013Y3Q_蒼き鋼のアルペジオ");
copy_if("ストライク・ザ・ブラッド", "2013Y3Q/2013Y3Q_ストライク・ザ・ブラッド");
copy_if("ゴールデンタイム", "2013Y3Q/2013Y3Q_ゴールデンタイム");
copy_if("インフィニット・ストラトス２", "2013Y3Q/2013Y3Q_インフィニット・ストラトス２");

# 2013/7-
copy_if("ロウきゅーぶ！[ＳS]{2}", "2013Y2Q/2013Y2Q_ロウきゅーぶ！ＳＳ");
copy_if("プリズマ.イリヤ", "2013Y2Q/2013Y2Q_Fate_kaleid_liner_プリズマ_イリヤ");
copy_if("犬とハサミは使いよう", "2013Y2Q/2013Y2Q_犬とハサミは使いよう");
copy_if("サーバント×サービス", "2013Y2Q/2013Y2Q_サーバント×サービス");
copy_if("戦姫絶唱シンフォギア[ＧG]", "2013Y2Q/2013Y2Q_戦姫絶唱シンフォギアＧ");
copy_if("たまゆら[　 ]〜もあぐれっしぶ〜", "2013Y2Q/2013Y2Q_たまゆら　〜もあぐれっしぶ〜");
copy_if("ダンガンロンパ", "2013Y2Q/2013Y2Q_ダンガンロンパ");
copy_if("恋愛ラボ", "2013Y2Q/2013Y2Q_恋愛ラボ");
copy_if("物語.シリーズ.*セカンドシーズン", "2013Y2Q/2013Y2Q_物語シリーズ　セカンドシーズン");
copy_if("Free！", "2013Y2Q/2013Y2Q_Free！");
copy_if("ふたりはミルキィホームズ", "2013Y2Q/2013Y2Q_ふたりはミルキィホームズ");
copy_if("げんしけん", "2013Y2Q/2013Y2Q_げんしけん");
copy_if("魔界王子", "2013Y2Q/2013Y2Q_魔界王子");
copy_if("有頂天家族", "2013Y2Q/2013Y2Q_有頂天家族");
copy_if("きんいろモザイク", "2013Y2Q/2013Y2Q_きんいろモザイク");
copy_if("私がモテないのはどう考えてもお前らが悪い", "2013Y2Q/2013Y2Q_私がモテないのはどう考えてもお前らが悪い！");
copy_if("神さまのいない日曜日", "2013Y2Q/2013Y2Q_神さまのいない日曜日");
copy_if("ハイスクール.+(ＮＥＷ|NEW)", "2013Y2Q/2013Y2Q_ハイスクールDxD_NEW");
copy_if("ブラッドラッド", "2013Y2Q/2013Y2Q_ブラッドラッド");
copy_if("幻影ヲ駆ケル太陽", "2013Y2Q/2013Y2Q_幻影ヲ駆ケル太陽");
copy_if("神のみぞ知るセカイ[　 ]女神篇", "2013Y2Q/2013Y2Q_神のみぞ知るセカイ　女神篇");
copy_if("君のいる町", "2013Y2Q/2013Y2Q_君のいる町");
copy_if("ステラ女学院高等科", "2013Y2Q/2013Y2Q_ステラ女学院高等科C3部");
copy_if("ローゼンメイデン", "2013Y2Q/2013Y2Q_ローゼンメイデン");
copy_if("超次元ゲイム ネプテュ.ヌ", "2013Y2Q/2013Y2Q_超次元ゲイム ネプテューヌ");
copy_if("劇場版「空の境界」", "2013Y2Q/2013Y2Q_劇場版「空の境界」");
copy_if("ファンタジスタドール", "2013Y2Q/2013Y2Q_ファンタジスタドール");

# 2013/4-
copy_if("ＤＥＶＩＬ　ＳＵＲＶＩＶＯＲ", "2013Y1Q/2013Y1Q_DEVIL_SURVIVOR2");
copy_if("デビルサバイバー", "2013Y1Q/2013Y1Q_DEVIL_SURVIVOR2");
copy_if("波打際のむろみさん", "2013Y1Q/2013Y1Q_波打際のむろみさん");
copy_if("やはり俺の青春ラ", "2013Y1Q/2013Y1Q_やはり俺の青春ラブコメはまちがっている");
copy_if("レッドデータガール", "2013Y1Q/2013Y1Q_レッドデータガール");
copy_if("這いよれ！ニャル子", "2013Y1Q/2013Y1Q_這いよれ！ニャル子さんＷ");
copy_if("ゆゆ式", "2013Y1Q/2013Y1Q_ゆゆ式");
copy_if("アラタカンガタリ", "2013Y1Q/2013Y1Q_アラタカンガタリ");
copy_if("カーニヴァル", "2013Y1Q/2013Y1Q_カーニヴァル");
copy_if("クライムエッジ", "2013Y1Q/2013Y1Q_断裁分離のクライムエッジ");
copy_if("はたらく魔王さま", "2013Y1Q/2013Y1Q_はたらく魔王さま");
copy_if("フォトカノ", "2013Y1Q/2013Y1Q_フォトカノ");
copy_if("デート.ア.ライブ", "2013Y1Q/2013Y1Q_デート・ア・ライブ");
copy_if("翠星のガルガンディア", "2013Y1Q/2013Y1Q_翠星のガルガンディア");
copy_if("変態王子と笑わない猫", "2013Y1Q/2013Y1Q_変態王子と笑わない猫。");
copy_if("とある科学の超電磁砲[SＳ]", "2013Y1Q/2013Y1Q_とある科学の超電磁砲S");
copy_if("絶対防衛レヴィアタン", "2013Y1Q/2013Y1Q_絶対防衛レヴィアタン");
copy_if("翠星のガルガンティア", "2013Y1Q/2013Y1Q_翠星のガルガンティア");
copy_if("ハヤテのごとく！Ｃｕｔｉｅｓ", "2013Y1Q/2013Y1Q_ハヤテのごとく！Ｃｕｔｉｅｓ");
copy_if("百花繚乱", "2013Y1Q/2013Y1Q_百花繚乱サムライブライド");
copy_if("俺の妹がこんなに可愛いわけがない", "2013Y1Q/2013Y1Q_俺の妹がこんなに可愛いわけがない");
copy_if("マジェスティックプリンス", "2013Y1Q/2013Y1Q_銀河機攻隊マジェスティックプリンス");
copy_if("鷹の爪団の楽しいテレビ", "2013Y1Q/2013Y1Q_鷹の爪団の楽しいテレビ");
copy_if("革命機ヴァルヴレイヴ", "2013Y1Q/2013Y1Q_革命機ヴァルヴレイヴ");

# 2013/1-
copy_if("中二病でも恋がしたい", "2012Y4Q/2012Y4Q_中二病でも恋がしたい");
copy_if("リトルバスターズ", "2012Y4Q/2012Y4Q_リトルバスターズ");
copy_if("ラブライブ", "2012Y4Q/2012Y4Q_ラブライブ");

# older
copy_if("フリージング", "2010Y4Q/2010Y4Q_フリージング");
copy_if("あの日見た花の名前を", "2011Y1Q/2011Y1Q_あの日見た花の名前を僕達はまだ知らない");
copy_if("ハイスクールD×D", "2012Y1Q/2012Y1Q_ハイスクールDxD");
copy_if("ロウきゅーぶ", "2012Y3Q/2012Y3Q_ロウきゅーぶ");
copy_if("ソードアート・オンライン", "2012Y2Q/2012Y2Q_ソードアート・オンライン");
copy_if("アイドルマスター", "2011Y2Q/2011Y2Q_アイドルマスター");
copy_if("魔法少女まどか", "2010Y4Q/2010Y4Q_魔法少女まどか☆マギカ");
copy_if("ペルソナ[4４]", "2011Y3Q/2011Y3Q_ペルソナ4");
copy_if("マクロス[ＦF]", "2008Y1Q/2008Y1Q_マクロスF");
copy_if("黒子のバスケ", "garb/黒子のバスケ");
copy_if("Ｅテレ２３５５", "garb/Ｅテレ２３５５");
copy_if("アニメ　ガッ活！", "garb/アニメ　ガッ活！");
copy_if("キャラディのジョークな毎日", "garb/キャラディのジョークな毎日");
copy_if("Ｐａｒａサイト劇場", "garb/Ｐａｒａサイト劇場");
copy_if("アニメ　キングダム", "garb/アニメ　キングダム");
copy_if("かよえ！チュー学", "garb/かよえ！チュー学");
copy_if("義風堂々！！兼続と慶次", "garb/義風堂々！！兼続と慶次");
copy_if("がんばれ！おでんくん", "garb/がんばれ！おでんくん");
copy_if("衝撃ゴウライガン", "garb/衝撃ゴウライガン");
copy_if("カラダはみんな生きている", "garb/カラダはみんな生きている");
copy_if("ＨＵＮＴＥＲ.ＨＵＮＴＥＲ", "garb/ＨＵＮＴＥＲ_x_ＨＵＮＴＥＲ");



copy_if("TeSTTeST", "ts/TestTest");
exit(1);
