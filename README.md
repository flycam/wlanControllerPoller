## AP & LTA Auslastungsscript fuer das Leitsystem der Bibliothek

## Installation ##

requires php5-cli, php5-snmp

Extract to a directory, and make sure the cache dir is writable for the user running the cronjob.

## Configuring ##
Copy the conf.d/example-conf.php to something.conf.php, add your controller information and destination filters.

See conf.g/example-conf.php for documentation of config

## Running by hand ##

running it by hand: ./auslastung_v1.php

## Cronjob ##

point your cronjob to auslastung_cron.sh
*/5 * * * * /absolute/path/to/dir/auslastung_cron.sh

A log of the last run will be in cache/last_cron_output.log