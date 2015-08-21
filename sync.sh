#!/bin/bash

location=`cat /home/pi/location`
`rsync -rv /home/pi/videos/ dp:/home/flightlines/$location`