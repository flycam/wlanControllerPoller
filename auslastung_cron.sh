#!/bin/bash

pushd `dirname $0` > /dev/null
SCRIPTPATH=`pwd`
popd > /dev/null


$SCRIPTPATH/auslastung_v1.php > $SCRIPTPATH/cache/last_cron_output.log
