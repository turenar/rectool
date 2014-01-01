#!/bin/sh
echo "CHANNEL : $CHANNEL"
echo "DURATION: $DURATION"
echo "OUTPUT  : $OUTPUT"
echo "TUNER : $TUNER"
echo "TUNER_UNIT : $TUNER_UNIT"
echo "TYPE : $TYPE"
echo "MODE : $MODE"
echo "SID  : $SID"

EPGREC_D=$(readlink -f $(dirname $0))

if [ ${TUNER} -lt ${TUNER_UNIT} ]; then
	RECORDER=/usr/local/bin/recpt1

	if [ ${OUTPUT} = '-' ]; then
		# リアルタイム視聴
		VIEW_CMD=$RECORDER' --b25 --strip --http 8888 --sid all'
		$VIEW_CMD
		echo "$VIEW_CMD" > /tmp/realview_cmd
	else
		# fail safe
		if [ -z $SID ]; then
		   	SID='hd'
		elif [ ${SID} = 'epg' ]; then
			# EPG専用出力モード
		   	MODE=1
		fi

		if [ ${MODE} = 0 ]; then
			# MODE=0では必ず無加工のTSを吐き出すこと
			$RECORDER --b25 --strip $CHANNEL $DURATION "$OUTPUT" >/dev/null
		elif [ ${MODE} = 1 ]; then
			# 目的のSIDのみ残す
			$RECORDER --b25 --strip --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
		elif [ ${MODE} = 2 ]; then
			# 目的のSIDのみ残す SD用
			$RECORDER --b25 --strip --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
		elif [ ${MODE} = 3 ]; then
 	 		$RECORDER --b25 --strip --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
			echo "OUTPUT='${OUTPUT}' ${EPGREC_D}/do_encode.sh" | at -q F $(date +'%y-%m-%d')
			( echo $OUTPUT; ionice -c 3 nice -n 19 /usr/local/bin/tsselect "${OUTPUT}" 2>/dev/null ) | mail -s 'drop report' root
		else
			$RECORDER --b25 --strip --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
		fi
	fi

else
	if [ ${TYPE} = 'GR' ]; then
		RECORDER=/usr/local/bin/recfsusb2n

		if [ ${OUTPUT} = '-' ]; then
			# リアルタイム視聴 PT1以外
			# パイプでVLCの配信機能を使用する場合
#			VIEW_CMD=$RECORDER" --b25 --sid $SID $CHANNEL - -"
#			$VIEW_CMD | cvlc - --play-and-exit --sout '#standard{access=http,mux=ts,dst=:8888}' &
			# 配信機能に対応している場合
			VIEW_CMD=$RECORDER" --b25 --http 8888 --sid $SID"
			$VIEW_CMD

			echo "$VIEW_CMD" > /tmp/realview_cmd
		else
			if [ -z $SID ]; then
			   	SID='hd'
			elif [ ${SID} = 'epg' ]; then
				# EPG専用出力モード
			   	MODE=1
			fi

			if [ ${MODE} = 0 ]; then
				# MODE=0では必ず無加工のTSを吐き出すこと
				$RECORDER --b25 $CHANNEL $DURATION "$OUTPUT" >/dev/null || sleep 10
			elif [ ${MODE} = 1 ]; then
				# 目的のSIDのみ残す
				$RECORDER --b25 --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null || sleep 10
			elif [ ${MODE} = 2 ]; then
				# 目的のSIDのみ残す SD用
				$RECORDER --b25 --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null || sleep 10
			elif [ ${MODE} = 3 ]; then
 	 			$RECORDER --b25 --strip --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
				echo "OUTPUT='${OUTPUT}' ${EPGREC_D}/do_encode.sh" >> ${EPGREC_D}/encode.sh
				( echo $OUTPUT; nice -n 19 /usr/local/bin/tsselect "${OUTPUT}" ) | mail -s 'drop report' root
#			elif [ ${MODE} = 3 ]; then
#				OUTPUT=${OUTPUT}.tmp.ts
#				$RECORDER --b25 --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null || sleep 10
			else
				$RECORDER --b25 --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null || sleep 10
			fi
		fi
	else
		RECORDER=/usr/local/bin/recfrio
		B25=/usr/local/bin/b25_bcas

		if [ ${OUTPUT} = '-' ]; then
			# リアルタイム視聴 PT1以外
			# パイプでVLCの配信機能を使用する場合
#			VIEW_CMD=$RECORDER" --b25 --sid $SID $CHANNEL - -"
#			$VIEW_CMD | cvlc - --play-and-exit --sout '#standard{access=http,mux=ts,dst=:8888}' &
			# 配信機能に対応している場合
			VIEW_CMD=$RECORDER" --b25 --http 8888 --sid $SID"
			$VIEW_CMD

			echo "$VIEW_CMD" > /tmp/realview_cmd
		else
			if [ -z $SID ]; then
			   	SID='hd'
			elif [ ${SID} = 'epg' ]; then
				# EPG専用出力モード
			   	MODE=1
			fi

			if [ ${MODE} = 0 ]; then
				# MODE=0では必ず無加工のTSを吐き出すこと
				$RECORDER --b25 $CHANNEL $DURATION "$OUTPUT" >/dev/null
			elif [ ${MODE} = 1 ]; then
				# 目的のSIDのみ残す
				$RECORDER --b25 --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
			elif [ ${MODE} = 2 ]; then
				# 目的のSIDのみ残す SD用
				$RECORDER --b25 --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
			elif [ ${MODE} = 3 ]; then
 	 			$RECORDER --b25 --strip --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
				echo "OUTPUT='${OUTPUT}' ${EPGREC_D}/do_encode.sh" >> ${EPGREC_D}/encode.sh
				( echo $OUTPUT; nice -n 19 /usr/local/bin/tsselect "${OUTPUT}" ) | mail -s 'drop report' root
			else
				$RECORDER --b25 --sid $SID $CHANNEL $DURATION "$OUTPUT" >/dev/null
			fi
		fi
	fi
fi
#if [ ${MODE} = 3 ]; then
#   ffmpeg -i "$OUTPUT" ... 適当なオプション "$OUTPUT"
#fi
