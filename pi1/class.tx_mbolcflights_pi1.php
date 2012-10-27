<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Martin Becker <vbmazter@web.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

// require_once(PATH_tslib . 'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath("tp_test") . 'pi1/olc_reader.php.inc');

/**
 * Plugin 'OLC Flights' for the 'mb_olc_flights' extension.
 *
 * @author	Martin Becker <vbmazter@web.de>
 * @package	TYPO3
 * @subpackage	tx_mbolcflights
 */
class tx_mbolcflights_pi1 extends tslib_pibase {
	public $prefixId      = 'tx_mbolcflights_pi1';		// Same as class name
	public $scriptRelPath = 'pi1/class.tx_mbolcflights_pi1.php';	// Path to this script relative to the extension dir.
	public $extKey        = 'mb_olc_flights';	// The extension key.

        /*
         * cHash: each URL contains the cHash variable.
         * This is a hash of the params in piVars[]. Also it is already checked 
         * for authenticity, i.e. it is not forged by the user, but the parameters
         * are coming from myself.
         * if the URL has no cHash set, then caching is disabled by the base class.
         * 
         * cHash links are created by typolink()
         */
        public $pi_USER_INT_obj=0;      // no USER_INT (uncached) object
	public $pi_checkCHash = TRUE;  // this is a USER plugin: caching for output of same parameters -> because the remote content may have changed.
	var $template;
        var $pid;
        
        var $limit;      // if set, then only the 'limit' most recent flights are shown
        var $days;       // if set, then all flights now - days are shown
        var $lastday;    // if true, then all recent flights from the last day are shown
        var $curdate;
        var $country;
        var $club;
        var $year;
        var $olctype;
        var $olc_baseurl;
        var $olc_fetchurl;
        var $best;      // if set, then the best flight is rendered
        var $mode;      // plugin mode \in {'item','list'}.
        var $mapping_link;  // assoc. array, maps a/c names to links
        var $mapping_img;   // assoc. array, maps a/c names to images
        var $img_maxw;
        var $img_maxh;
        
    
	/**
	 * @brief The main method of the Plugin.
	 * @param string $content The Plugin content
	 * @param array $conf The Plugin configuration
	 * @return string The content that is displayed on the website
	 */
	public function main($content, array $conf) {
		$this->conf = $conf;
                $this->pi_initPIflexForm(); // Init and get the flexform data of the plugin
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL(); // language file
		//$this->pi_USER_INT_obj = 1;  // if this is active, then even within a cached page the plugin is not cached                                                
                
                // sanitize all parameters to avoid security problems.
                $errmsg="";
                if (!$this->_get_and_validate_params($errmsg)) {
                    return $this->pi_wrapInBaseClass("<div style='color:#f00;font-weight:bold;'>$this->prefixId: " . $errmsg . "</div>");
                }      
                //var_dump($this->conf);
                
                /*************************************************************
                 *  XXX: never-ever use piVars or conf anymore from here!!!
                 *  only use the sanitized class attributes.
                 *************************************************************/
                
                // computed values                            
                $this->curdate = getdate();
                $this->year=$this->curdate['year'];
                $this->olc_fetchurl= $this->olc_baseurl . '/flightsOfClub.html?cc=' . $this->club . '&st=' . $this->olctype . '&rt=olc&c=' . $this->country . '&sc=th&sp=' . $this->year . '&paging=100000';
                
                // ### switch plugin mode: list view or single view?
                if($this->mode=='detail') {
                    $content = $this->_detail_view();                    
                } else {
                    $content = $this->_list_view();                                        
                }  

                //echo "live";    // DEBUG...just to check caching
		return $this->pi_wrapInBaseClass($content);
	}
        
