#!/bin/bash

basedir=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
location=`cat $basedir/location`
rsync -r $basedir/$location flserver:/home/flightlines/
rsync -r $basedir/logs/ flserver:/home/flightlines/$location/
git pull origin master -q