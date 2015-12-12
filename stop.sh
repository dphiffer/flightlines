#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# Cleanup lock files
rm /tmp/flightlines*

# Stop any existing processes
killall capture.sh
killall sync.sh
killall rsync
killall raspistill

# If we are restarting, don't save stopped flag
if [ ! -n "$1" ] ; then
	touch "$basedir/stopped"
fi