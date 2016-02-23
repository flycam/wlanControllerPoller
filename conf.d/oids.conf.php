<?php
/**
 * oid configs
 * User: stephan
 * Date: 8/21/15
 * Time: 3:14 PM
 */

class OIDs {
    public $descripton;
    public $enterprises;
    public $ap_names;
    public $ap_status;
    public $ap_status_ok_val;
    public $ap_group;
    public $ap_clients;
}

$oids['hp'] = new OIDs();
$oids['hp']->descripton = "HP MSM760";
$oids['hp']->enterprises = "1.3.6.1.4.1.8744";
$oids['hp']->ap_names = "5.23.1.2.1.1.6";
$oids['hp']->ap_status = "5.23.1.2.1.1.5";
$oids['hp']->ap_status_ok_val = array(6,7);
$oids['hp']->ap_group = "5.23.1.2.1.1.9";
$oids['hp']->ap_clients = "5.25.1.2.1.1.9";

$oids['aruba'] = new OIDs();
$oids['aruba']->descripton = "Aruba";
$oids['aruba']->enterprises = "1.3.6.1.4.1.14823";
$oids['aruba']->ap_names = "2.2.1.5.2.1.4.1.3";
$oids['aruba']->ap_status = "2.2.1.5.2.1.4.1.19";
$oids['aruba']->ap_status_ok_val = 1;
$oids['aruba']->ap_group = "2.2.1.5.2.1.4.1.4";
$oids['aruba']->ap_clients = "2.2.1.5.2.1.5.1.7";



