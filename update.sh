#!/bin/bash
BASEDIR="$(dirname $0)"
EPGRDIR=/var/www/localhost/htdocs/epgrec-una/

function test_re(){
	php ${BASEDIR}/regexp.php "/$1/u" "$2"
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

	echo "Moving ${src} into ${dst}"
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

copy_if "$1" "ハイスクールD×D" "2012Y1Q_ハイスクールDxD"
copy_if "$1" "ロウきゅーぶ" "2012Y3Q_ロウきゅーぶ"
copy_if "$1" "中二病でも恋がしたい" "2012Y4Q_中二病でも恋がしたい"
copy_if "$1" "リトルバスターズ" "2012Y4Q_リトルバスターズ"
copy_if "$1" "ＤＥＶＩＬ　ＳＵＲＶＩＶＯＲ" "2013Y1Q_DEVIL_SURVIVOR2"
copy_if "$1" "デビルサバイバー" "2013Y1Q_DEVIL_SURVIVOR2"
copy_if "$1" "波打際のむろみさん" "2013Y1Q_波打際のむろみさん"
copy_if "$1" "やはり俺の青春ラ" "2013Y1Q_やはり俺の青春ラブコメはまちがっている"
copy_if "$1" "レッドデータガール" "2013Y1Q_レッドデータガール"
copy_if "$1" "這いよれ！ニャル子" "2013Y1Q_這いよれ！ニャル子さんＷ"
copy_if "$1" "ゆゆ式" "2013Y1Q_ゆゆ式"
copy_if "$1" "百花繚乱" "2013Y1Q_百花繚乱サムライブレイド"
copy_if "$1" "アラタカンガタリ" "2013Y1Q_アラタカンガタリ"
copy_if "$1" "カーニヴァル" "2013Y1Q_カーニヴァル"
copy_if "$1" "クライムエッジ" "2013Y1Q_断裁分離のクライムエッジ"
copy_if "$1" "はたらく魔王さま" "2013Y1Q_はたらく魔王さま"
copy_if "$1" "フォトカノ" "2013Y1Q_フォトカノ"
copy_if "$1" "デート.ア.ライブ" "2013Y1Q_デート・ア・ライブ"
copy_if "$1" "翠星のガルガンディア" "2013Y1Q_翠星のガルガンディア"
copy_if "$1" "変態王子と笑わない猫" "2013Y1Q_変態王子と笑わない猫。"
copy_if "$1" "とある科学の超電磁砲" "2013Y1Q_とある科学の超電磁砲S"
copy_if "$1" "絶対防衛レヴィアタン" "2013Y1Q_絶対防衛レヴィアタン"


exit 1
