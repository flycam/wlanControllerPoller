<?php
/**
 * WlanControllerClasses for wlanControllerPoller
 * Author: Stephan Westphal
 * Date: 8/23/15
 * Time: 11:31 AM
 */
class WlanControllerStats
{
    private $name;
    private $community;
    private $cache_file;
    private $cache_time;
    private $max_cache_age;
    private $debug;

    private $oids;
    /* @var $session SNMP */
    private $session;
    // output
    private $aps;
    // cachable values
    private $ap_names;
    private $ap_names_time;
    private $ap_groups;
    private $ap_groups_time;

    private $error = false;

    public $verbose = false;

    public function __construct($host, $community, $oids, $cache_file = false)
    {
        // gets config
        $this->host = $host;
        $this->community = $community;
        $this->oids = $oids;
        $path = realpath(dirname(__FILE__));
        $this->cache_file = $path . "/" . $cache_file;
        $this->max_cache_age = 3600 * 2;

        $this->session = false;
        $this->debug = new stdClass();

        $this->ap_names = false;
        $this->ap_names_time = 0;
        $this->ap_groups = false;
        $this->ap_groups_time = 0;

    }

    public function getName()
    {
        return $this->host;
    }

    public function setMaxCacheAge($seconds)
    {
        if (is_numeric($seconds) && $seconds >= 0 && $seconds < 24 * 3600) {
            $this->max_cache_age = $seconds;
            return true;
        }
        return false;
    }

    public function saveCache()
    {
        if ($this->cache_file) {
            $cache = new stdClass();
            $cache->time = time();
            $cache->controller = $this->host;
            $cache->ap_names = $this->ap_names;
            $cache->ap_groups = $this->ap_groups;
            $cache->aps = $this->aps;

            if (file_put_contents($this->cache_file, json_encode($cache))) {
                $this->debug->cache_saved = 1;
            }
        }
    }

    public function loadCache($load_aps = false)
    {
        if ($this->cache_file && file_exists($this->cache_file)) {
            // load cached content
            if ($cache_contents = file_get_contents($this->cache_file)) {
                if ($cache = json_decode($cache_contents)) {
                    if (isset($cache->time)) $this->cache_time = $cache->time;
                    if (isset($cache->ap_names)) {
                        $this->ap_names = $cache->ap_names;
                        $this->ap_names_time = $cache->time;
                    }
                    if (isset($cache->ap_groups)) {
                        $this->ap_groups = $cache->ap_groups;
                        $this->ap_groups_time = $cache->time;
                    }
                    if ($load_aps && isset($cache->aps)) $this->aps = $cache->aps;
                    $this->debug->cache_loaded = 1;
                }

            }
        }
    }

    public function getCacheAge()
    {
        return time() - $this->cache_time;
    }

    public function getValues($oid)
    {
        if ($this->session) {
            try {
                $results = $this->session->walk($this->oids->enterprises . '.' . $oid, 1);
            } catch (Exception $e) {
            }
        }
        return (isset($results)) ? $results : false;
    }

    public function getCachedValues($oid, $file)
    {

    }

    /**
     * ap_clients function.
     *
     * @access public
     * @param bool $force_reload (default: false)
     * @return bool
     */
    public function ap_clients($force_reload = false)
    {
        if (!$this->session) $this->startSession();

        if (!$this->session) $this->error[] = "Unable to start snmp session";

        $data = array();

        $t1 = microtime(1);
        //fetch status
        $ap_status = $this->getValues($this->oids->ap_status);

        // fetch Clients
        $clients = $this->getValues($this->oids->ap_clients);

        //check reload
        $check_cache_age = $this->getCacheAge() < $this->max_cache_age;
        $check_entry_count = count($ap_status) == count((array)$this->ap_names); // casting to array to be able to count names in object
        if (!($this->ap_names && $check_entry_count && $check_cache_age)) {
            $force_reload = true; // reload names/groups if not set or there is a discrepancy between the number of APs
        }

        //fetch names
        if (!$this->ap_names || $force_reload) {
            $this->ap_names = $this->getValues($this->oids->ap_names);
            $this->debug->fetchedApNames = 1;
        }

        //fetch Groups
        if (!$this->ap_groups || $force_reload) {
            $this->ap_groups = $this->getValues($this->oids->ap_group);
            $this->debug->fetchedApGroups = 1;
        }

        $t2 = microtime(1);
        $time = time();

        $num_aps = 0;
        if (!$this->ap_names) {
            $this->error[] = "AP Names not loaded. Cannot get stats without ap names";
            return false;
        }
        foreach ($this->ap_names AS $i => $ap_name) {
            $ap = new stdClass();
            $ap_name = str_replace('"', '', $ap_name);

            $id_2G = "$i.2";
            $id_5G = "$i.1";
            $ap->id = $i;
            $ap->name = $ap_name;
            if (!is_array($this->oids->ap_status_ok_val))
                $this->oids->ap_status_ok_val = array($this->oids->ap_status_ok_val);
            $ap->status = ($ap_status && in_array($ap_status[$i], $this->oids->ap_status_ok_val)) ? 1 : 0;

            $ap->clients_2G = -1;
            $ap->clients_5G = -1;

            if ($ap->status == 1 && $clients) {
                $ap->clients_2G = $clients[$id_2G];
                $ap->clients_5G = $clients[$id_5G];
            }

            if ($this->ap_groups) {
                if (is_array($this->ap_groups)) {
                    $ap->gruppe = ($this->ap_groups) ? str_replace('"', '', $this->ap_groups[$i]) : NULL;
                } else {
                    $ap->gruppe = ($this->ap_groups) ? str_replace('"', '', $this->ap_groups->$i) : NULL;
                }
            }

            $ap->time = $time;

            if ($this->verbose) print "AP: $ap_name: " . $ap_status[$i] . "\n";

            $data[] = $ap;
            $num_aps++;
        }

        $this->debug->total_time = $t2 - $t1;
        $this->debug->time_per_ap = $this->debug->total_time / $num_aps;
        $this->debug->num_aps = $num_aps;

        $this->aps = $data;
        return $data;
    }

