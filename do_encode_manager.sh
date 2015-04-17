#!/bin/bash


# '<user>@<hostname>/<job>' if localhost, we don't exec ssh.
ENCODE_HOSTS='localhost/2 illyasviel@einzbern/3'
# lock file directory
LOCK_DIR='/run/epgrec'
# mail address to sent encode log to
MAILTO='INVALID MAIL ADDRESS@localhost'
# interval to check not running worker
QUEUE_RECHECK=60
# video base dir
VIDEO_BASE_DIR=/data/epgrec



#exec >> /tmp/manager.log
while true; do
	for jost in ${encode_hosts:-${ENCODE_HOSTS}}; do
		host=${jost%/*}
		job=${jost#*/}
		if [ x${host} = x${job} ]; then
			job=1
		fi

		for i in $(seq 1 $job); do
			exec 8>>/run/epgrec/queue-${host}-${i}.lock
			flock -n 8
			if [ $? -eq 0 ]; then
				trap 'kill $(jobs -p)' EXIT
				echo "[manager] Running with ${host}#${i}"
				cd "${VIDEO_BASE_DIR}"
				enc_ssh=${host} $(dirname $0)/do_encode.sh "$*" 2>&1 | tee >(mail -s "Encode job" ${MAILTO})
				trap '' EXIT
				exit $?
			fi
		done
	done

	exec 8>&-
	sleep ${QUEUE_RECHECK}
done

