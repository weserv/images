<?php
/**
 * This class helps getting the DNS information of any site with an
 * help of a dns server, and re-directs it self to the name server
 * of the specific web site and gets more information
 * 
 * @author G.Kamalakar (gKodes)
 * @package gKodes Networking
 * @name gDNS
 * @version 0.4 RC 1
 * @example example.php
 * @lastUpdated Feb,10 2008
 */

/**
 * Definations for record output sorting.
 * This defines to generate as the old format of output, which was generated in 0.3
 */
define( "GDNS_SORT_OLD", 3021 );
/**
 * Definations for record output sorting.
 * This defines to generate similar output as php's dns_get_record.
 */
define( "GDNS_SORT_PHP", 3022 );

class gDNS {

	/**
	 * @access pivate
	 * @var array
	 */
     private $qtypes = array( "A" => 1, "NS" => 2, "ND" => 3, "NF" => 4, "CNAME" => 5, "SOA" => 6,
         "MB" => 7, "MG" => 8, "MR" => 9, "NULL" => 10, "WKS" => 11, "PTR" => 12, "HINFO" => 13,
         "MINFO" => 14, "MX" => 15, "TXT" => 16, "RP" => 17, "SIG" => 24, "KEY" => 25, "LOC" => 29,
         "NXT" => 30, "AAAA" => 28, "SRV" => 33, "CERT" => 37, "A6" => 38, "AXFR" => 252,
         "IXFR" => 251, "*" => 255 );

	/**
	 * @access pivate
	 * @var array
	 */
     private $qclass = array( "IN" => 1, "CS" => 2, "CH" => 3, "HS" => 4, "*" => 255 );

	/**
	 * Contains all the log's
	 * 
	 * @access public
	 * @var array
	 */
     public $t_log = "";

	/**
	 * @access pivate
	 * @var array
	 */
     private $_responsers = array();

	/**
	 * @access pivate
	 * @var array
	 */
     private $_dnsip = "192.33.4.12";

	/**
	 * @access pivate
	 * @var array
	 */
     private $dnsip_v6 = "2001:0503:a83e:0000:0000:0000:0002:0030";

	/**
	 * @access pivate
	 * @var string
	 */
     private $datagram;

	/**
	 * @access pivate
	 * @var bool
	 */
     private $is_ipv6 = false;

	/**
	 * Contains the current processed domain name, Usefull when CNAME is present.
	 * 
	 * @access public
	 * @var array
	 */
     public $cur_domain = "";

	/**
	 * @access pivate
	 * @var bool
	 */
     private $cname_rider = false;


    /**
 	 * Constructor function for gDNS
 	 * 
 	 * $ipv6 is a bool that specfies to use IPv6, if enabled the class would only use IPv6
 	 * ip addresses only.
 	 * $cname is a boolean specfies , to re-direct if a CNAME is found.
 	 * $def_dns is the default DNS server the querys are sent to,  this may be a domain name
 	 * or IP address of a DNS server.
	 *
 	 * @access public
 	 * @param bool $ipv6
 	 * @param bool $cname
 	 * @param string $def_dns
 	 * @return gDNS
	 */
	function gDNS( $ipv6 = false, $cname = false, $def_dns = "" ) {
		$this->cname_rider = $cname;
		if( strlen($def_dns) > 0 ) {
			if( $ipv6 ) {
				$this->dnsip_v6 = $def_dns;
				} else{
				$this->dnsip = $def_dns;
			}
		}
		$this->is_ipv6 = $ipv6;
    }

