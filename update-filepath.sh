#!/bin/bash
BASEDIR="$(dirname $0)"
EPGRDIR=/var/www/localhost/htdocs/epgrec-una/

function test_re(){
	php ${BASEDIR}/regexp.php "@$1@u" "$2"
	return $?
}

function die() {
	echo "$1" >&2
	exit 1
}

function move_into(){
	local src dst
	src="$1"
	dst="$2"
	dstfile="${dst}/$(basename "${src}")"

	test -d "${dst}" || mkdir "${dst}"

	echo "Moving $(basename "${src}") into ${dst}"
	test -f "${dstfile}" && echo 'Replacing...' && rm "${dstfile}"
	ln "${src}" "${dstfile}"
	test -f "${dstfile}" || die "${dstfile} is not created! Abort"
	sleep 1
	until php "${EPGRDIR}/mediatomb-update.php" "$(readlink -f "${src}")" "$(readlink -f "${dstfile}")"; do
		echo "Wait..."
		sleep 1
	done
	rm "${src}"
}

function copy_if(){
	local src re dst
	src="$1"
	re="$2"
	dst="$3"
	if [ -z "$1" -o -z "$2" -o -z "$3" ]; then
		echo "[copy_if] Missing operand."
		return 1
	fi

	test_re "$re" "$src" && move_into "$src" "$dst" && exit 0
}

if [ ! -f "$1" ]; then
	echo Not found. 2>&1
	exit 1
fi

# 2013/10-
copy_if "$1" "境界の彼方" "2013Y3Q_境界の彼方"
copy_if "$1" "メガネブ" "2013Y3Q_メガネブ"
copy_if "$1" "ファイヤーレオン" "2013Y3Q_ファイヤーレオン"
copy_if "$1" "リトルバスターズ.[RＲ][eｅ]" "2013Y3Q_リトルバスターズRefrain"
copy_if "$1" "俺の脳内選択肢が" "2013Y3Q_俺の脳内選択肢が、学園ラブコメを全力で邪魔している"
copy_if "$1" "キルラキル" "2013Y3Q_キルラキル"
copy_if "$1" "アウトブレイク.カンパニー" "2013Y3Q_アウトブレイク・カンパニー"
copy_if "$1" "インフィニットストラトス[２2]" "2013Y3Q_インフィニットストラトス2"
copy_if "$1" "京騒戯画" "2013Y3Q_京騒戯画"
copy_if "$1" "フリージング.*ヴァイブレーション" "2013Y3Q_フリージングヴァイブレーション"

# 2013/7-
copy_if "$1" "ロウきゅーぶ！[ＳS]{2}" "2013Y2Q_ロウきゅーぶ！ＳＳ"
copy_if "$1" "プリズマ.イリヤ" "2013Y2Q_Fate_kaleid_liner_プリズマ_イリヤ"
copy_if "$1" "犬とハサミは使いよう" "2013Y2Q_犬とハサミは使いよう"
copy_if "$1" "サーバント×サービス" "2013Y2Q_サーバント×サービス"
copy_if "$1" "戦姫絶唱シンフォギア[ＧG]" "2013Y2Q_戦姫絶唱シンフォギアＧ"
copy_if "$1" "たまゆら[　 ]〜もあぐれっしぶ〜" "2013Y2Q_たまゆら　〜もあぐれっしぶ〜"
copy_if "$1" "ダンガンロンパ" "2013Y2Q_ダンガンロンパ"
copy_if "$1" "恋愛ラボ" "2013Y2Q_恋愛ラボ"
copy_if "$1" "物語.シリーズ.*セカンドシーズン" "2013Y2Q_物語シリーズ　セカンドシーズン"
copy_if "$1" "Free！" "2013Y2Q_Free！"
copy_if "$1" "ふたりはミルキィホームズ" "2013Y2Q_ふたりはミルキィホームズ"
copy_if "$1" "げんしけん" "2013Y2Q_げんしけん"
copy_if "$1" "魔界王子" "2013Y2Q_魔界王子"
copy_if "$1" "有頂天家族" "2013Y2Q_有頂天家族"
copy_if "$1" "きんいろモザイク" "2013Y2Q_きんいろモザイク"
copy_if "$1" "私がモテないのはどう考えてもお前らが悪い" "2013Y2Q_私がモテないのはどう考えてもお前らが悪い！"
copy_if "$1" "神さまのいない日曜日" "2013Y2Q_神さまのいない日曜日"
copy_if "$1" "ハイスクール.+(ＮＥＷ|NEW)" "2013Y2Q_ハイスクールDxD_NEW"
copy_if "$1" "ブラッドラッド" "2013Y2Q_ブラッドラッド"
copy_if "$1" "幻影ヲ駆ケル太陽" "2013Y2Q_幻影ヲ駆ケル太陽"
copy_if "$1" "神のみぞ知るセカイ[　 ]女神篇" "2013Y2Q_神のみぞ知るセカイ　女神篇"
copy_if "$1" "君のいる町" "2013Y2Q_君のいる町"
copy_if "$1" "ステラ女学院高等科" "2013Y2Q_ステラ女学院高等科C3部"
copy_if "$1" "ローゼンメイデン" "2013Y2Q_ローゼンメイデン"
copy_if "$1" "超次元ゲイム ネプテュ.ヌ" "2013Y2Q_超次元ゲイム ネプテューヌ"

