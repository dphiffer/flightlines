#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# Stop any scripts still running
`$basedir/stop.sh restarting`

# Update scripts
cd "$basedir" && git pull origin master -q

# Remove stopped flag
if [ -f "$basedir/stopped" ] ; then
	rm "$basedir/stopped"
fi

`$basedir/capture.sh`