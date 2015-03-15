<?php

/**
 * CloudFlare API
 *
 *
 * @author AzzA <azza@broadcasthe.net>
 * @copyright omgwtfhax inc. 2013
 * @version 1.1
 * @github github.com/vexxhost/CloudFlare-API/
 */
class cloudflare_api
{
    //The URL of the API
    private static $URL = array(
        'USER' => 'https://www.cloudflare.com/api_json.html',
        'HOST' => 'https://api.cloudflare.com/host-gw.html'
    );

    //Service mode values.
    private static $MODE_SERVICE = array('A', 'AAAA', 'CNAME');

    //Prio values.
    private static $PRIO = array('MX', 'SRV');

    //Timeout for the API requests in seconds
    const TIMEOUT = 5;

    //Interval values for Stats
    const INTERVAL_365_DAYS = 10;
    const INTERVAL_30_DAYS = 20;
    const INTERVAL_7_DAYS = 30;
    const INTERVAL_DAY = 40;
    const INTERVAL_24_HOURS = 100;
    const INTERVAL_12_HOURS = 110;
    const INTERVAL_6_HOURS = 120;


    /**
     * CLIENT API
     * Section 3
     * Access
     */

    /**
     * 3.1 - Retrieve Domain Statistics For A Given Time Frame
     * This function retrieves the current stats and settings for a particular website.
     * It can also be used to get currently settings of values such as the security level.
     */
    public function stats($domain, $interval = 20)
    {
        $data = array(
            'a'        => 'stats',
            'z'        => $domain,
            'interval' => $interval
        );
        return $this->http_post($data);
    }

    /**
     * 3.2 - Retrieve A List Of The Domains
     * This lists all domains in a CloudFlare account along with other data.
     */
    public function zone_load_multi()
    {
        $data = array(
            'a' => 'zone_load_multi'
        );
        return $this->http_post($data);
    }

    /**
     * 3.3 - Retrieve DNS Records Of A Given Domain
     * This function retrieves the current DNS records for a particular website.
     */
    public function rec_load_all($domain)
    {
        $data = array(
            'a' => 'rec_load_all',
            'z' => $domain
        );
        return $this->http_post($data);
    }

    /**
     * 3.4 - Checks For Active Zones And Returns Their Corresponding Zids
     * This function retrieves domain statistics for a given time frame.
     */
    public function zone_check($zones)
    {
        if (is_array($zones)) {
            $zones = implode(',', $zones);
        }
        $data = array(
            'a'     => 'zone_check',
            'zones' => $zones
        );
        return $this->http_post($data);
    }

    /**
     * 3.5 - Pull Recent IPs Visiting Your Site
     * This function returns a list of IP address which hit your site classified by type.
     * $zoneid = ID of the zone you would like to check.
     * $hours = Number of hours to go back. Default is 24, max is 48.
     * $class = Restrict the result set to a given class. Currently r|s|t, for regular, crawler, threat resp.
     * $geo = Optional token. Add to add longitude and latitude information to the response. 0,0 means no data.
     */
    public function zone_ips($domain, $hours, $class, $geo = '0,0')
    {
        $data = array(
            'a'     => 'zone_ips',
            'z'     => $domain,
            'hours' => $hours,
            'class' => $class,
            'geo'   => $geo
        );
        return $this->http_post($data);
    }

    /**
     * 3.6 - Check The Threat Score For A Given IP
     * This function retrieves the current threat score for a given IP.
     * Note that scores are on a logarithmic scale, where a higher score indicates a higher threat.
     */
    public function threat_score($ip)
    {
        $data = array(
            'a'  => 'ip_lkup',
            'ip' => $ip
        );
        return $this->http_post($data);
    }

    /**
     * 3.7 - List All The Current Settings
     * This function retrieves all the current settings for a given domain.
     */
    public function zone_settings($domain)
    {
        $data = array(
            'a' => 'zone_settings',
            'z' => $domain
        );
        return $this->http_post($data);
    }
    
