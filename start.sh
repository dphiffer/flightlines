#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# Stop any scripts still running
`$basedir/stop.sh`

# Update scripts
cd "$basedir" && git pull origin master -q

# Cleanup in-progress video files
for file in $basedir/*.h264 ; do
	if [ -e "$basedir/$file" ] ; then
		rm "$basedir/$file"
	fi
done

`$basedir/capture.sh`