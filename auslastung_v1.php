#!/usr/bin/php
<?php
/**
 * AP-Auslastungsskript für das Leitsystem der KIT Bibliothek
 *
 * http://www.bibliothek.kit.edu/cms/freie-lernplaetze.php
 *
 * Author: Stephan Westphal
 *
 */
$path = realpath(dirname(__FILE__));
include_once("$path/WlanControllerStats.class.php");
include_once("$path/SwitchStats.class.php");

$oids = array();
$controllerConfig = array();
$destinations = array();

// include all config files in conf.d ending in conf.php
foreach (glob($path . '/conf.d/*.conf.php') as $file) {
    include_once $file;
}
/******* CONFIGURATION *********/

$allow_upload = 0;
$config_file = "$path/conf.d/conf.json"; // for SwitchStats

$verbose = 5;

// end config

if ($verbose > 1) {
    print "Starte Auslastungsskript" . date("Y-m-d H:i:s") . "\n";
}

$output = new stdClass(); //output object

/*****
 *
 * Controller abfragen
 *
 ****/
$controllerStats = array(); // Controller objects
foreach ($controllerConfig AS $i => $c) {
    if ($verbose > 1) print "\n** Polling Controller $c->name **\n";
    $controller = new WlanControllerStats($c->host, $c->community, $oids[$c->type], $c->cache_file);
    //$controller->setMaxCacheAge();

    if ($c->use_cache)
        $controller->loadCache(false);

    if ($controller->ap_clients())
        $controller->saveCache();
    $controller->closeSession();

    if ($verbose > 0) print_r($controller->getErrors());
    if ($verbose > 0) print_r($controller->getDebug());

    $controllerStats[$i] = $controller;
}

/*****
 *
 * Zielfilter anwenden
 *
 ****/

$reload_requests = array();

foreach ($destinations AS $j => $d) {

    $output = new stdClass();
    $output->name = $d->name;
    $output->ap_success = 0;
    $output->ap_errors = 0;

    $filtered_aps = array();

    $count = 0;

    // run filters on all controller objects
    foreach ($controllerStats AS $i => &$c) {
        /* @var $c WlanControllerStats */
        $data = $c->getActiveAps();
        // $data has: id, name, status, gruppe, time
        foreach ($data AS $i_ap => $ap) {
            $count++;
            $age = (time() - $ap->time);
            $age_str = floor($age / 60) . "min " . ($age % 60) . "s";

            // execute filters
            $filter_res = 0;
            if(!$d->filer_group && !$d->filter_name){
                $filter_res = 1; // if no includes defined, use all
            }else if($d->filter_group and in_array($ap->gruppe, $d->filter_group)) {
                // filtered by group
                $filter_res = 1;
            } else if ($d->filter_name) {
                // filter by name
                foreach ($d->filter_name As $i_name => $name_str) {
                    if (strstr($ap->name, $name_str)) {
                        $filter_res = 1;
                        break; // substring found, set to include and stop looking
                    }
                }
            }
            // exclude list
            if ($d->exclude_name) {
                // filter by name
                foreach ($d->exclude_name As $i_exclude => $exclude_str) {
                    if (strstr($ap->name, $exclude_str)) {
                        $filter_res = 0;
                        break; // substring found, set to exclude and stop looking
                    }
                }
            }
            // status für auslesen ok/fehler verwenden
            $ap->status = ($ap->clients_5G > -1 or $ap->clients_2G > -1) ? 1 : 0;

            if ($filter_res == 1) $output->aps[] = $ap;

            if ($filter_res == 1 and $ap->status == 1) $output->ap_success++;
            if ($filter_res == 1 and $ap->status == 0) $output->ap_errors++;

            if ($filter_res && $verbose > 1) print "[$count]\t " . str_pad($ap->name, 30) . " " . (($ap->status == 1) ? "online" : "offline") . "\t " . str_pad($ap->gruppe, 20) . " \t $age_str \t $filter_res \t $ap->clients_2G\t $ap->clients_5G \n";

        }

    } // end foreach Controller


    // save output-file

    $output_file = "$path/$d->output_file";
    $save_ok = file_put_contents($output_file, json_encode($output));

    if ($verbose > 0) print "Datei '$output_file' " . (($save_ok) ? "gespeichert. \n" : " konnte nicht gespeichert werden. \n");


    if ($allow_upload && $d->upload_url) {
        $upload_output = shell_exec("curl -s --upload-file $output_file '$d->upload_url'");
        $upload_res = (substr($upload_output, 0, 3) == "OK,") ? 1 : 0;
        if ($verbose > 1) print(($upload_res) ? " Upload OK" : "Upload failed");
    }
    if ($verbose > 0) print"\n";
    //print_r($output);
}


?>
