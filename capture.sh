#!/bin/bash

lockfile="/tmp/flightlines-capture.lock"
if ( set -o noclobber; echo "locked" > "$lockfile") 2> /dev/null; then

	trap 'rm -f "$lockfile"; exit $?' INT TERM EXIT
	
	min_time="73000"    # start at 7:30am
	max_time="163000"   # end at 4:30pm
	bitrate="7500000"   # 7.5 mb/s
	timeout="600000"    # 10 minutes
	width="1024"
	height="576"

	basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
	location="nowhere"

	# Don't run if stopped
	if [ -f "$basedir/stopped" ] ; then
		exit 1
	fi

	if [ -f "/etc/hostname" ] ; then
		location=`cat /etc/hostname`
	fi

	videos="$basedir/videos/$location"

	# Override timeout with first param
	if [ -n "$1" ] ; then
		timeout="$1"
	fi

	if [ ! -d "$videos" ] ; then
		mkdir -p "$videos"
	fi

	while [ 1 ] ; do
		date=`date +%Y%m%d`
		time=`date +%H%M%S`
		log_date=`date +%Y-%m-%d`
		comp_time=`echo $time | sed 's/^0*//'`
		logfile="$basedir/logs/$location-capture-$log_date.log"
		{
			if (( $comp_time > $min_time )) && (( $comp_time < $max_time )) ; then

				h264_file="$location-$date-$time.h264"
				mp4_file="$location-$date-$time.mp4"
				echo "$h264_file"

				# Capture video
				raspivid \
					--nopreview \
					--timeout $timeout \
					--width $width \
					--height $height \
					--bitrate $bitrate \
					--vflip \
					--hflip \
					--output "$basedir/$h264_file"

				# Create the date folder if none exists
				if [ ! -d "$videos/$date" ] ; then
					mkdir -p "$videos/$date"
				fi

				# Process mp4 file and move it to the videos folder
				MP4Box -add "$basedir/$h264_file" -fps 30 "$basedir/$mp4_file" > /dev/null
				mv "$basedir/$mp4_file" "$videos/$date/$mp4_file"
				rm "$basedir/$h264_file"

			else

				# Too dark out
				echo "$location waiting $time"
				sleep 60 # wait until $min_time

			fi
		} >> $logfile
	done
	
	# Release lockfile
	rm -f "$lockfile"
fi
