<?php
/**
 * Auslastung class, version 2. Still in dev, do not use for production
 * Author: Stephan Westphal
 * Date: 8/23/15
 * Time: 11:31 AM
 */
include_once("WlanControllerStats.class.php");
include_once("SwitchStats.class.php");


class Auslastung{
    public $allow_upload = false;

    private $oids = array();
    private $controllerConfig = array();
    private $controllerObjects = array();
    private $reload_requests;

    private $destinations = array();

    public $verbosity = 0;

    public function __construct(){

    }

    public function menu(){
        $shortopts = "hvpur";
        $options = getopt($shortopts);
        //var_dump($options);

        if(isset($options['h'])){
            $this->help();
            return;
        }
        $this->verbosity = isset($options['v']);
        $this->allow_upload = isset($options['u']);
        $this->reload_requests = isset($options['r']);
    }

    public function help(){
        print "Auslastungsskript. Optionen sind: \n -h\t Diese Hilfe\n -v\t Verbose\n -u\t Upload\n -r\t Refresh Cache\n";
    }

    public function readConfig(){

    }
    public function readOIDs(){

    }

    public function writeOIDconfig($oids,$filename){
        if($content = json_encode($oids) && is_writable($filename)){
            file_put_contents($filename,$content);
        }
    }

    public function queryControllers(){
        $this->controllerObjects = array(); // Controller objects
        foreach($this->controllerConfig AS $i => $c){
            if($this->verbosity > 1) print "\n** Polling Controller $c->name **\n";
            $controller = new WlanControllerStats($c->host, $c->community, $this->oids[$c->type],$c->cache_file);
            //$controller->setMaxCacheAge();

            $controller->loadCache(false);
            if($controller->ap_clients())
                $controller->saveCache();
            $controller->closeSession();

            if($this->verbosity > 0) print_r($controller->getErrors());
            if($this->verbosity > 0) print_r($controller->getDebug());

            $this->controllerObjects[$i] = $controller;
        }
    }

    public function parseDestinations(){
        foreach($this->destinations AS $j => $d) {
            /* @var $d Destination */
            $output = new stdClass();
            $output->name = $d->name;
            $output->ap_success = 0;
            $output->ap_errors = 0;

            $output->aps = array();

            $count = 0;

            // fetch & filter all APs
            foreach ($this->controllerObjects AS $i => &$c) {
                /* @var $c WlanControllerStats */
                //$data = $c->getActiveAps();
                array_merge($output->aps, $c->filterAps($d->filter_group, $d->filter_name, $d->exclude_name));
            }

            // do some stats and prepare data
            foreach ($output->aps AS $ap_i => $ap) {
                // $ap has: id, name, status, gruppe, time

                $count++;
                $age = (time() - $ap->time);
                $age_str = floor($age / 60) . "min " . ($age % 60) . "s";

                // status in AP für Ausgabe neu belegen
                $ap->status &= ($ap->clients_5G > -1 or $ap->clients_2G > -1);
                $output->aps[$ap_i]->status = $ap->status;

                if ($ap->status == 1) $output->ap_success++;
                if ($ap->status == 0) $output->ap_errors++;

                if ($this->verbosity > 1) print "[$count]\t " . str_pad($ap->name, 30) . " " . (($ap->status == 1) ? "online" : "offline") . "\t " . str_pad($ap->gruppe, 20) . " \t $age_str \t $ap->clients_2G\t $ap->clients_5G \n";
            }
        }
    }
}
