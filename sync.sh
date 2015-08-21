#!/bin/bash

lockfile="/tmp/flightlines-sync.lock"
basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location=`cat $basedir/location`
date=`date +%Y-%m-%d`
time=`date +%H:%M:%S`
logfile="$basedir/logs/$location-sync-$date.log"

# Don't run more than one sync script at a time
if [ -z "$flock" ] ; then
	lockopts="-w 0 $lockfile"
	exec env flock=1 flock $lockopts $0 "$@"
fi

{
	echo "-- $date $time --"

	# Sync video files
	rsync -rvi --ignore-existing "$basedir/videos/$location" flserver:/home/flightlines/

	# Update scripts
	cd $basedir && git pull origin master -q
} >> $logfile

# Sync log files
rsync -r --exclude .keep-dir "$basedir/logs/" "flserver:/home/flightlines/$location/"