    /**
     * Undocumented method
     * SEE: https://github.com/vexxhost/CloudFlare-API/pull/3
     */
     public function zone_init($zone)
     {
         $data['a']    = 'zone_init';
         $data['z']    = $zone;
         return $this->http_post($data);
     }

    /**
     * CLIENT API
     * Section 4
     * Modify
     */

    /**
     * 4.1 - Set The Security Level
     * This function sets the Basic Security Level to I'M UNDER ATTACK! / HIGH / MEDIUM / LOW / ESSENTIALLY OFF.
     * The switches are: (help|high|med|low|eoff).
     */
    public function sec_lvl($domain, $mode)
    {
        $data = array(
            'a' => 'sec_lvl',
            'z' => $domain,
            'v' => $mode
        );
        return $this->http_post($data);
    }

    /**
     * 4.2 - Set The Cache Level
     * This function sets the Caching Level to Aggressive or Basic.
     * The switches are: (agg|basic).
     */
    public function cache_lvl($domain, $mode)
    {
        $data = array(
            'a' => 'cache_lvl',
            'z' => $domain,
            'v' => (strtolower($mode) == 'agg') ? 'agg' : 'basic'
        );
        return $this->http_post($data);
    }

    /**
     * 4.3 - Toggling Development Mode
     * This function allows you to toggle Development Mode on or off for a particular domain.
     * When Development Mode is on the cache is bypassed.
     * Development mode remains on for 3 hours or until when it is toggled back off.
     */
    public function devmode($domain, $mode)
    {
        $data = array(
            'a' => 'devmode',
            'z' => $domain,
            'v' => ($mode == true) ? 1 : 0
        );
        return $this->http_post($data);
    }

    /**
     * 4.4 - Clear CloudFlare's Cache
     * This function will purge CloudFlare of any cached files.
     * It may take up to 48 hours for the cache to rebuild and optimum performance to be achieved.
     * This function should be used sparingly.
     */
    public function fpurge_ts($domain)
    {
        $data = array(
            'a' => 'fpurge_ts',
            'z' => $domain,
            'v' => 1
        );
        return $this->http_post($data);
    }

    /**
     * 4.5 - Purge A Single File In CloudFlare's Cache
     * This function will purge a single file from CloudFlare's cache.
     */
    public function zone_file_purge($domain, $url)
    {
        $data = array(
            'a'   => 'zone_file_purge',
            'z'   => $domain,
            'url' => $url
        );
        return $this->http_post($data);
    }

    /**
     * 4.6 - Update The Snapshot Of Your Site
     * This snapshot is used on CloudFlare's challenge page
     * This function tells CloudFlare to take a new image of your site.
     * Note that this call is rate limited to once per zone per day.
     * Also the new image may take up to 1 hour to appear.
     */
    public function update_image($zoneid)
    {
        $data = array(
            'a'   => 'zone_grab',
            'zid' => $zoneid
        );
        return $this->http_post($data);
    }

    /**
     * 4.7a - Whitelist IPs
     * You can add an IP address to your whitelist.
     */
    public function wl($ip)
    {
        $data = array(
            'a'   => 'wl',
            'key' => $ip
        );
        return $this->http_post($data);
    }

    /**
     * 4.7b - Blacklist IPs
     * You can add an IP address to your blacklist.
     */
    public function ban($ip)
    {
        $data = array(
            'a'   => 'ban',
            'key' => $ip
        );
        return $this->http_post($data);
    }

    /**
     * 4.7c - Unlist IPs
     * You can remove an IP address from the whitelist and the blacklist.
     */
    public function nul($ip)
    {
        $data = array(
            'a'   => 'nul',
            'key' => $ip
        );
        return $this->http_post($data);
    }

    /**
     * 4.8 - Toggle IPv6 Support
     * This function toggles IPv6 support.
     */
    public function ipv46($domain, $mode)
    {
        $data = array(
            'a' => 'ipv46',
            'z' => $domain,
            'v' => ($mode == true) ? 1 : 0
        );
        return $this->http_post($data);
    }

