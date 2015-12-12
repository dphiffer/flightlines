#!/bin/bash

min_time="90000"    # start at 9am
max_time="220000"   # end at 10pm

lockfile="/tmp/flightlines-capture.lock"
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

date=`date +%Y%m%d`
time=`date +%H%M%S`
log_date=`date +%Y-%m-%d`
comp_time=`echo $time | sed 's/^0*//'`
logfile="$basedir/logs/$location-capture-$log_date.log"
{
	if (( $comp_time > $min_time )) && (( $comp_time < $max_time )) ; then

			jpg_file="$location-$date-$time.jpg"
			echo "$jpg_file"
			
                        # Create the date folder if none exists
			if [ ! -d "$videos/$date" ] ; then
				mkdir -p "$videos/$date"
			fi
			# Capture video
			raspistill \
			    --output "$videos/$date/$jpg_file" \
			    --width 1920 \
			    --height 1440 \
			    --nopreview \
			    --quality 70

	fi
} >> $logfile

`$basedir/sync.sh`