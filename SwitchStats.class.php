<?php
/**
 * SwitchStats Class
 * Author: Stephan Westphal
 * Date: 8/23/15
 * Time: 11:31 AM
 */
class SwitchStats
{
    private $conf;
    private $errors;

    private $session;

    public $debug;
    public $verbose;

    private $lta;
    private $aps;

    private $lta_stats;

    function __contruct()
    {
        $this->errors = array();
        $this->session = false;
        $this->debug = 0;
        $this->verbose = 0;

        $this->lta = array();
        $this->aps = array();
    }

    /**
     * loadConfig function.
     *
     * @access public
     * @param string $config path to config file
     * @return bool
     */
    public function loadConfig($config)
    {
        if (!$file = file_get_contents($config)) {
            // send error
            $this->errors[] = "AP-Auslastung: Konfiguration $config konnte nicht gelesen werden.\n";
            return false;
        }
        // read file
        if (!$this->conf = json_decode($file)) {
            $this->errors[] = "AP-Auslastung: Syntaxfehler in Konfiguration $config.\n";
            return false;
        }
        return true;
    }

    public function checkNameCache()
    {
        // future use, get lta device names from

    }

    /**
     * lta_auslastung function.
     *
     * @access public
     * @param int $save (default: 0)
     * @return object $switches, false on error
     */
    public function auslastung($save = 0)
    {
        if (!isset($this->conf->switches)) {
            $this->errors[] = "Keine Switche in Konfiguration gefunden";
            return false;
        }

        $errors = 0;

        $switches = array();

        foreach ($this->conf->switches AS $i => $s) {
            if (!$type = $this->getSwitchType($s->type)) {
                //$this->errors[] = "Switchtyp konnte nicht geladen werden.";
                continue; //failed to load type, so skip
            }
            // starte SNMP session
            if (!$session = $this->startSession($s->name, $s->community)) {
                $this->errors[] = "SNMP Session für '$s->name' konnte nicht gestartet werden.";
                $errors++;
                continue;
            }

            // lade interface Untagged Vlan
            try {
                $ifUntagged = $session->walk($type->port_vlan_oid, 1);
            } catch (Exception $e) {
                $this->errors[] = "ifDefaultEgress Abfrage an '$s->name' fehlgeschlagen";
                continue;
            }
            // lade interface OperStatus
            try {
                $ifStatus = $session->walk($type->port_status_oid, 1);
            } catch (Exception $e) {
                $this->errors[] = "ifStatus Abfrage an '$s->name' fehlgeschlagen";
                continue;
            }

            $port_count = 0;
            $up_count = 0;
            foreach ($ifUntagged AS $j => $p) {
                if ($p == $type->lta_search_string) {
                    $port_count++;
                    if (!isset($ifStatus[$j])) {
                        // error joining arrays
                        continue;
                    }

                    if ($this->verbose) print "ifStatus: " . $ifStatus[$j] . "\n";

                    if ($ifStatus[$j] == "up" || $ifStatus[$j] == 1) { // Bugfix vom 11.03.2015, es wurde nun kein integer sondern "up"/"down" ausgegeben.
                        //up = 1, down = 2
                        $up_count++;
                    }
                }
            }
            $percentage = round($up_count / $port_count * 100, 2);
            if ($this->verbose) print "LTA-Ports '$s->name': $up_count/$port_count = $percentage%. \n";

            $s->lta_ports = $port_count;
            $s->lta_ports_up = $up_count;
            $s->percentage_up = $percentage;
            unset($s->type);
            $switches[] = $s;

            usleep(2000);
        }
        $this->lta = $switches;

        if ($save) {

        }
        return $switches;
    }

    /**
     * lta_stats function.
     *
     * @access public
     * @param mixed $switches
     * @return object stats
     */
    public function stats($switches = false)
    {
        if (!$switches) $switches = $this->lta;
        $obj = new stdClass();
        $obj->num_switches = 0;
        $obj->num_lta_ports = 0;
        $obj->num_lta_up = 0;
        foreach ($switches AS $i => $s) {
            $obj->num_switches++;
            $obj->num_lta_ports += $s->lta_ports;
            $obj->num_lta_up += $s->lta_ports_up;
        }
        $obj->total_usage = round($obj->num_lta_up / $obj->num_lta_ports * 100, 2);
        $stats_str = "$obj->num_switches Switche mit $obj->num_lta_up von $obj->num_lta_ports LTA-Ports up ($obj->total_usage%) \n";
        if ($this->verbose) print $stats_str;
        $this->lta_stats = $obj;
        return $stats_str;
    }

    public function get_stats()
    {
        return $this->lta_stats;
    }

    /**
     * getSwitchType function.
     *
     * @access protected
     * @param string $type
     * @return object $switchtypes, false on error
     */
    protected function getSwitchType($type)
    {
        if (!isset($this->conf->switchtypes)) {
            $this->errors[] = "Switchtypen konnte nicht in der Konfigurationsdatei gefunden werden.";
            return false;
        }
        foreach ($this->conf->switchtypes AS $i => $stype) {
            if ($type == $stype->type) {
                return $stype;
            }
        }
        $this->errors[] = "Switchtyp '$type' konnte nicht gefunden werden.";
        return false;

    }


    public function saveData($file)
    {
        // saves ap and lta data to file
        $data = new stdClass();
        $data->time = time();
        $data->description = "LTA Auslastung";

        // get ap data
        if (isset($this->aps)) {
            $data->aps = $this->aps;
        }
        // get lta data
        if (isset($this->lta)) {
            $data->lta = $this->lta;

        }
        return (file_put_contents($file, $data));
    }

    /**
     * getErrors function.
     *
     * @access public
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * startSession function.
     *
     * @access private
     * @param string $host "hostname"
     * @return object $session, else false
     */
    private function startSession($host, $community)
    {
        try {
            // workaround für fehlende Exceptions im SNMP Constructor
            set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
                // error was suppressed with the @-operator
                if (0 === error_reporting()) {
                    return false;
                }

                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
            $session = new SNMP(SNMP::VERSION_2c, $host, $community);
            restore_error_handler();
            $session->exceptions_enabled = SNMP::ERRNO_ANY;
            $session->quick_print = 1;

        } catch (Exception $e) {
            return false;
        }

        return $session;
    }

    public function closeSession($session)
    {
        $session->close();
    }
}

?>