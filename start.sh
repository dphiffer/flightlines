#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# Stop any scripts still running
`$basedir/stop.sh`

# Update scripts
cd "$basedir" && git pull origin master -q

# Start capture script
`$basedir/capture.sh`