	/**
	 * Query for an DNS record
	 * 
	 * $domain is the domain name for which you want to get the DNS records for.
	 * $type is the type of DNS records you want for example : NS => Name Server , MX => Mail Exchange
	 * $auth is a boolean sepecfing that you want the request for the Authoritative DNS server of
	 * the specfic domain name.
	 * $class is the class in which you what the records for example : IN => Internet
	 * $server is the dns server you want to use for the current query, this may be a domain name
	 * or IP address of a DNS server.
	 *
	 * @access public
	 * @param string $domain
	 * @param string $type
	 * @param bool $auth
	 * @param string $class
	 * @param string $server
	 * @return bool
	 */
    public function query( $domain, $type = "*" , $auth = true , $class = "IN", $server = null ) {
        $response = $this->packetBuilder( $domain, $type, $class, $server, array( "opc" => 0 ) );
         if ( $response && is_array( $response ) && count( $response ) == 3 ) {
            /* loop for Authoritative Answer response.  */
            while ( is_array( $response ) && $auth && !$response["Header"]["AA"]
                 && $response["Request"] != $domain ) {
                 	$server = $this->getDnsServer( $response["Records"] );
					$this->t_log[] = "Request redirected to $server for records of $domain";
                 $response = $this->packetBuilder( $domain, $type, $class, $server, array("opc"=>0) );
            }
            if( $this->cname_rider && ( $new_domain = $this->CnameReDir($response["Records"]) ) ) {
            	$server = $this->getDnsServer( $response["Records"] );
            		$this->clearRecords($domain);
            		$this->cur_domain = $new_domain;
            	return $this->Query($new_domain, $type, $auth, $class, $server);
            }
            if ( is_array( $response ) ) {
                $this->_responsers[$domain] = $response;
                $this->cur_domain = $domain;
                return true;
                }
            }
        $this->t_log[] = "Failed to get DNS Records.";
        return false;
	}

	/**
	 * Used to build request packed and get the response.
	 *
	 * @access private
	 * @param string $domain
	 * @param specfies $type
	 * @param specfies $class
	 * @param specfies $server
	 * @param array $flags
	 * @return bool|array
	 */
    private function packetBuilder( $domain, $type = "*", $class = "IN", $server = null, $flags = array() ) {
         if ( strlen( $domain ) > 0 && $this->isValidDomain( $domain ) ) {
            $response = array();
             $id = chr( rand( 1, 255 ) ) . chr( rand( 1, 255 ) );
             $this->recivedPack = null;
             $query = $id . chr( ( ( $flags['opc'] << 3 ) | 1 ) ) . chr( 0 ) . chr( 0 ) . chr( 1 ) .
             chr( 0 ) . chr( 0 ) . chr( 0 ) . chr( 0 ) . chr( 0 ) . chr( 0 );
             foreach( explode( ".", $domain ) as $uri ) {
                $query .= chr( strlen( $uri ) ) . $uri;
                 }
            $query .= chr(0).chr(0).chr( $this->qtypes[$type] ).chr(0).chr( $this->qclass[$class] );
             $server = $this->getDnsServer( $server );
             if ( $server === false ) {
                return false;
                 }
            $this->t_log[] = "Requesting $server for records of $domain";
             $dns_server = fsockopen( "udp://" . $server, 53 );
             stream_set_timeout( $dns_server, 10 );
             if ( $dns_server ) {
                fwrite( $dns_server, $query );
                 if ( ( $recivedPack = @fread( $dns_server, 1 ) ) !== false ) {
                    $str_info = stream_get_meta_data( $dns_server );
                     if ( $str_info['unread_bytes'] > 0 && !$str_info['timed_out'] ) {
                        $recivedPack .= fread( $dns_server, $str_info["unread_bytes"] );
                         // file_put_contents("packet.dunp", $recivedPack);
                        $response = $this->responseParser( $recivedPack, true );
                         }else if ( $str_info['timed_out'] ) {
                        $this->t_log[] = "Connection timed out.";
                         return false;
                         }
                    }
                 $str_info = stream_get_meta_data( $dns_server );
                 fclose( $dns_server );
                 return $response;
                 }else {
                $this->t_log[] = "Failed to establish connection with the Server.";
                 }
            }else {
            $this->t_log[] = "Invalid Domain Name.";
             }
        return false;
	}
    
    /**
	 * Process the responce header
	 *
	 * @access private 
	 * @return array 
	 */
    private function Header_processor( $packet ) {
         $head = sprintf( "%08d", decbin( ord($packet{2}) ) ) .
         								 sprintf( "%08d", decbin( ord($packet{3}) ) );
         $info = array( 
            "ID" => substr( $this->recivedPack, 0, 2 ),
             "QR" => ( bool )$head{0},
             "OPCODE" => bindec( substr( $head, 1, 4 ) ),
             "AA" => ( bool )$head{5},
             "TC" => ( bool )$head{6},
             "RD" => ( bool )$head{7},
             "RA" => ( bool )$head{8},
             "Z" => bindec( substr( $head, 9, 3 ) ),
             "RCODE" => bindec( substr( $head, -1, 4 ) ),
             "QDCOUNT" => $this->bit8to16( substr( $packet, 4, 2 ) ),
             "ANCOUNT" => $this->bit8to16( substr( $packet, 6, 2 ) ),
             "NSCOUNT" => $this->bit8to16( substr( $packet, 8, 2 ) ),
             "ARCOUNT" => $this->bit8to16( substr( $packet, 10, 2 ) )
             );
        return $info;
    }
    
