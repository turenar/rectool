#!/bin/bash
FROM=/data/epgrec
TO=/data/encoded
ARCHIVE=/data/archives.ts
BUFFER_SIZE=512M
#6GB
LARGE_FILE_SIZE=$((6 * 1024 * 1024 * 1024))
TMPDIR_DEFAULT=/tmp
TMPDIR_LARGEFILE=/data/tmp
#skip_cmcut=y
FAIL_FILESIZE_RATIO="10 / 100"
THROTTLE_SPEED=${enc_throttle-1024}
NOT_FOUND_RETCODE=${enc_nofile_ret-0}

export DISPLAY=:0

ulimit -m $((4 * 1024 * 1024)) -v $((4 * 1024 * 1024))
renice -n 19 $$
ionice -c 3 -p $$

if true; then # avoid syntax error with editting
test -z "${OUTPUT}" && OUTPUT="$1"

EPGREC_D="$(dirname "$0")"
BASEDIR="$(dirname "$(readlink -f "$0")")"
# for -pre:0 hq
export AVCONV_DATADIR="${BASEDIR}"
# avconv's loglevel
loglevel=${av_loglevel:-error}
# ssh's host
SSH_HOST=${enc_ssh:-localhost}
CRFactor=${av_crf:-20}

FNFULLPATH="$(readlink -f "${OUTPUT}")"
FILENAME="$(basename "$OUTPUT")"
FN_NOSUF="${FILENAME%.*}"

#echo "FILENAME: $FILENAME"
#echo "FN_NOSUF: $FN_NOSUF"

cd "$(dirname "$OUTPUT")"
OUT_FULL="$(readlink -f "${OUTPUT}")"
echo "OUT_FULL: $OUT_FULL"

if [ ! -e "${FILENAME}" ]; then
	echo "file is not found. abort."
	exit ${NOT_FOUND_RETCODE}
fi

fullfile="${TO}/full/${FN_NOSUF}.mp4"
nocmfile="${TO}/full/nocm-${FN_NOSUF}.mp4"

THROTTLE_CMD=cat
if [ "x${SSH_HOST}" \!= xlocalhost ]; then
	SSH_CMD="ssh ${SSH_HOST} -- nice -n19 ionice -c 3"
	SSH_EXEC="${SSH_CMD}"
	SCP_CMD="scp -l ${THROTTLE_SPEED}"
	if type throttle >/dev/null 2>&1; then
		THROTTLE_CMD="throttle -k ${THROTTLE_SPEED}"
		echo "Throttle speed: ${THROTTLE_SPEED}"
	else
		echo "Throttle disabled"
	fi
	OUTBUF_SIZE=64M
else
	SSH_CMD=":"
	SSH_EXEC=""
	SCP_CMD=":"
	OUTBUF_SIZE=64k
fi
#check file size
if [ $(stat -c %s "${FILENAME}") -gt ${LARGE_FILE_SIZE} ]; then
	echo "Large file!"
	TMPDIR=${TMPDIR_LARGEFILE}
else
	TMPDIR=${TMPDIR_DEFAULT}
fi

