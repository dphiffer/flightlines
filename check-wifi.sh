#!/bin/bash

ping -c4 phiffer.org > /dev/null

if [ $? != 0 ] ; then
	/sbin/ifdown 'wlan0'
	/usr/bin/killall sync.sh
	/bin/rm /tmp/flightlines-sync.lock
	sleep 5
	/sbin/ifup --force 'wlan0'
fi