    /**
	 * Returns the lable at the specfic position sepcfide.
	 *
	 * @access private 
	 * @param int $loc
	 * @param string $packet 
	 */
    private function GetLable( &$loc = 0 ) {
        $lable = array();
        $loc_c = $loc;
         if ( is_string( $this->datagram ) && strlen( $this->datagram ) > 0 ) {
            while ( ord( $this->datagram { $loc }) != 0 ) {
                if ( ( ( ord( $this->datagram { $loc }) ^ 192 ) <= 63 ) ) {
                    $ploc = $this->bit8to16( ( chr( ( ord( $this->datagram{$loc}) ^ 192 ) )
                             . $this->datagram{ $loc + 1 }) ); $loc += 2;
                     return array_merge( $lable, $this->GetLable( $ploc ) );
                     }else {
                    $lable[] = substr( $this->datagram, $loc + 1, ord( $this->datagram{$loc}) );
                    $loc += ord( $this->datagram{$loc}) + 1;
                    }
                }
            if ( count( $lable ) > 0 ) {
                $this->labels[$loc_c] = $lable;
                 }
            }
        return $lable;
	}

	/**
	 * Processes the response from the DNS server.
	 *
	 * @access private
	 * @param string $packet
	 * @return array
	 */
    private function responseParser( $packet ) {
        $i = 12;
        $this->datagram = $packet;
        $this->labels = array();
        $header = $this->Header_processor( $packet );
        $res_info = array( 
            "Header" => $header,
            "Records" => array()
        );
         if ( $header['RCODE'] == 0
             && ( $header['ANCOUNT'] > 0 || $header['ARCOUNT'] > 0 || $header['NSCOUNT'] > 0 ) ) {
            $res_info["Request"] = $this->readQuestion( $i );
             $len = strlen( $packet );
             for ( ; $i < $len; $i += 10 ) {
                $domain = $this->GetLable( $i );
                 $type = $this->bit8to16( substr( $packet, $i , 2 ) );
                 $class = $this->bit8to16( substr( $packet, $i + 2 , 2 ) );
                 $ttl = $this->bit16to32( substr( $packet, $i + 4 , 4 ) );
                 $rdlength = $this->bit8to16( substr( $packet, $i + 8 , 2 ) );
                 $rdata = substr( $packet, $i + 10, $rdlength );
                 $record = array( 
                    "Domain" => implode( ".", $domain ),
                     "Type" => array_search( $type , $this->qtypes ),
                     "Class" => array_search( $class, $this->qclass ),
                     "Ttl" => $ttl,
                     "Record" => $this->RData( $type, $i + 10, $i + 10 + $rdlength )
                     );
                 $i += strlen( $rdata );
                 $res_info["Records"][] = $record;
                 }
            return $res_info;
             }else {
            $this->t_log[] = "There might be a problum in the request data, or the server had".
            				 " responded with zero records as response.";
    	}
    	return false;
	}

	/**
	 * Returns the full response with Header, Request and Records (in GDNS_SORT_PHP format) of the
	 * specfied domain.
	 *
	 * @access public
	 * @param string $domain
	 * @return array
	 */
    public function getResponse( $domain ) {
		if ( array_key_exists( $domain, $this->_responsers ) ) {
			return $this->_responsers[$domain];
		}
		return false;
	}

