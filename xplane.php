<?php

$fp = fopen('xplane/Resources/default data/earth_awy.dat', 'r');
include dirname(__FILE__).'/db.php';

$list ='SELECT * FROM phpvms_navdata
		WHERE `lat`=0 OR `lng`=0';
	
$missing = array();	
$result = mysql_query($list);
while($row = mysql_fetch_object($result)) {
	$missing[$row->name] = array();
}

$airways = array();
$total=0;
$skip = 0;
while($line = fgets($fp)) {
	
	# Skip the first three lines
	if($skip < 3) {
		$skip ++;
		continue;
	}
	
	list($entry_name, $entry_lat, $entry_lng, $exit_name, $exit_lat, $exit_lng, 
			$hi_lo, $base, $top, $name) = explode(' ', $line);
	
	$entry_name = trim($entry_name);
	if(array_key_exists($entry_name, $missing)) {
		$missing[$entry_name] = array(
			'lat' => $entry_lat,
			'lng' => $entry_lng,
			'source' => 'awy',
		);
		
		$total++;
	}	
	
	/*$exit_name = trim($exit_name);
	if(array_key_exists($exit_name, $missing))
	{
		$missing[$exit_name] = array(
			'lat' => $exit_lat,
			'lng' => $exit_lng,
		);
		
		$total++;
	}*/
}

// Next check the fixes
fclose($fp);

$fp = fopen('xplane/Resources/default data/earth_fix.dat', 'r');
$skip = 0;
while($line = fgets($fp)) {
	
	# Skip the first three lines
	if($skip < 3) {
		$skip ++;
		continue;
	}
	
	list($lat, $lng, $name) = explode(' ', $line);
	
	$name = trim($name);
	if(array_key_exists($name, $missing)) {
		$missing[$name] = array(
			'lat' => $lat,
			'lng' => $lng,
			'source' => 'fix',
		);
			
		$total++;
	}
}

fclose($fp);

$fp = fopen('xplane/Resources/default data/earth_nav.dat', 'r');
$skip = 0;
while ($fix_info = fscanf($fp, "%s %s %s %s %s %s %s %s %s %s %s\n"))  {
	
	if($fix_info[0] == '2') {
		$type = NAV_NDB;
		$lat = $fix_info[1];
		$lng = $fix_info[2];
		$freq = $fix_info[4];
		$name = $fix_info[7];
		$title = $fix_info[8];
		$total_ndb ++;
	} elseif($fix_info[0] == '3') {
		$type = NAV_VOR;
		$lat = $fix_info[1];
		$lng = $fix_info[2];
		$freq = $fix_info[4];
		$name = $fix_info[7];
		$title = $fix_info[8];
		$total_vor ++;
	}
	
	$name = trim($name);
	$title = mysql_escape_string($title);
	
	if(empty($lat) || empty($lng))
		continue;
	
	if(array_key_exists($name, $missing)) {
		
		$missing[$name] = array(
			'lat' => $lat,
			'lng' => $lng,
			'source' => 'nav',
		);
			
		$total++;
	}
}

fclose($fp);

print_r($missing);

echo "Total: ".count($missing).", updated {$total}\n";
