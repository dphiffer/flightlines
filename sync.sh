#!/bin/bash

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
if pgrep sync.sh >/dev/null 2>&1
	then
		echo "sync.sh already running."
		exit 1
fi

{
	echo "-- $date $time --"

	# Sync video files to server
	rsync \
		--recursive \
		--verbose \
		--ignore-existing \
		--remove-source-files \
		--timeout=30 \
		"$basedir/videos/$location" \
		flserver:/home/flightlines/videos/

	# Update scripts
	cd "$basedir" && git pull origin master -q
	
	date=`date +%Y-%m-%d`
	time=`date +%H:%M:%S`
	echo "Finished at $date $time"
} >> "$logfile"

# Sync log files
rsync \
	--recursive \
	--exclude .keep-dir \
	--timeout=30 \
	"$basedir/logs/" \
	"flserver:/home/flightlines/videos/$location/logs/"
