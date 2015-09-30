#!/bin/bash

lockfile="/tmp/flightlines-sync.lock"
basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location="nowhere"

# Don't run if stopped
if [ -f "$basedir/stopped" ] ; then
	exit 1
fi

if [ -f "/etc/hostname" ] ; then
	location=`cat /etc/hostname`
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

	# Sync video files to server
	rsync \
		--recursive \
		--verbose \
		--ignore-existing \
		--remove-source-files \
		"$basedir/videos/$location" \
		flserver:/home/flightlines/videos/

	# Update scripts
	cd "$basedir" && git pull origin master -q
	
	date=`date +%Y-%m-%d`
	time=`date +%H:%M:%S`
	echo "Finished at $date $time"
} >> "$logfile"

# Sync log files
rsync -r --exclude .keep-dir "$basedir/logs/" "flserver:/home/flightlines/videos/$location/logs/"

rm "$lockfile"
