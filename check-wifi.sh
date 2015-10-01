#!/bin/bash

ping -c4 phiffer.org > /dev/null
 
if [ $? != 0 ] 
then
	/sbin/ifdown 'wlan0'
	sleep 5
	/sbin/ifup --force 'wlan0'
fi
