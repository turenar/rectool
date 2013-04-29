#!/bin/bash
FROM=/data/epgrec
TO=/data/encoded


EPGREC_D="$(dirname "$0")"
BASEDIR="$(dirname "$(readlink -f "$0")")"
# for -pre:0 hq
export AVCONV_DATADIR="${BASEDIR}"
# avconv's loglevel
loglevel=${av_loglevel:-error}
#echo "BASEDIR: $BASEDIR"
#echo "OUTPUT: $OUTPUT"

FILENAME="$(basename "$OUTPUT")"
FN_NOSUF="${FILENAME%.*}"

echo "FILENAME: $FILENAME"
#echo "FN_NOSUF: $FN_NOSUF"

cd "${FROM}"

if [ ! -e "${FILENAME}" ]; then
	echo "file is not found. abort."
	exit 1
fi

fullfile="${TO}/full/${FN_NOSUF}-full.mp4"
minifile="${TO}/mini/${FN_NOSUF}-mini.mp4"

if [ ! -v 'av_encskip' ]; then
	tempfile="$(mktemp -d /tmp/jobs.encode.XXXXXXXXXX)"
	if [ $? -ne 0 -o -z "${tempfile}" ]; then
		echo "failed mktemp"
		exit 1
	fi

	# check source.ts
	avconv -i "${FILENAME}" > "${tempfile}/tsstat" 2>&1

	video_map=$(grep 'Video: mpeg2video' < "${tempfile}/tsstat" \
		| sed -e 's/^.\+Stream #0.\([0-9]\+\).\+$/0:\1/' \
		| sort -u )
	audio_map=$(grep 'Audio: aac' < "${tempfile}/tsstat" \
		| grep -P '\d{3} kb/s' \
		| sed -e 's/^.\+Stream #0.\([0-9]\+\).\+$/0:\1/' \
		| sort -u )

	echo "video.map: ${video_map}"
	echo "audio.map: ${audio_map}"


	echo "avconv with full"
	nice -n 19 avconv -i "${OUTPUT}" -loglevel ${loglevel} -y -f mp4 \
		-pre:0 hq -vcodec libx264 -vsync 1 \
		-me_method umh -bufsize 20000k -maxrate 16000k \
		-r 30000/1001 -deinterlace -aspect 16:9 \
		-ab 256k -crf 20 -ss 00:00:01 \
		-map ${video_map} -map ${audio_map} -acodec libfaac \
		"${tempfile}/full.mp4"
	echo "avconv with mini"
	nice -n 19 avconv -i "${OUTPUT}" -loglevel ${loglevel} -y -f mp4 \
		-pre:0 slow_firstpass -vcodec libx264 -vsync 1 -threads 3 \
		-pass 1 -passlogfile ${tempfile}/passlog -qcomp 0.8 -me_method dia \
		-r 30000/1001 -deinterlace -aspect 16:9 -s 640x480 \
		-vb 900k -ab 160k -ss 00:00:01 \
		-map ${video_map} -map ${audio_map} -acodec libfaac \
		/dev/null
	nice -n 19 avconv -i "${OUTPUT}" -loglevel ${loglevel} -y -f mp4 \
		-pre:0 slow -vcodec libx264 -vsync 1 -threads 3 \
		-pass 2 -passlogfile ${tempfile}/passlog -qcomp 0.8 -me_method umh \
		-r 30000/1001 -deinterlace -aspect 16:9 -s 640x480 \
		-vb 900k -ab 160k -ss 00:00:01 \
		-map ${video_map} -map ${audio_map} -acodec libfaac \
		"${tempfile}/mini.mp4"
	qt-faststart "${tempfile}/full.mp4" \
		"${fullfile}"
	qt-faststart "${tempfile}/mini.mp4" \
		"${minifile}"
	echo "finish! cleaning up..."
	rm ${tempfile}/*
	rmdir ${tempfile}
	echo "Wait for mediatomb importing..."
	sleep 1
fi

if [ -e "${fullfile}" ]; then
	until php "${EPGREC_D}/mediatomb-update.php" "${FROM}/${FILENAME}" "${fullfile}"; do
		echo -n 'Wait..'
		sleep 1
	done
fi
if [ -e "${minifile}" ]; then
	until php "${EPGREC_D}/mediatomb-update.php" "${FROM}/${FILENAME}" "${minifile}" " (low)"; do
		echo -n 'Wait..'
		sleep 1
	done
fi

cd "${TO}/full"
"${BASEDIR}/update.sh" "${FN_NOSUF}-full.mp4"

if [ -e */"${FN_NOSUF}-full.mp4" ]; then
	_filename=*/"${FN_NOSUF}-full.mp4"
	php "${EPGREC_D}/epgrec-update.php" "${FILENAME}" "mp4/${_filename}"
	[ -e "${EPGREC_D}/thumbs/${FILENAME}.jpg" ] && mv "${EPGREC_D}/thumbs/${FILENAME}.jpg" "${EPGREC_D}/thumbs/${FN_NOSUF}-full.mp4.jpg"
elif [ -e "${FN_NOSUF}-full.mp4" ]; then
	php "${EPGREC_D}/epgrec-update.php" "${FILENAME}" "mp4/${FN_NOSUF}-full.mp4"
	[ -e "${EPGREC_D}/thumbs/${FILENAME}.jpg" ] && mv "${EPGREC_D}/thumbs/${FILENAME}.jpg" "${EPGREC_D}/thumbs/${FN_NOSUF}-full.mp4.jpg"
fi

cd "${TO}/mini"
"${BASEDIR}/update.sh" "${FN_NOSUF}-mini.mp4"

#rm -f ${FILENAME} ${tempfile}
