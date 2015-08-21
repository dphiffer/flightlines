#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location=`cat $basedir/location`
date=`date +%Y-%m-%d`
time=`date +%H:%I:%S`
logfile="$basedir/logs/$location-sync-$date.log"
{
	echo "-- $date $time --"
	rsync -r $basedir/videos/$location flserver:/home/flightlines/
	rsync -r --exclude .keep-dir $basedir/logs/ flserver:/home/flightlines/$location/
	git pull origin master -q
} >> $logfile