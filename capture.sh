#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location=`cat $basedir/location`
videos="$basedir/videos/$location"

min_time="55959"  # start after 05:59:59
max_time="200000" # end before 20:00:00

if [ ! -d "$videos" ]; then
	`mkdir -p $videos`
fi

while [ 1 ]
do
	date=`date +%Y%m%d`
	time=`date +%H%M%S`
	log_date=`date +%Y-%m-%d`
	comp_time=`echo $time | sed 's/^0*//'`
	logfile="$basedir/logs/$location-capture-$log_date.log"
	{
		if (( $comp_time > $min_time )) && (( $comp_time < $max_time )); then

			filename="$location-$date-$time.mp4"
			echo "$filename"

			# Capture video for 10 minutes
			`raspivid -t 600000 -n -w 960 -h 540 -b 25000000 -o $basedir/$filename`

			# Create the date folder if none exists
			if [ ! -d "$videos/$date" ]; then
				`mkdir -p $videos/$date`
			fi

			# Move mp4 to videos folder
			`mv $basedir/$filename $videos/$date/$filename`

		else

			# Too dark out
			echo "$location waiting $time"
			sleep 60 # wait until $min_time

		fi
	} >> $logfile
done