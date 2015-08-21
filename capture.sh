#!/bin/bash

location=`cat /home/pi/location`
today=`date +%F`
dir="/home/pi/videos/$today"
`mkdir -p $dir`
`raspivid -t 0 -n -w 960 -h 540 -b 25000000 -sg 60000 -o $dir/$location-$today-%d.mp4`
