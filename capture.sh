#!/bin/bash

lockfile="/tmp/flightlines-capture.lock"
basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location="nowhere"

if [ -f "$basedir/location" ] ; then
	location=`cat $basedir/location`
else
	echo "Warning: no 'location' file found."
fi

videos="$basedir/videos/$location"

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
		if (( $comp_time > $min_time )) && (( $comp_time < $max_time )); then

			h264_file="$location-$date-$time.h264"
			mp4_file="$location-$date-$time.mp4"
			echo "$h264_file"

			# Capture video for 10 minutes
			raspivid -t 600000 -n -w 960 -h 540 -b 12500000 -o "$basedir/$h264_file"

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
