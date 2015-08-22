#!/bin/bash

# Cleanup lock files
rm "/tmp/flightlines*"

killall capture.sh
killall sync.sh
killall rsync
killall raspivid