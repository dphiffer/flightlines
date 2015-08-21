#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location=`cat $basedir/location`
date=`date +%Y-%m-%d`
time=`date +%H:%M:%S`
logfile="$basedir/logs/$location-sync-$date.log"
{
	echo "-- $date $time --"
	rsync -rv $basedir/videos/$location flserver:/home/flightlines/
	git pull origin master -q
} >> $logfile
rsync -r --exclude .keep-dir $basedir/logs/ flserver:/home/flightlines/$location/