    /**
     * 4.9 - Set Rocket Loader
     * This function changes Rocket Loader setting.
     */
    public function async($domain, $mode)
    {
        $data = array(
            'a' => 'async',
            'z' => $domain,
            'v' => $mode
        );
        return $this->http_post($data);
    }

    /**
     * 4.10 - Set Minification
     * This function changes minification settings.
     */
    public function minify($domain, $mode)
    {
        $data = array(
            'a' => 'minify',
            'z' => $domain,
            'v' => $mode
        );
        return $this->http_post($data);
    }


    /**
     * CLIENT API
     * Section 5
     * DNS Record Management
     */

    /**
     * 5.1 - Add A New DNS Record
     * This function creates a new DNS record for a zone.
     * See http://www.cloudflare.com/docs/client-api.html#s5.1 for documentation.
     */
    public function rec_new($domain, $type, $name, $content, $ttl = 1, $mode = 1, $prio = 1, $service = 1, $srvname = 1, $protocol = 1, $weight = 1, $port = 1, $target = 1)
    {
        $data = array(
            'a'       => 'rec_new',
            'z'       => $domain,
            'type'    => $type,
            'name'    => $name,
            'content' => $content,
            'ttl'     => $ttl
        );
        if (in_array($type, self::$MODE_SERVICE))
            $data['service_mode'] = ($mode == true) ? 1 : 0;
        else if (in_array($type, self::$PRIO)) {
            $data['prio'] = $prio;
            if ($type == 'SRV') {
                $data = array_merge($data, array(
                    'service'  => $service,
                    'srvname'  => $srvname,
                    'protocol' => $protocol,
                    'weight'   => $weight,
                    'port'     => $port,
                    'target'   => $target
                ));
            }
        }
        return $this->http_post($data);
    }

    /**
     * 5.2 - Edit A DNS Record
     * This function edits a DNS record for a zone.
     * See http://www.cloudflare.com/docs/client-api.html#s5.1 for documentation.
     */
    public function rec_edit($domain, $type, $id, $name, $content, $ttl = 1, $mode = 1, $prio = 1, $service = 1, $srvname = 1, $protocol = 1, $weight = 1, $port = 1, $target = 1)
    {
        $data = array(
            'a'       => 'rec_edit',
            'z'       => $domain,
            'type'    => $type,
            'id'      => $id,
            'name'    => $name,
            'content' => $content,
            'ttl'     => $ttl
        );
        if (in_array($type, self::$MODE_SERVICE))
            $data['service_mode'] = ($mode == true) ? 1 : 0;
        else if (in_array($type, self::$PRIO)) {
            $data['prio'] = $prio;
            if ($type == 'SRV') {
                $data = array_merge($data, array(
                    'service'  => $service,
                    'srvname'  => $srvname,
                    'protocol' => $protocol,
                    'weight'   => $weight,
                    'port'     => $port,
                    'target'   => $target
                ));
            }
        }
        return $this->http_post($data);
    }

    /**
     * 5.3 - Delete A DNS Record
     * This function deletes a DNS record for a zone.
     * $zone = zone
     * $id = The DNS Record ID (Available by using the rec_load_all call)
     * $type = A|CNAME
     */
    public function delete_dns_record($domain, $id)
    {
        $data = array(
            'a'  => 'rec_delete',
            'z'  => $domain,
            'id' => $id
        );
        return $this->http_post($data);
    }


    /**
     * GLOBAL API CALL
     * HTTP POST a specific task with the supplied data
     */
    private function http_post($data, $type = 'USER')
    {
        $data['u']   = /*##REDACTED###*/;
        $data['tkn'] = /*###REDACTED###*/;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_URL, self::$URL[$type]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $http_result = curl_exec($ch);
        $error       = curl_error($ch);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code != 200) {
            return array(
                'error' => $error
            );
        } else {
            return json_decode($http_result);
        }
    }
}
?>