        /**
         * @brief merge flexform data into $this->config
         */
        function _config() {
            if (is_array($this->cObj->data['pi_flexform']['data'])) { // if there are flexform values
                foreach ($this->cObj->data['pi_flexform']['data'] as $key => $value) { // every flexform category
                    if (count($this->cObj->data['pi_flexform']['data'][$key]['lDEF']) > 0) { // if there are flexform values
                        foreach ($this->cObj->data['pi_flexform']['data'][$key]['lDEF'] as $key2 => $value2) { // every flexform option
                            if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key2, $key)) { // if value exists in flexform
                                $this->conf[$key][$key2] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key2, $key); // overwrite $this->conf
                            }
                        }
                    }
                }
            }
        }
        
        /**
         * @brief returns true if all parameters are okay, else false. 
         * In the latter case the parameter errmsg will hold a description
         * @param type $errmsg
         */
        function _get_and_validate_params(&$errmsg) {
            $errmsg="";

            $this->_config();            
            /************************************************
             *   FROM CONFIGURATION
             ************************************************/            
            $this->pid=$GLOBALS['TSFE']->pid; // page id (trusted)
            
            // HTML template (relative to PATH_site) FIXME: is this secure?
            //$tfile = t3lib_div::getFileAbsFileName($this->conf['template'],1,0);
            $tfile = $this->conf['template'];
            if ($tfile) {
                // custom template
                $this->template=$this->cObj->fileResource($tfile);                                          
            } else {
                // revert to default
                $this->template=$this->cObj->fileResource('EXT:mb_olc_flights/res/template.html');                                          
            }            

            // club ID
            if (!$this->_assign_int($this->club, $this->conf['club'],FALSE)) $errmsg .= "Club ID invalid.<br>";
            
            // img_maxw    
            if (empty($this->conf['img_maxw'])) {
                $this->img_maxw = 140;  // default
            } else {
                if (!$this->_assign_int($this->img_maxw, $this->conf['img_maxw'], FALSE)) {
                    $errmsg .= "Parameter 'img_maxw' invalid.<br>";
                }
            }
            
            // img_maxh 
            if (empty($this->conf['img_maxh'])) {
                $this->img_maxh = 140;  // default
            } else {
                if (!$this->_assign_int($this->img_maxh, $this->conf['img_maxh'], FALSE)) {
                    $errmsg .= "Parameter 'img_maxh' invalid.<br>";
                }
            }
            
            // country    
            if (empty($this->conf['country'])) {
                $this->country = 'DE';  // default
            } else {
                if (!$this->_assign_string($this->country, $this->conf['country'], '/^\w+$/')) {
                    $errmsg .= "Parameter 'country' invalid.<br>";
                }
            }
            
            // olc type \in {'olc-league', 'olcp'}
            if (empty($this->conf['olctype'])) {
                $this->olctype = 'olcp';    // default
            } else {
                if (!$this->_assign_string($this->olctype, $this->conf['olctype'], '/^((olcp)|(olc-league))$/')) {
                    $errmsg .= "Parameter 'olctype' invalid.<br>";
                }
            }
            
            // URL to olc...user or default
            if (t3lib_div::isValidUrl($this->conf['baseurl'])) {
                $this->olc_baseurl = $this->conf['baseurl'];
            } else {
                $this->olc_baseurl = 'http://www.onlinecontest.org/olc-2.0/gliding'; // http://www.onlinecontest.org/olc-2.0/gliding/flightsOfClub.html?sp=2012&c=DE&sc=th&rt=olc&st=olcp&cc=1677&paging=100000
            }
            
            // ### mapping of A/C names to links and images ###
            $ac_mapping = $this->conf['ac_mapping.'];
            // todo: add default mappings
            $this->mapping_img = array();
            $this->mapping_link = array();
                        
            if (!empty($ac_mapping)) {                               
                foreach ($ac_mapping as $ac_key => $value) {    
                    if(is_array($value)) {
                        $this->_assign_string($acname, $value['name']);                        
                        if (!empty($acname)) {
                            // image must be local on the server
                            $this->_assign_localfile_rel($img, $value['img']);
                            // link can be either a URL ...
                            $this->_assign_url($link, $value['link']);
                            // ... or a local page
                            if (empty($link)) $this->_assign_localurl($link, $value['link']);
                            // ... if it is still empty then the input is not accepted
                            
                            // finally put into assoc array
                            if (!empty($link)) $this->mapping_link[$acname] = $link;
                            if (!empty($img)) $this->mapping_img[$acname] = $img;                    
                        }
                    }
                }
            }            
            //var_dump($this->mapping_img, $this->mapping_link);
                                    
            /************************************************
             *   FROM FLEXFORM
             ************************************************/
            if (!$this->_assign_int($this->days, $this->conf['settings.main']['days'])) $errmsg .= "Parameter 'days' invalid.<br>";                        
            if (!$this->_assign_int($this->lastday, $this->conf['settings.main']['lastday'])) $errmsg .= "Parameter 'lastday' invalid.<br>";   
            if (!$this->_assign_int($this->limit, $this->conf['settings.main']['limit'])) $errmsg .= "Parameter 'limit' invalid.<br>";                                    
            if (!$this->_assign_int($this->best, $this->conf['settings.main']['show_best'])) $errmsg .= "Parameter 'best' invalid.<br>";            
                 
            // plugin mode
            if (!empty($this->piVars['item'])) {
                $this->mode = 'item';
            } else {
                $this->mode = 'list';
            }
            
            if (empty($errmsg)) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
        
        /**
         * @brief assign string which matches a regexp, else default string
         * @param ref $tar assignee
         * @param string $str source string
         * @param string $filter_regexp regular expression which $str must satisfy
         * @return bool true if matched, else false
         */
        function _assign_string(&$tar, $str, $filter_regexp="/.*/") {
            // test match
            $str=htmlspecialchars(trim($str));
            $matches=array();
            $match = preg_match ( $filter_regexp , $str, $matches);
            if ($match) {
                $tar = $str;
                return TRUE;
            } else {
                $tar = "";
                return FALSE;
            }
        }
        
        /**
         * @brief assign potential URL to string, performs checking
         * @param ref $tar assignee
         * @param string $urlstr the potential URL string which is checked and assigned
         * @return boolean TRUE if assigned, FALSE if none
         */
        function _assign_url(&$tar, $urlstr) {
            if (t3lib_div::isValidUrl($urlstr)) {
                $tar = $urlstr;
                return TRUE;
            } else {
                $tar = "";
                return FALSE;
            }
        }
        
         /**
         * @brief assign potential local URL to string, performs checking
         * a local URL is of the form 'pid,anchor'
         * @param ref $tar assignee
         * @param string $urlstr the potential local URL string which is checked and assigned
         * @return boolean TRUE if assigned, FALSE if none
         */
        function _assign_localurl(&$tar, $urlstr) {
            // 1. split into pid and anchor
            $parts = explode ( ',', $urlstr, 3);    // pid, anchor, rest
            
            // defaults
            $tar="";
            $pid=0;
            $anchor="";
            
            // 2. sanitize
            if (count($parts) < 1) {
                return FALSE;
            }            
            if (count($parts) > 0) {
                // sanitize pid
                if (!$this->_assign_int($pid, $parts[0], FALSE)) {
                    // sanitize failed
                    return FALSE;
                }
            }
            if (count($parts) > 1) {
                // sanitize anchor            
                $this->_assign_string($anchor, $parts[1]);                                    
            }
            
            // build typolink with (potentially empty) anchor            
            $typolink_conf = array(
                "no_cache" => 0,
                "parameter" => $pid . "#" . $anchor,                
                "useCacheHash" => 1);
            $tar = $this->cObj->typoLink_URL($typolink_conf);
            return TRUE;
        }
        
        /**
         * @brief assign absolute file path to a string, sanitizing input
         * @param string $tar assignee
         * @param string $pathstr the potential path which is checked and assigned
         * @return boolean TRUE if assigned, else FALSE
         */
        function _assign_localfile_abs(&$tar, $pathstr) {
            $tar_full = t3lib_div::getFileAbsFileName($pathstr,1,0);           
            
            // if tar_full is not empty, then the path is safe, but not necessarily existing.
            // check whether it exists.
            if (file_exists($tar_full)) {
                $tar = $tar_full;
                return TRUE;
            } else {
                $tar = "";
                return FALSE;
            }
        }
        
         /**
         * @brief assign relative file path to a string, sanitizing input
         * @param string $tar assignee
         * @param string $pathstr the potential path which is checked and assigned
         * @return boolean TRUE if assigned, else FALSE         
         */
        function _assign_localfile_rel(&$tar, $pathrelstr) {
            
            if (!t3lib_div::validPathStr($pathrelstr)) {
                $tar = "";
                return FALSE;
            }
            
            // relative from Typo main dir
            $tar_abs = PATH_site . $pathrelstr;
                        
            // check whether it exists.
            if (file_exists($tar_abs)) {
                $tar = $pathrelstr;
                return TRUE;
            } else {
                $tar = "";
                return FALSE;
            }
        }
        
        /**
         * @brief safe int (or empty) assignment from typoscript config (string)
         * @param string $str source string
         * @param &int tar assignee
         * @param bool allowempty if true then empty values are valid
         * @return true if assignment successful, else false
         */
        function _assign_int(&$tar,$str,$allowempty=TRUE) {            
            if ($allowempty) {
                if (empty($str)) {
                    $tar = 0;   // empty($tar) -> true.
                    return TRUE;
                } 
            }
            $str=trim($str);
            if (t3lib_div::testInt($str)) {
                $tar = $str;
                return TRUE;
            } else {
                // not an integer
                $tar = 0;
                return FALSE;
            }
        }
        
        /**
         * @brief clean names in OLC list
         * @param string $n name to be cleaned
         * @return string the name without the clutter of country
         */
        function _filter_name($n) {
                // filter away "(DE), ...")
                $p = strpos($n, "(DE)");
                $islink = strpos($n, "href");
                if ($p !== FALSE) {
                        $n = substr($n,0,$p-1);
                        if ($islink !== FALSE) {
                                $n = $n . "</a>"; // close link because we chopped it away
                        }
                }
                return $n;
        }

        /**
         * @brief provide an image of aircraft (path of it)
         * Performs a similarity compariston to match the a/c name.
         * @param string $aircraft aircraft name
         * @return string rel. path to image
         * FIXME: use typolink()?
         */
        function _get_ac_image($aircraft) {
                // find most similar string and show image for that            
                $aircraft = strtolower($aircraft);
                $maxper = 0;
                $minper = 3; // minimum number of consecutive(?) letters to be matched at least
                $match="default";
                reset($this->mapping_img);
                while (list($key, $value) = each($this->mapping_img)) {
                    $per = similar_text($aircraft, $key);
                    if ($per >= $maxper && $per >= $minper) {
                        $maxper = $per;
                        $match = $key;
                    }
                }
                // only return when a match was found
                if (!empty($this->mapping_img[$match])) {
                    return $this->mapping_img[$match];
                    //$img = "<img src=\"" . $this->mapping_img[$match] . "\" alt=\"" . $match . "\"/>";                    
                    //return $this->_get_ac_link($aircraft,$img);
                } else {                    
                    return "";
                }                
        }

        /**
         * @brief provides a link to the aircraft
         * Performs a similarity compariston to match the a/c name.
         * @param string $aircraft a/c name input
         * @param string $linktext ...
         * @return string html code like <a href="...">$linktext</a>
         */
        function _get_ac_link($aircraft,$linktext) {  
                // find most similar string and generate link for that            
                $aircraft = strtolower($aircraft);
                $maxper = 0;
                $minper = 3; // minimum number of consecutive(?) letters to be matched at least
                $match="default";
                
                reset($this->mapping_link);
                while (list($key, $value) = each($this->mapping_link)) {
                    $per = similar_text($aircraft, $key);
                    //echo "a/c=$aircraft, key=$key, similarity=$per<br>";
                    if ($per >= $maxper && $per >= $minper) {
                        $maxper = $per;
                        $match = $key;
                    }
                }

                if (!empty($this->mapping_link[$match])) {
                    // FIXME: typolink()
                    $link = "<a href=\"" . $this->mapping_link[$match] . "\">" . $linktext . "</a>";
                } else {
                    // no match, return label itself
                    $link = $linktext;                        
                }
                return $link;
        }

        /**
         * @brief rewrite OLC links to fit our needs (set own text, remove clutter)
         * @param string $l html link code
         * @param string $linktext the link text that we want
         * @return string html code with the new link
         */
        function _rewrite_link($l,$linktext) {
                // 1. get link target
                // find begin of href
                $link = str_replace(" ", "", $l);
                $p = strpos($link, "ahref=");
                if ($p == FALSE) {
                        return $l; // fail
                }
                $href=substr($link, $p+7);
                // find end of href
                $p = strpos($href, "\"");
                if ($p == FALSE) {
                        return $l; // fail
                }
                $href = substr($href,0,$p);
                // now we should have href content
                //$content .=  "href=$href";

                // 2. rebuild link
                $link = "<a href=\"" . $href . "\" target=\"_new\">" . $linktext . "</a>";
                return $link;
        }

        /**
         * @brief input seconds and you get an output formatted hh:mm (a duration)
         * @param int $diff seconds
         * @return string human-readable duration hh:mm (no seconds!)
         */
        function _sec2str($diff) {
                if( $hours=intval((floor($diff/3600))) ) {
                        $diff = $diff % 3600;
                }
                if( $minutes=intval((floor($diff/60))) ) {
                        $diff = $diff % 60;
                }
                $diff    =    intval( $diff );            

                $str=sprintf("%02d:%02d", $hours, $minutes);
                return $str;
        }

        /**
         * @brief render one single flight
         * @param array $f from OLC reader
         * @param string $tpl template
         * @return string html code
         */
        function _render_flight($f,$tpl) {
            
                $startzeit = strtotime($f["Start"]);  //$content .=  "start=$startzeit<br>";
                $endzeit = strtotime($f["End"]); //$content .=  "end=$endzeit<br>";
                $flugzeit = $endzeit-$startzeit; //$content .=  "flugzeit=$flugzeit";
                $str_flugzeit = $this->_sec2str($flugzeit);
                $str_date = strftime("%d. %B", strtotime($f["date"]));
                $points = $f["points"];

                $markerArray['###TIME_START###']=$f["Start"];
                $markerArray['###TIME_END###']=$f["End"];
                $markerArray['###DURATION###']=$str_flugzeit;                        
                $markerArray['###DATE###']=$str_date;
                $markerArray['###POINTS###']=$points;
                $markerArray['###NAME###']=$this->_filter_name($f["name"]);
                $markerArray['###LINK###']=$this->_rewrite_link($f["Info"], "[mehr ...]"); // TODO: cache!
                $markerArray['###AC_LINK_TXT###']=$this->_get_ac_link($f["Aircraft"],$f["Aircraft"]); // TODO: cache!
                $markerArray['###AC_LINK_PIC###']=$this->cObj->IMAGE(array(
                    'file' => $this->_get_ac_image($f["Aircraft"]),
                    'file.maxW' => $this->img_maxw,
                    'file.maxH' => $this->img_maxh,
                ));                // FIXME: scaling does not take effect!
                $markerArray['###SPEED###']= str_replace(".", ",", $f["km/h"]); // TODO: localize
                $markerArray['###DISTANCE###']= str_replace(".", ",", $f["km"]); // TODO: localize  
                
                return $this->cObj->substituteMarkerArray($tpl,$markerArray);
        }

        /**
         * @brief render list of flights
         * @param array $farray from OLC reader
         * @param string $tpl template
         * @return string html code of all flights in array
         */
        function _render_flights($farray,$tpl) {
                $content="";
                if (empty($farray)) {
                  $content .=  $this->pi_getLL("noflights");
                } else {
                  foreach ($farray as $f) {
                        $content .= $this->_render_flight($f,$tpl);     
                  }                  
                }
                return $content;
        }
        
        /**
         * @brief render list view
         * @return string comprising the HTML list using the template
         */
        function _list_view() {
            $cache=1;       // yes, we use chash for the detail view of a flight (target page)            
            
            // grab stats from OLC page.
            $res = new olc_reader($this->olc_fetchurl,$this->olc_baseurl);
            $res->fetch();            
                        
            // get templates for subparts for list
            $tpl_list    = $this->cObj->getSubpart($this->template,'###LISTVIEW###'); 
            $tpl_lflight = $this->cObj->getSubpart($tpl_list,'###FLIGHT_LIST###'); 
            $tpl_bflight = $this->cObj->getSubpart($tpl_list,'###FLIGHT_BEST###');                                                         
            
            // default content            
            $content_flightbest="";
            $content_flightlist="";
            $title_list="";
            
            // bester flug nur wenn nicht die letzten 'days' tage angezeigt werden
            if ($this->best==1) {
                $best = $res->get_best();
                $content_flightbest .= $this->_render_flight($best,$tpl_bflight);
            } 

            if ($this->lastday == 1 && $this->limit != 1) {
                // all flights of the last flight day, probably with a limit > 1
                if (empty($this->limit)) { $this->limit = 0; }

                $recent = $res->get_recent_day($this->limit);

                $maxdate_str = strtoupper(strftime("%d. %B", $res->get_max_date()));
                $content_flightlist .= $this->_render_flights($recent,$tpl_lflight); // show list
                $title_list = $this->pi_getLL("flights_from") . " " . $maxdate_str;
            } else if (!empty($this->days)) {
                // all flights from today looking 'days' backward
                if (empty($this->limit)) { $this->limit = 0; }

                $startdate = strtotime ( '-' . $this->days . ' day') ;
                $recent = $res->get_from_date($startdate,$this->limit);

                $maxdate_str = strtoupper(strftime("%d. %B", $res->get_max_date()));
                if (empty($recent)) {
                   $content_flightlist .=  "In den letzten $this->days Tagen fanden keine Fl&uuml;ge statt...<br>";
                } else {
                   $content_flightlist .= $this->_render_flights($recent,$tpl_lflight); // show list
                }
                $title_list = sprintf($this->pi_getLL("flights_of_last_n_days"), $this->days);
            } else {
                // DEFAULT: just the most recent flight
                $latest = $res->get_latest();
                $content_flightlist .= $this->_render_flight($latest,$tpl_lflight);  // show single
                $title_list = $this->pi_getLL("most_recent_flight");
            }

            // clean
            $res->clear();
            unset($res);
            
            // replacements in template: put best and list
            $subpartArray['###FLIGHT_LIST###']=$content_flightlist;
            $subpartArray['###FLIGHT_BEST###']=$content_flightbest; 
            $subpartArray['###TITLE_LIST###']=$title_list;
            return $this->cObj->substituteMarkerArrayCached($tpl_list,array(), $subpartArray); 
        }
         
        /**
         * @brief render detail view
         * @return string comprising the HTML detail view using the template
         * FIXME: not needed currently
         */        
        function _detail_view() {
            //unser Subpart
            $subpart=$this->cObj->getSubpart($this->template,'###DETAILVIEW###'); 
                       
            //backlink als einfachen Link ohne Parameter
            $markerArray['###BACKLINK###']=$this->pi_linkToPage($this->pi_getLL('back'),$this->pid);
                   
            return $this->cObj->substituteMarkerArrayCached($subpart,$markerArray,array(),array());     
        }

}



if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/mb_olc_flights/pi1/class.tx_mbolcflights_pi1.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/mb_olc_flights/pi1/class.tx_mbolcflights_pi1.php']);
}

?>