	/**
	 * Returns the response records of the specfied domain.
	 *
	 * @access public
	 * @param string $domain
	 * @param int $sort_id
	 * @return array
	 */
    public function getRecords( $domain, $sort_id = GDNS_SORT_OLD ) {
         if ( !array_key_exists( $domain, $this->_responsers ) ) {
            return false;
             }
        if ( $sort_id == GDNS_SORT_PHP ) {
            return $this->_responsers[$domain]["Records"];
             }else if ( $sort_id == GDNS_SORT_OLD ) {
            $records = array();
             foreach ( $this->_responsers[$domain]["Records"] as $record ) {
                if ( !key_exists( $record["Domain"], $records ) ) {
                    $records[$record["Domain"]] = array();
                     }
                if ( !key_exists( $record["Class"], $records[$record["Domain"]] ) ) {
                    $records[$record["Domain"]][$record["Class"]] = array();
                     }
                if ( !key_exists( $record["Type"], $records[$record["Domain"]][$record["Class"]] ) ) {
                    $records[$record["Domain"]][$record["Class"]][$record["Type"]] = array();
                     }
                $records[$record["Domain"]][$record["Class"]][$record["Type"]][] = array( 
                    "TTL" => $record["Ttl"],
                     "Responce" => $record["Record"]
                     );
                 }
            return $records;
    	}
    	return false;
	}
    
    /**
	 * Process RR record Data.
	 *
	 * @access private 
	 * @param string $type 
	 * @param string $data 
	 * @return mixed 
	 */
    private function RData( $type, $ind, $len ) {
        $return = null;
        switch ( $type ) {
        case 1:/* A */
             $return = array();
             for( ; $ind < $len; $ind++ ) {
                $return[] = ord( $this->datagram{$ind});
                 };
             $return = implode( '.', $return );
             break;
         case 2:/* NS */
         case 3:/* MD */
         case 4:/* MF */
         case 5:/* CNAME */
             $return = implode( '.', $this->GetLable( $ind ) );
             break;
         case 6:/* SOA */
             $return = array();
             $return['MNAME'] = implode( '.', ( $this->GetLable( $ind ) ) );
             $return['RNAME'] = $this->toMail( ( $this->GetLable( $ind ) ) );
             $return['SERIAL'] = $this->bit16to32( substr( $this->datagram, $ind, 4 ) );
             $return['REFRESH'] = $this->bit16to32( substr( $this->datagram, $ind + 4, 4 ) );
             $return['RETRY'] = $this->bit16to32( substr( $this->datagram, $ind + 8, 4 ) );
             $return['EXPIRE'] = $this->bit16to32( substr( $this->datagram, $ind + 12, 4 ) );
             $return['MINIMUM'] = $this->bit16to32( substr( $this->datagram, $ind + 16, 4 ) );
             break;
         case 7:/* MB */
         case 8:/* MG */
         case 9:/* MR */
             $return = $this->toMail( ( $this->GetLable( $ind ) ) );
             break;
         case 10:/* NULL */
             $return = "";
             break;
         case 11:/* WKS */
             $return = array( 
                "address" => $this->RData( 1, $ind, 4 ),
                 "protocall" => ord( $this->datagram {
                        $ind + 5}
                    ),
                 "bitmap" => substr( $this->datagram, $ind + 6, $len - ( 6 + $ind ) )
                 );
             break;
         case 12:/* PTR */
             $return = implode( '.', ( $this->GetLable( $ind ) ) );
             break;
         case 13:/* HINFO */
             $i = $this->GetLable( $ind );
             $return = array( 
                "cpu" => $i[0],
                 "os" => $i[1]
                 );
             break;
         case 14:/* MINFO */
             $return = array( 
                "RMAILBX" => $this->toMail( $this->GetLable( $ind ) ),
                 "EMAILBX" => $this->toMail( substr( $this->datagram, $ind, $len - $ind ) )
                 );
             break;
         case 15:/* MX */
             $ind += 2;
             $return = array( 
                "prefrence" => $this->bit8to16( $this->datagram {
                        $ind-2}
                    . $this->datagram {
                        $ind-1}
                    ),
                 "domain" => implode( '.', $this->GetLable( $ind ) )
                 );
             break;
         case 16:/* TXT */
             $return = implode( '', $this->GetLable( $ind ) );
             break;
         case 17:/* RP */
             $return = $return = array( 
                "mbox" => $this->toMail( $this->GetLable( $ind ) ),
                "txtdname" => implode( '.', $this->GetLable( $ind ) )
                );
             break;
         case 28:/* AAAA */
             $return = array();
             for( ; $ind < $len; $ind += 2 ) {
                $return[] = sprintf( "%02s", dechex( ord( $this->datagram{$ind}) ) )
                 			. sprintf( "%02s", dechex( ord( $this->datagram {$ind + 1}) ) );
                 };
             $return = implode( ':', $return );
             break;
         case 29:/* LOC rfc1876 */
             $lat = sprintf( "%u", $this->bit16to32( substr( $this->datagram, $ind + 4, 4 ) ) );
             $lon = sprintf( "%u", $this->bit16to32( substr( $this->datagram, $ind + 8, 4 ) ) );
             $return = array( 
                "size" => sprintf( "%01.2fm", $this->Loc_8_4( $this->datagram{$ind + 1}) / 100 ),
                 "horiz_pre" => sprintf( "%01.2fm", $this->Loc_8_4( $this->datagram {$ind + 2}) / 100 ),
                 "vert_pre" => sprintf( "%01.2fm", $this->Loc_8_4( $this->datagram {$ind + 3}) / 100 ),
                 "latitude" => implode( ":", $this->Loc_dms( $lat ) ) . " "
                 										. ( ( $lat > pow( 2, 31 ) )? "N" : "S" ),
                 "longitude" => implode( ":", $this->Loc_dms( $lon ) ) . " "
                 										. ( ( $lon > pow( 2, 31 ) )? "E" : "W" ),
                 "altitude" => sprintf( "%01.2fm",
                     ( ( $this->bit16to32( substr( $this->datagram, 12, 4 ) )-10000000 ) / 100 ) )
                 );
             break;
         case 33:/* SRV rfc2782 */
             $return = array( 
                "priority" => $this->bit8to16( substr( $this->datagram, $ind, 2 ) ),
                 "weight" => $this->bit8to16( substr( $this->datagram, $ind + 4, 2 ) ),
                 "port" => $this->bit8to16( substr( $this->datagram, $ind + 6, 2 ) ),
                 "target" => implode( '.', substr( $this->datagram, $ind + 7, $len-7 ) )
                 );
             break;
         default:
             $return = substr( $this->datagram, $ind, $len-$ind );
             }
        return $return;
	}

