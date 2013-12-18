#!/bin/bash
FROM=/data/epgrec
TO=/data/encoded
ARCHIVE=/data/archives.ts
export DISPLAY=:0

renice -n 19 $$
ionice -c 3 -p $$

if true; then # avoid syntax error with editting

EPGREC_D="$(dirname "$0")"
BASEDIR="$(dirname "$(readlink -f "$0")")"
# for -pre:0 hq
export AVCONV_DATADIR="${BASEDIR}"
# avconv's loglevel
loglevel=${av_loglevel:-error}

FNFULLPATH="$(readlink -f "${OUTPUT}")"
FILENAME="$(basename "$OUTPUT")"
FN_NOSUF="${FILENAME%.*}"

echo "FILENAME: $FILENAME"
#echo "FN_NOSUF: $FN_NOSUF"

cd "$(dirname "$OUTPUT")"

if [ ! -e "${FILENAME}" ]; then
	echo "file is not found. abort."
	exit 1
fi

fullfile="${TO}/full/${FN_NOSUF}-full.mp4"
#minifile="${TO}/mini/${FN_NOSUF}-mini.mp4"

if [ ! -v 'av_encskip' ]; then
	exec 9>>/run/encode.lock
	flock -n 9
	if [ $? -ne 0 ]; then
		echo 'Waiting for exclusive lock...'
		flock 9
	fi

	tempfile="$(mktemp -d /tmp/jobs.encode.XXXXXXXXXX)"
	if [ $? -ne 0 -o -z "${tempfile}" ]; then
		echo "failed mktemp"
		exit 1
	fi
	av_encfile="${OUTPUT}"

	# check source.ts
	avconv -i "${OUTPUT}" > "${tempfile}/tsstat" 2>&1
	
	if [ ! -v "av_map_skip" ]; then
	video_map="$(grep 'Video: mpeg2video' < "${tempfile}/tsstat" \
		| sed -e 's/^.\+Stream #0.\([0-9]\+\).\+$/-map 0:\1/' \
		| sort -u | tr '\n' ' ')"
	audio_map="$(grep 'Audio: aac' < "${tempfile}/tsstat" \
		| grep -P '\d{3} kb/s' \
		| sed -e 's/^.\+Stream #0.\([0-9]\+\).\+$/-map 0:\1/' \
		| sort -u | tr '\n' ' ')"
	fi

	echo "video.map: ${video_map}"
	echo "audio.map: ${audio_map}"

	echo "avconv with full"
	dd if="${OUTPUT}" ibs=512M obs=64k | avconv -i pipe: -loglevel ${loglevel} -y -f mp4 \
		-pre:v hq -vcodec libx264 -vsync 1 \
		-acodec libfaac -bufsize 20000k -maxrate 16000k \
		-r 30000/1001 -filter:v yadif -aspect 16:9 \
		-crf 20 -ss 00:00:01 \
		${video_map} ${audio_map} \
		"${tempfile}/full.mp4" || exit 1
	#echo "avconv with mini"
	#nice -n 19 avconv -i "${av_encfile}" -loglevel ${loglevel} -y -f mp4 \
	#	-pre:0 slow_firstpass -vcodec libx264 -vsync 1 \
	#	-pass 1 -passlogfile ${tempfile}/passlog -qcomp 0.8 \
	#	-r 30000/1001 -filter:v yadif -aspect 16:9 -s 640x480 \
	#	-vb 750k -an -ss 00:00:01 \
	#	${video_map} \
	#	/dev/null || exit 1
	#nice -n 19 avconv -i "${av_encfile}" -loglevel ${loglevel} -y -f mp4 \
	#	-pre:0 slow -vcodec libx264 -vsync 1 \
	#	-pass 2 -passlogfile ${tempfile}/passlog -qcomp 0.8 \
	#	-r 30000/1001 -filter:v yadif -aspect 16:9 -s 640x480 \
	#	-vb 750k -ab 160k -ss 00:00:01 -mixed-refs 0 \
	#	${video_map} ${audio_map} -acodec libfaac \
	#	"${tempfile}/mini.mp4" || exit 1
	qt-faststart "${tempfile}/full.mp4" \
		"${fullfile}"
	#qt-faststart "${tempfile}/mini.mp4" \
	#	"${minifile}"
	echo "finish! cleaning up..."
	rm ${tempfile}/*
	rmdir ${tempfile}
	#echo "Wait for mediatomb importing..."
	#sleep 5
fi

#if [ -e "${fullfile}" ]; then
#	php "${EPGREC_D}/mediatomb-update.php" "${FNFULLPATH}" "${fullfile}"
#fi
#if [ -e "${minifile}" ]; then
#	until php "${EPGREC_D}/mediatomb-update.php" "${FNFULLPATH}" "${minifile}" ' (low)'; do
#		echo -n 'Wait..'
#		sleep 1
#	done
#fi

cd "${TO}/full"
php "${EPGREC_D}/update-filepath.php" "${FN_NOSUF}-full.mp4"

if [ -e "${FN_NOSUF}-full.mp4" ]; then
	php "${EPGREC_D}/epgrec-update.php" "${FILENAME}" "mp4/${FN_NOSUF}-full.mp4"
	[ -e "${EPGREC_D}/thumbs/${FILENAME}.jpg" ] && mv "${EPGREC_D}/thumbs/${FILENAME}.jpg" "${EPGREC_D}/thumbs/${FN_NOSUF}-full.mp4.jpg"
	echo "${TO}/${FN_NOSUF}" > "${TO}/mini/${FN_NOSUF}-mini.enc"
elif [ -e */"${FN_NOSUF}-full.mp4" ]; then
	_filename=*/"${FN_NOSUF}-full.mp4"
	echo "${TO}/${_filename}" > "${TO}/mini/${FN_NOSUF}-mini.enc"
	php "${EPGREC_D}/epgrec-update.php" "${FILENAME}" "mp4/${_filename}"
	[ -e "${EPGREC_D}/thumbs/${FILENAME}.jpg" ] && mv "${EPGREC_D}/thumbs/${FILENAME}.jpg" "${EPGREC_D}/thumbs/${FN_NOSUF}-full.mp4.jpg"
fi

cd "${TO}/mini"
php "${EPGREC_D}/update-filepath.php" "${FN_NOSUF}-mini.enc"

if [ ! -v 'av_encskip' -a -e "${FROM}/${FILENAME}" ]; then
	echo 'Moving file into archives'
	cd "${FROM}"
	ln "${FILENAME}" "${ARCHIVE}/${FILENAME}"
	#sleep 1
	#php "${EPGREC_D}/mediatomb-update.php" "${FNFULLPATH}" "${ARCHIVE}/${FILENAME}"
	rm "${FILENAME}"
fi

#rm -f ${FILENAME} ${tempfile}

fi # avoid size differing
#########################