    /**
     * saveAps function.
     *
     * @access public
     * @param string $file (filename)
     * @return void
     */
    public function saveAps($file)
    {
        file_put_contents($file, json_encode($this->aps));
    }

    /**
     * loadAps function.
     *
     * @access public
     * @param string $file
     * @return bool
     */
    public function loadAps($file)
    {
        if ($cache_contents = file_get_contents($file)) {
            if ($aps = json_decode($cache_contents)) {
                $this->aps = $aps;
                return true;
            } //else syntax error
        }
        return false;
    }

    /**
     * getAps function.
     *
     * @access public
     * @return array
     */
    public function getAps()
    {
        return $this->aps;
    }

    public function getActiveAps()
    {
        if (!is_array($this->aps))
            return false;
        $res = array();
        foreach ($this->aps As $i => $ap) {
            if ($ap->status == 1) $res[] = $ap;
        }
        return $res;
    }

    /**
     * @param array $group
     * @param array $names
     * @param array $exclude
     * @return array|bool
     */
    public function filterAps(array $group, array $names, array $exclude)
    {
        if (!is_array($this->aps))
            return false;
        $default_filter_result = false;
        if (!$group && count($names) == 0) { // no filter given, return all
            $default_filter_result = true;
        }
        $res = array();
        foreach ($this->aps As $i => $ap) {
            if ($this->filterAp($ap, $group, $names, $exclude, $default_filter_result)) {
                $res[] = $ap;
            }
        }
        return $res;
    }

    /** Filters an AP based on a list of name-filter, groups and exlcude lists
     * @param $ap
     * @param array $group
     * @param array $names
     * @param array $exclude
     * @param bool|false $all
     * @return bool
     */
    public function filterAp(&$ap, array $group, array $names, array $exclude, $all = false)
    {
        if ($ap->status != 1) return false; // do not return unsassociated APs

        $filter_res = $all;
        if (count($group) > 0 and in_array($ap->gruppe, $group)) {
            $filter_res = true;
        } else if (count($names) > 0 && $this->filterByName($ap->name, $names)) {
            // filter by name
            $filter_res = true;
        }
        if (count($exclude) > 0 && $this->filterByName($ap->name, $exclude)) {
            $filter_res = false;
        }

        return $filter_res;
    }

    /**
     * @param $name
     * @param array $filters
     * @return bool
     */
    protected function filterByName($name, array $filters)
    {
        foreach ($filters AS $filter_i => $filter_str) {
            if (strpos($name, $filter_str) !== false) {
                return true;
            }
        }
        return false;
    }

    public function getApsByGroup($group, $active_only = false)
    {
        if (!is_array($this->aps))
            return false;
        $res = array();
        foreach ($this->aps As $i => $ap) {
            if ($ap->gruppe == $group && (!$active_only || $ap->status == 1)) $res[] = $ap;
        }
        return $res;
    }

    /**
     * startSession function.
     *
     * @access private
     * @return object $session, else false
     */
    private function startSession()
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
            $session = new SNMP(SNMP::VERSION_2c, $this->host, $this->community);
            restore_error_handler();
            $session->exceptions_enabled = SNMP::ERRNO_ANY;
            $session->quick_print = 1;

        } catch (Exception $e) {
            $this->error[] = $e;
            return false;
        }

        $this->session = $session;
        return true;
    }

    public function closeSession()
    {
        if ($this->session)
            $this->session->close();
    }

    public function getErrors()
    {
        return (isset($this->error)) ? $this->error : false;
    }

    public function getDebug()
    {
        return $this->debug;
    }
}


/**
 * Controller class.
 */
class Controller
{
    public $name;
    public $host;
    public $type; // aruba or hp
    public $cache_time;
    public $cache_file;
    public $community;
    public $use_cache;

    public function __construct($name, $host, $type, $community, $cache = false)
    {
        $this->cache_time = 7200;
        $this->name = $name;
        $this->type = $type;
        $this->host = $host;
        $this->community = $community;
        $this->cache_file = "cache/$name.json";
        $this->use_cache = $cache;
    }

}


/**
 * OIDs Class for different models of controllers
 */
class ControllerOIDs
{
    public $enterprises;
    public $ap_names;
    public $ap_status;
    public $ap_status_ok_val;
    public $ap_group;
    public $ap_clients;
}


/**
 * Destination class.
 */
class Destination
{
    public $name;
    public $filter_group = array();
    public $filter_name = array();
    public $exclude_name = array();
    public $output_file;
    public $upload_url;

    public function __construct($name, $filter_group = NULL, $filter_name = NULL, $exclude_name = NULL, $upload = false)
    {
        $this->name = $name;
        $this->filter_group = $filter_group;
        $this->filter_name = $filter_name;
        $this->exclude_name = $exclude_name;
        $this->output_file = "cache/$name-output.json";
        $this->upload_url = $upload;
    }
}