	/**
	 * Used for convertion the lable array to an valid mail id.
	 *
	 * @access private
	 * @param array $domain
	 * @return string
	 */
    private function toMail( $domain ) {
         $mail = $domain[0] . "@";
         unset( $domain[0] );
         return $mail . implode( ".", $domain );
	}

	/**
	 * Additional function to process LOC record
	 *
	 * @access private
	 * @param int $raw_sec
	 * @return array
	 */
    private function Loc_dms( $raw_sec ) {
         $abs = abs( abs( $raw_sec ) - pow( 2, 31 ) );
         $deg = ( int ) ( $abs / ( 60 * 60 * 1000 ) );
         $abs -= $deg * ( 60 * 60 * 1000 );
         $min = ( int ) ( $abs / ( 60 * 1000 ) );
         $abs -= $min * ( 60 * 1000 );
         $sec = ( int ) ( $abs / 1000 );
         $abs -= $sec * 1000;
         $msec = $abs;
         return array( 
            "dec" => $deg,
             "min" => $min,
             "sec" => $sec . '.' . sprintf( "%03d", $msec )
		);
    }

    /**
	 * Additional function to process LOC record
	 *
	 * @access private
	 * @param string $data
	 * @return float
	 */
    private function Loc_8_4( $data ) {
         $size = decbin( ord( $data ) );
         return ( bindec( substr( $size, 0, strlen( $size )-4 ) ) * 
														pow( 10, bindec( substr( $size, -4 ) ) ) );
    }

    /**
     * Reads the Question Session from the response data.
     *
     * @access privae
     * @param string $loc
     * @return array
     */
    private function readQuestion( &$loc ) {
	$qu = array( 
    	"QNAME" => implode( ".", $this->GetLable( $loc ) ),
    	"QTYPE" => array_search( $this->bit8to16( substr( $this->datagram, $loc + 1 , 2 ) ) 
    																			, $this->qtypes ),
		"QCLASS" => array_search( $this->bit8to16( substr( $this->datagram, $loc + 3 , 2 ) )
																				, $this->qclass )
			);
			$loc += 5;
		return $qu;
	}

