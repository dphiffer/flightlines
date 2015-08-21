#!/bin/bash

# Check lock file
lockfile -r 0 /tmp/flightlines-sync.lock || exit 1

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location=`cat $basedir/location`
date=`date +%Y-%m-%d`
time=`date +%H:%M:%S`
logfile="$basedir/logs/$location-sync-$date.log"
{
	echo "-- $date $time --"

	# Sync video files
	rsync -rvi --ignore-existing $basedir/videos/$location flserver:/home/flightlines/

	# Update scripts
	cd $basedir && git pull origin master -q
} >> $logfile

# Sync log files
rsync -r --exclude .keep-dir $basedir/logs/ flserver:/home/flightlines/$location/

# Release lock file
rm -f /tmp/flightlines-sync.lock
