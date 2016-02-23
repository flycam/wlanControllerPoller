#!/usr/bin/php
<?php
/**
 * Example configuration file. copy and rename to something.conf.php to have it included.
 * Author: Stephan Westphal, stephan.westphal@partner.kit.edu
 * Date: 2/23/16
 * Time: 6:20 PM
 */

$controllerConfig[] = new Controller(
    "controller-name",  // name, used in cache-file name
    "controller.example.com",  // hostname or IP
    "aruba",    //type, as defined in oids.conf.php
    "public"  //snmp community
);

$destinations[] = new Destination(
    "destination-name",  // name (used in filename)
    array("default"),   // Ap-Group name to inclue
    array(              // AP names to include. matched using contains
        "ap-name-1",
        "ap-name-2",
        "ap-extern"
    ),
    false,  // exclude-list (array), matched using contains
    NULL    // upload URL, using http-file-upload (curl -S)
);

// for all aps of a controller, leave group and destination false, exapmle:
// $destinations = new Destination("name",false,false,false,NULL);