	/**
	 * Return the NS records from the given record set
	 *
	 * @access private
	 * @param array $records
	 * @param bool $ipv6
	 * @return array
	 */
    private function getNS( $records, $ipv6 = false ) {
         $ns = array();
         foreach ( $records as $record ) {
            if ( $record["Type"] == "NS" ) {
                $ns[$record["Record"]] = $record["Record"];
                 } else if ( array_key_exists( $record["Domain"], $ns ) &&
                     ( ( !$ipv6 && $record["Type"] == "A" ) || ( $ipv6 && $record["Type"] == "AAAA" ) ) ) {
                if ( $record["Type"] == "A" ) {
						$ns[$record["Domain"]] = $record["Record"];
                     }else {
						$ns[$record["Domain"]] = "[" . $record["Record"] . "]";
                     }
                }
            }
        return $ns;
	}

	/**
	 * Return a valid DNS server IP.
	 *
	 * @access private
	 * @param string $server
	 * @return string
	 */
    private function getDnsServer( $server ) {
		if ( is_array( $server ) ) {
			$ns = $this->getNS( $server, $this->is_ipv6 );
			if( count($ns) > 0 ) {
				$ns_name = array_keys( $ns );
					$serv_id = rand( 0, count( $ns )-1 );
				return ( $ns[$ns_name[$serv_id]] === false )? 
							$ns_name[$serv_id] : $ns[$ns_name[$serv_id]];
			}
			return false;
		}
        if ( $server && !$this->isIP( $server ) ) {
			$this->Query( $server, "A", true );
			$records = $this->getRecords( $server, GDNS_SORT_PHP );
			$this->clearRecords( $server );
			if ( $records ) {
				$type = ( ($this->is_ipv6)? "AAAA" : "A" ) ;
                foreach ( $records as $record ) {
                    if ( $record["Type"] == $type && $record["Domain"] == $server ) {
                        return $record["Record"];
                         }
                    }
                }
                $this->t_log[] = "Failed to find a valid ipV".
            		    ( ($this->is_ipv6)? '6' : '4' )." Supported Name Server";
            return false;
             }
        return ( ( $server )? $server : 
        					( ($this->is_ipv6)? "[".$this->dnsip_v6."]" :  $this->_dnsip ) );
	}

	/**
	 * Checks for a CNAME, if found will return the CNAME or else returns false.
	 *
	 * @access private
	 * @return bool|string
	 */
	private function CnameReDir($records) {
		if( is_array($records) && count($records) > 0 ) {
		foreach ( $records as $record ) {
				if( $record["Type"] == "CNAME" ) {
						$this->t_log[] = "CNAME present, re-directed to " . $record["Record"];
						$this->cur_domain = $record["Record"];
					return $record["Record"];
				}
			}
		}
		return false;
	}

	/**
	 * Removes the records of the specfied domain.
	 *
	 * @access public
	 * @param string $domain
	 */
    public function clearRecords( $domain ) {
         unset( $this->_responsers[$domain] );
	}

	/**
	 * Validates if the given $ip is a valid IPv4 or IPv6 IP address.
	 *
	 * @access public
	 * @param string $ip
	 * @param bool $ip6
	 * @return bool
	 */
    public function isIP( $ip , $ip6 = false ) {
         $ipv4 = "/^(25[0-5]|2[0-4]\d|[0-1]?\d?\d)(\.(25[0-5]|2[0-4]\d|[0-1]?\d?\d)){3}$/";
         $ipv6 = "/^(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/";
         return ( preg_match( ( ( $ip6 )? $ipv6 : $ipv4 ) , $ip ) > 0 )? true : false;
	}

	/**
	 * Validates if the given $domain is an valid domain.
	 *
	 * @access public
	 * @param string $doamin
	 * @return bool
	 */
    public function isValidDomain( $doamin ) {
         $dreg = "/^([a-zA-Z0-9]([a-zA-Z0-9\\-]{0,61}[a-zA-Z0-9])?\\.)+[a-zA-Z]{2,6}$/";
         return ( preg_match( $dreg, $doamin ) >= 1 )? true : false;
	}
    
    /**
	 * Converts 8bit to 16bit data.
	 *
	 * @access private 
	 * @param array $char 
	 * @return int 
	 */
    private function bit8to16( $char ) {
         return ( int ) ( ord( $char{0}) << 8 | ord( $char{1}) );
	}
    
    /**
	 * Converts 16bit to 32bit data.
	 *
	 * @access private 
	 * @param array $char 
	 * @return double 
	 */
    private function bit16to32( $char ) {
         return ( double ) ( $this->bit8to16( $char{0}. $char{1}) << 16 
         										| $this->bit8to16( $char{2}. $char{3}) );
	}
}
?>