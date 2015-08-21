#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location=`cat $basedir/location`
videos="$basedir/videos"

min_time="55959" # start after 05:59:59
max_time="200000" # end before 20:00:00

if [ ! -d "$videos" ]; then
	`mkdir -p $videos`
fi

while [ 1 ]
do
	date=`date +%Y%m%d`
	time=`date +%H%M%S`
	comp_time=`echo $time | sed 's/^0*//'`
	if (( $comp_time > $min_time )) && (( $comp_time < $max_time )); then

		filename="$location-$date-$time.mp4"
		echo "$filename"
		`raspivid -t 600000 -n -w 960 -h 540 -b 25000000 -o $basedir/$filename`

		if [ ! -d "$videos/$date" ]; then
			`mkdir -p $videos/$date`
		fi
		`mv $basedir/$filename $videos/$date/$filename`

	else
		echo "Waiting... $time"
		sleep 60 # wait until $min_time
	fi
done