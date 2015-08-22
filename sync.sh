#!/bin/bash

lockfile="/tmp/flightlines-sync.lock"
basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location="nowhere"

if [ -f "$basedir/location" ] ; then
	location=`cat $basedir/location`
else
	echo "Warning: no 'location' file found."
fi

date=`date +%Y-%m-%d`
time=`date +%H:%M:%S`
logfile="$basedir/logs/$location-sync-$date.log"

# Don't run more than one sync script at a time
if [ -f "$lockfile" ] ; then
	echo "Lock file exists: $lockfile"
	exit 1
fi

touch "$lockfile"

{
	echo "-- $date $time --"

	# Sync video files
	rsync -rv --ignore-existing "$basedir/videos/$location" flserver:/home/flightlines/videos/

	# Update scripts
	cd "$basedir" && git pull origin master -q
} >> "$logfile"

# Sync log files
rsync -r --exclude .keep-dir "$basedir/logs/" "flserver:/home/flightlines/$location/"

date=`date +%Y-%m-%d`
time=`date +%H:%M:%S`
echo "Finished at $date $time"

rm "$lockfile"