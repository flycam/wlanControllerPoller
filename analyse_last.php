#!/usr/bin/php
<?php
$path = realpath(dirname(__FILE__));

$files = array();
foreach (glob($path . '/cache/*-output.json') as $file) {
    $mtime = filemtime($file);
    $files[$mtime] = $file;
}
krsort($files);
reset($files);
if(current($files)){
    $input_file = current($files);
    print "Using file $input_file Age: ".date("H:i:s",time() - filemtime($input_file)) . "\n";
}else{
    print "No recent output file was found.";
    exit;
}
//print_r(json_decode($input_file));


// Argument parsing for upload and silence
$show_failed = false;
$silent = false;
$print = false;
$list = false;
foreach($argv AS $i => $arg){
    $all = ($arg == "-a");
    if($arg == "-s") $silent = true;
    if($arg == "--failed" || $all) $show_failed = true;
    if($arg == "-p" || $all) $print = true;
    if($arg == "-l" || $all) $list = true;
    //if($arg == "--dev") $input_file = "auslastung-output-dev.json";
}

if($list){
    print_r($files);
}

$input_data = json_decode(file_get_contents("$input_file"));

$num_ok = 0;
$num_error = 0;
$num_aps = 0;
$date = date("Y-m-d H:i:s",((isset($input_data->time)) ? $input_data->time : filemtime($input_file)));

$failed = array();

$clients_2G = 0;
$clients_5G = 0;

print "Abfrage vom $date [";
foreach($input_data->aps AS $i => $ap){
    if(!isset($ap->error)){
        print "+";
        $clients_2G += $ap->clients_2G;
        $clients_5G += $ap->clients_5G;
        $num_ok++;
    }else{
        print "-";
        $num_error++;
        $failed[] = $ap;
    }
    $num_aps++;
}
$percentage = round($num_ok/$num_aps * 100);
print "] $num_ok/$num_aps $percentage% Erfolgreich. \n$clients_2G 2,4GHz und $clients_5G 5GHz Clients (".($clients_2G+$clients_5G).")\n";

if($print){
    print str_pad("== AP ==",30)."2,4GHz\t\t5GHz\n";
    foreach($input_data->aps AS $i => $ap){
        if(!isset($ap->error))
            print str_pad($ap->name,30)." ".str_pad($ap->clients_2G,3)."\t\t$ap->clients_5G \n";

    }
}

if(isset($input_data->lta_stats)){
    $obj = $input_data->lta_stats;
    $stats_str = "$obj->num_switches Switche mit $obj->num_lta_up von $obj->num_lta_ports LTA-Ports up ($obj->total_usage%) \n";
    print $stats_str;
}
if($print){
    print str_pad("== Switch ==",15)."UP/LTA Ports\n";
    //{"name":"a3050g-103a","lta_ports":42,"lta_ports_up":5,"percentage_up":11.9}
    if(isset($input_data->lta)) foreach($input_data->lta AS $i => $switch){
        print str_pad($switch->name,15)." ".str_pad($switch->lta_ports_up,2,STR_PAD_LEFT)."/$switch->lta_ports ($switch->percentage_up%) Up\n";

    }
}
if($show_failed){
    print "== Failed APs ==\n";
    foreach($failed AS $i => $ap){
        print "$ap->name @$ap->controller\n";

    }
}

?>