# 2013/4-
copy_if "$1" "ＤＥＶＩＬ　ＳＵＲＶＩＶＯＲ" "2013Y1Q_DEVIL_SURVIVOR2"
copy_if "$1" "デビルサバイバー" "2013Y1Q_DEVIL_SURVIVOR2"
copy_if "$1" "波打際のむろみさん" "2013Y1Q_波打際のむろみさん"
copy_if "$1" "やはり俺の青春ラ" "2013Y1Q_やはり俺の青春ラブコメはまちがっている"
copy_if "$1" "レッドデータガール" "2013Y1Q_レッドデータガール"
copy_if "$1" "這いよれ！ニャル子" "2013Y1Q_這いよれ！ニャル子さんＷ"
copy_if "$1" "ゆゆ式" "2013Y1Q_ゆゆ式"
copy_if "$1" "アラタカンガタリ" "2013Y1Q_アラタカンガタリ"
copy_if "$1" "カーニヴァル" "2013Y1Q_カーニヴァル"
copy_if "$1" "クライムエッジ" "2013Y1Q_断裁分離のクライムエッジ"
copy_if "$1" "はたらく魔王さま" "2013Y1Q_はたらく魔王さま"
copy_if "$1" "フォトカノ" "2013Y1Q_フォトカノ"
copy_if "$1" "デート.ア.ライブ" "2013Y1Q_デート・ア・ライブ"
copy_if "$1" "翠星のガルガンディア" "2013Y1Q_翠星のガルガンディア"
copy_if "$1" "変態王子と笑わない猫" "2013Y1Q_変態王子と笑わない猫。"
copy_if "$1" "とある科学の超電磁砲[SＳ]" "2013Y1Q_とある科学の超電磁砲S"
copy_if "$1" "絶対防衛レヴィアタン" "2013Y1Q_絶対防衛レヴィアタン"
copy_if "$1" "翠星のガルガンティア" "2013Y1Q_翠星のガルガンティア"
copy_if "$1" "ハヤテのごとく！Ｃｕｔｉｅｓ" "2013Y1Q_ハヤテのごとく！Ｃｕｔｉｅｓ"
copy_if "$1" "百花繚乱" "2013Y1Q_百花繚乱サムライブライド"
copy_if "$1" "俺の妹がこんなに可愛いわけがない" "2013Y1Q_俺の妹がこんなに可愛いわけがない"
copy_if "$1" "マジェスティックプリンス" "2013Y1Q_銀河機攻隊マジェスティックプリンス"
copy_if "$1" "鷹の爪団の楽しいテレビ" "2013Y1Q_鷹の爪団の楽しいテレビ"

# 2013/1-
copy_if "$1" "中二病でも恋がしたい" "2012Y4Q_中二病でも恋がしたい"
copy_if "$1" "リトルバスターズ" "2012Y4Q_リトルバスターズ"
copy_if "$1" "ラブライブ" "2012Y4Q_ラブライブ"

# older
copy_if "$1" "フリージング" "2010Y4Q_フリージング"
copy_if "$1" "あの日見た花の名前を" "2011Y1Q_あの日見た花の名前を僕達はまだ知らない"
copy_if "$1" "ハイスクールD×D" "2012Y1Q_ハイスクールDxD"
copy_if "$1" "ロウきゅーぶ" "2012Y3Q_ロウきゅーぶ"
copy_if "$1" "魔法少女まどか" "2010Y4Q_魔法少女まどか☆マギカ"
copy_if "$1" "ペルソナ[4４]" "2011Y3Q_ペルソナ4"




exit 1
