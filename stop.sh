#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# Cleanup lock files
rm /tmp/flightlines*

# Stop any existing processes
killall capture.sh
killall sync.sh
killall rsync
killall raspivid

# Cleanup in-progress video files
cd "$basedir" && rm *.h264