#!/bin/bash

lockfile="/tmp/flightlines-capture.lock"
basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location="nowhere"
timeout=600000 # 10 minutes

if [ -f "$basedir/location" ] ; then
	location=`cat $basedir/location`
else
	# Use default 'nowhere'
	echo "Warning: no 'location' file found."
fi

videos="$basedir/videos/$location"

# Override timeout with first param
if [ -n "$1" ] ; then
	timeout="$1"
fi

min_time="55959"  # start after 05:59:59
max_time="200000" # end before 20:00:00

# Don't run more than one capture script at a time
if [ -f "$lockfile" ] ; then
	echo "Lock file exists: $lockfile"
	exit 1
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
				--width 960 \
				--height 540 \
				--bitrate 12500000 \
				--output "$basedir/$h264_file"

			# Create the date folder if none exists
			if [ ! -d "$videos/$date" ] ; then
				mkdir -p "$videos/$date"
			fi

			# Process mp4 file and move it to the videos folder
			MP4Box -add "$basedir/$h264_file" "$basedir/$mp4_file"
			mv "$basedir/$mp4_file" "$videos/$date/$mp4_file"
			rm "$basedir/$h264_file"

		else

			# Too dark out
			echo "$location waiting $time"
			sleep 60 # wait until $min_time

		fi
	} >> $logfile
done
