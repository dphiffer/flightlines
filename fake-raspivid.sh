#!/bin/bash

# Symlink me to /usr/local/bin/raspivid for testing

timeout=9000

while [[ $# > 1 ]]
do
key="$1"

case $key in
    -o|--output)
    output="$2"
    shift # past argument
    ;;
    -t|--timeout)
    timeout="$2"
    shift # past argument
    ;;
    *)
		# unknown option
    ;;
esac
shift # past argument or value
done

time_secs=`echo "${timeout}/1000" | bc`

`touch $output`
`sleep $time_secs`