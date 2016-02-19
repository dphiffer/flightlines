#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location="nowhere"
log_date=`date +%Y-%m-%d`

if [ -f "/etc/hostname" ] ; then
	location=`cat /etc/hostname`
fi

# Stop any scripts still running
`$basedir/stop.sh restarting`

logfile="$basedir/logs/$location-start-$log_date.log"
{
	ifconfig wlan0

	# Update scripts
	cd "$basedir" && git pull origin master -q

	# Remove stopped flag
	if [ -f "$basedir/stopped" ] ; then
		rm "$basedir/stopped"
	fi
} >> $logfile

`$basedir/capture.sh`