if [ ! -v 'av_encskip' ]; then
	test -d /run/epgrec || mkdir /run/epgrec
	exec 9>>/run/epgrec/encode-${SSH_HOST}.lock
	flock -n 9
	if [ $? -ne 0 ]; then
		echo 'Waiting for exclusive lock...'
		flock 9
	fi

	tempfile="$(mktemp -d ${TMPDIR}/jobs.encode.XXXXXXXXXX)"
	if [ $? -ne 0 -o -z "${tempfile}" ]; then
		echo "failed mktemp"
		exit 1
	fi
	trap "ret=\$?; echo removing temporaty files; rm -f ${tempfile}/*; rmdir ${tempfile}; exit \$ret" EXIT
	av_encfile="${OUTPUT}"

	# check source.ts
	avconv -i "${OUTPUT}" > "${tempfile}/tsstat" 2>&1

	video_map="-map v"
	audio_map="-map a"

	#echo "video.map: ${video_map}"
	#echo "audio.map: ${audio_map}"

	echo "avconv with full on ${SSH_HOST}"
	remote_tmp=$(${SSH_CMD} mktemp)
	remote_tmp=${remote_tmp:-${tempfile}/full.mp4}
	dd if="${OUTPUT}" ibs=${BUFFER_SIZE} obs=${OUTBUF_SIZE} | \
		${THROTTLE_CMD} | \
		${SSH_EXEC} avconv -i pipe: -loglevel ${loglevel} -y \
		-f mp4 -pre:v hq -vcodec libx264 -acodec libfaac -vsync 1 \
		-r 30000/1001 -filter:v yadif -aspect 16:9 -crf ${CRFactor} \
		-ss 00:00:01 ${video_map} ${audio_map} \
		${remote_tmp} || exit 1
	if [ "x${SSH_HOST}" \!= xlocalhost ]; then
		exec 9>&-
		echo Running scp...
	fi
	${SCP_CMD} ${SSH_HOST}:${remote_tmp} ${tempfile}/full.mp4
	${SSH_CMD} rm -f ${remote_tmp}
	echo "avconv with cm cut"
	if [ "${skip_cmcut}" = y ]; then
		echo "skip because user requested"
	elif [[ "${FILENAME}" == GR* ]]; then
		echo "skip because this file is GR*"
	else
		if [ "x${SSH_HOST}" \!= xlocalhost ]; then
			exec 9>>/run/epgrec/encode-localhost.lock
			flock -n 9
			if [ $? -ne 0 ]; then
				echo 'Waiting for exclusive lock...'
				flock 9
			fi
		fi
		ln -s "$(readlink -f "${OUTPUT}")" "${tempfile}/source.ts"
		mkfifo "${tempfile}/CUT-source.ts"
			cd "${tempfile}"
		"${EPGREC_D}/comskip_wrapper.sh" "${EPGREC_D}/comskip.ini" source.ts
		errorcode=$?
		if [ ${errorcode} -eq 2 ]; then
			: # No commercials are found
		elif [ ${errorcode} -ne 0 ]; then
			echo "comskip seems to exit with error. abort."
			exit 1
		else
			avconv -i "${tempfile}/CUT-source.ts" -loglevel ${loglevel} -y -f mp4 \
				-pre:v hq -vcodec libx264 -vsync 1 -acodec libfaac \
				-r 30000/1001 -filter:v yadif -aspect 16:9 \
				-crf ${CRFactor} -ss 00:00:01 \
				${video_map} ${audio_map} \
				"${tempfile}/cmcut.mp4" || exit 1
			qt-faststart "${tempfile}/cmcut.mp4" \
				"${nocmfile}" || exit $?
		fi
	fi #gr check
	qt-faststart "${tempfile}/full.mp4" \
		"${fullfile}" || exit 1
	if [ ! -e "${fullfile}" ]; then
		echo "NOT FOUND ENCODED FILE!!!"
		exit 16
	fi
	if [ $(stat -c %s "${fullfile}") -lt $(( $(stat -c %s "${OUT_FULL}") * ${FAIL_FILESIZE_RATIO} )) ]; then
		echo "Output file is too much small! Abort."
		exit 4
	fi
	echo "finish! cleaning up..."
	rm ${tempfile}/*
	rmdir ${tempfile}
	trap EXIT
	#echo "Wait for mediatomb importing..."
	#sleep 5
fi

if [ ! -e "${fullfile}" ]; then
	echo "NOT FOUND ENCODED FILE!!!"
	exit 16
	#php "${EPGREC_D}/mediatomb-update.php" "${FNFULLPATH}" "${fullfile}"
fi

cd "${TO}/full"
php "${EPGREC_D}/update-filepath.php" "${FN_NOSUF}.mp4"
php "${EPGREC_D}/update-filepath.php" "nocm-${FN_NOSUF}.mp4"

if [ -z "${TRANS}" ]; then
	: #ignore
elif [ -e "${FN_NOSUF}.mp4" ]; then
	php "${EPGREC_D}/epgrec-update.php" "${TRANS}" "$(pwd)/${FN_NOSUF}.mp4"
	#[ -e "${EPGREC_D}/thumbs/${FILENAME}.jpg" ] && mv "${EPGREC_D}/thumbs/${FILENAME}.jpg" "${EPGREC_D}/thumbs/${FN_NOSUF}.mp4.jpg"
elif [ -e */"${FN_NOSUF}.mp4" ]; then
	_filename=*/"${FN_NOSUF}.mp4"
	php "${EPGREC_D}/epgrec-update.php" "${TRANS}" "$(pwd)/${_filename}"
	#[ -e "${EPGREC_D}/thumbs/${FILENAME}.jpg" ] && mv "${EPGREC_D}/thumbs/${FILENAME}.jpg" "${EPGREC_D}/thumbs/${FN_NOSUF}.mp4.jpg"
fi

if [ ! -v 'av_encskip' -a -e "${FROM}/${FILENAME}" ]; then
	echo 'Moving file into archives'
	cd "${FROM}"
	ln "${FILENAME}" "${ARCHIVE}/${FILENAME}" || exit 1
	#sleep 1
	#php "${EPGREC_D}/mediatomb-update.php" "${FNFULLPATH}" "${ARCHIVE}/${FILENAME}"
	rm "${FILENAME}"
fi

#rm -f ${FILENAME} ${tempfile}

fi # avoid size differing
#########################
