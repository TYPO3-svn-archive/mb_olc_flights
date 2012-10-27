<?php
/*
 * THIS IS RELEASED UNDER GPLv3.
 *
 * @author Martin Becker (vbmazter@web.de)
 * @version 1.0
 * @package PlaceLocalInclude
 * @subpackage simple_html_dom
 */

include('simple_html_dom.php.inc');

define('OLR_READER_VERSION', 1);


// some helper funcs
function is_link_rel($link) {
        return (strncmp('http',strtolower($link),4) == 0)?FALSE:TRUE;
}
function make_link_abs($base_url,$link) {
        return $base_url . '/' . $link;
}
function process_property(&$val, $key, $base_url) {
        $fhtml = str_get_html($val);
        $updated=FALSE;
        foreach($fhtml->find('a') as $link) { // all links in each property
                $tar = $link->href;
                if (is_link_rel($tar)) {
                        // update html
                        $link->href=make_link_abs($base_url,$tar);
                        //echo "$tar -> $link->href<br>";
                        $updated=TRUE;
                }
        }
        // write back if necessary
        if ($updated) {
                $val = $fhtml->save();
        }
        // clean up memory
        $fhtml->clear();
        unset($fhtml);
}
function process_flight(&$val, $key, $base_url) {
        array_walk($val,'process_property',$base_url);
}


// class doing the work
class olc_reader {
    private $fetch_url = null;
    private $base_url = null;
    private $data = null;
    private $maxdate = 0;

    function __construct($fetch_url, $base_url)
    {
        $this->fetch_url = $fetch_url;
        $this->base_url = $base_url;
    }

    function __destruct()
    {
        $this->clear();
    }

    function get_best() {
        if ($this->data == null) $this->fetch();

        // now search max points
	$pt=0;
	$f=null;
	foreach ($this->data as $flight) {
		$this_pt = $flight["Points"];
		if ($this_pt > $pt) {
			$pt=$this_pt;
			$f = $flight;
		}
	}
	return $f;
    }

    function get_latest() {
        if ($this->data == null) $this->fetch();

        // now search max points
	$latest=0;
	$f=null;
	foreach ($this->data as $flight) {
		$this_day = strtotime($flight["date"]);
		if ($latest < $this_day) {
			$latest=$this_day;
			$f = $flight;
		}
	}
	return $f;
    }

    function get_max_date() {
	return $this->maxdate;
    }

    function get_recent_day($limit) {
        if ($this->data == null) $this->fetch();

	$cnt=0;

	// debug
	//$this->maxdate = strtotime("2012/07/25");
        // now search latest date points
	$f=array();
	foreach ($this->data as $flight) {
		$this_day = strtotime($flight["date"]);
		if ($this_day == $this->maxdate) {
			$cnt++;
			// append
			$f[]=$flight;
		}
		if ($cnt==$limit && $limit > 0) break;
	}
	return $f;
    }

    function get_from_date($date, $limit) {
        if ($this->data == null) $this->fetch();

	$cnt=0;
	$f=array();
	foreach ($this->data as $flight) {
		$this_day = strtotime($flight["date"]);
		if ($this_day >= $date) {
			$cnt++;
			$f[]=$flight;
		}
		if ($cnt==$limit && $limit > 0) break;
	}
	return $f;
    }

    function fetch() {
	// get DOM from URL or file
	$html = file_get_html($this->fetch_url);

	$this->data = array();
	foreach($html->find('table#dailyScore') as $t) {
	    // build the table in memory
	    $head = $t->find('thead');
	    if ($head == null) {
	        echo "no header found in" . dump_html_tree($t) . "<br>";
	        return;
	    } else {  
	        // we want key-value pairs; keys are the headers we parse here
	        $headers = array();
	        foreach($head[0]->find('th') as $h) {
	            $headers[]=trim($h->plaintext);
	        }
	        //echo "<b>Header</b>:"; print_r($headers); echo "<br><br>";
	        // now read in the data and put it in an key-value array for easy handling
	        $b = $t->find('tbody');
	        if ($b == null) {
	            echo "no table body found in: <br>"; dump_html_tree($t); echo "<br>";
	            return;
	        }
		$this->maxdate=strtotime("2000/01/01");
	        foreach($b[0]->find('tr') as $flight) {
	                $c=0;
	                $entry=array();
	                foreach($flight->find('td') as $property) {
				$val = trim($property->innertext);
	                        $entry[$headers[$c]] = $val;
				// track max date
				if (strtolower($headers[$c])=="date") {
					$dval = strtotime($val);
					if ($dval > $this->maxdate) {
						$this->maxdate = $dval;
					}
				}
        	                $c=$c+1;
                	}
	                $this->data[]=$entry; // append this flight's key-value array
	        }
	    }
	    break; // there shall only be one
	}
	//echo "maxdate=$this->maxdate<br>";

	// clean up memory
	$html->clear();
	unset($html);

	// make all relative links absolute
	$this->fix_rel_links();
    }

    function fix_rel_links() {
        array_walk($this->data,'process_flight',$this->base_url);
    }


    function get_all() {
	return $this->data;
    }

    // clean up memory due to php5 circular references memory leak...
    function clear()
    {
        $this->fetch_url = null;
        $this->base_url = null;
	$this->data = null;
    }

}

?>
