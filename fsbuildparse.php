<?php

include dirname(__FILE__).'/db.php';

/**
 * Import VOR and NDBs into our database
 */
function get_coordinates($line) {
	/* Get the lat/long */
	preg_match('/^([A-Za-z])(\d*):(\d*:\d*)/', $line, $coords);

	$lat_dir = $coords[1];
	$lat_deg = $coords[2];
	$lat_min = $coords[3];

	$lat_deg = ($lat_deg*1.0) + ($lat_min/60.0);

	if(strtolower($lat_dir) == 's')
		$lat_deg = '-'.$lat_deg;

	if(strtolower($lat_dir) == 'w')
		$lat_deg = $lat_deg*-1;

	return $lat_deg;
}


/**
 * Return string with general location:
 * NAM - North America
 * SAM - South American
 * EUR - Europe
 * ASA - Asia
 * AFR - Africa
 * AUS - Australia
 * NAT - North Atlantic Track
 * SAT - South Atlantic Track
 * PAT - Pacific Ocean Track
 */
function get_earth_location($lat, $lng) {
	
	// Asia
	if($lng > 34 && $lng < 180) {
		// Australia
		if($lat < -11) {
			return 'AUS';
		}
		
		return 'ASA';
	}
	
	
	// NAT
	if($lng > -58 && $lng < -11) {
		if($lat > 42) {
			return 'NAT';
		}
	}
	
	// Europe
	if($lng > -15 && $lng < 40) {
		if($lat > 35) {
			return 'EUR';
		}
	}
	
	// NAM
	if($lng > -180 && $lng < - 56){
		if($lat > 25) {
			return 'NAM';
		} else {
			return 'SAM';
		}
	}
	
	return '';
}

# Setup the inserts into chunks of 1000
$chunks = 3000;

mysql_query('TRUNCATE table phpvms_navdata');


$sql = "INSERT INTO phpvms_navdata (name, airway, airway_type, seq, loc, lat, lng, type) VALUES ";

$total = 0;
$list = array();


echo "Loading airways segments...";
$handle = fopen('fsbuild/fsb_awy2.txt', 'r');

while ($line = fscanf($handle, "%s %s %s %s %s %s %s\n"))  {

	if($line[0][0] == ';') {
		continue;
	}

	list ($airway, $sequence, $name, $lat, $lng, $airway_type, $type) = $line;

	/*if($lat == '' && $lat == '')
	{
		continue;
	}*/

	if($lat == 0 || $lng == 0) {
		//print_r($line);
		//exit;
	}
	
	$title = mysql_escape_string($title);

	if($type == 'F' || $type == 'B') {
		$type = NAV_FIX;
	} else {
		$type = NAV_VOR;
	}
	
	$loc = get_earth_location($lat, $lng);
	
	$list[] = "('{$name}', '{$airway}', '{$airway_type}', '{$sequence}', '{$loc}', '{$lat}', '{$lng}', {$type})";

	$total ++;

	if($total == $chunks) {
		$values = implode(',', $list);
		$list = array();

		mysql_query($sql.$values);

		if(mysql_errno() != 0) {
			echo "============\n".mysql_error()."\n==========\n";
			die();
		}

		$chunks += $chunks;
	}
}
# Do the last bit
$values = implode(',', $list);
mysql_query($sql.$values);

fclose($handle);

echo "{$total} airway segments loaded... \n";
echo "Loading VORs...";

$total = 0;
$updated = 0;

$updated_list = array();

$list = array();
$chunks = 3000;

$sql = "INSERT INTO phpvms_navdata (name, title, airway, seq, loc, lat, lng, freq, type) VALUES ";

$handle = fopen("fsbuild/fsb_vor.txt", "r");
while ($lineinfo = fscanf($handle, "%s %s %s %s %s %s\n"))  {
	if($lineinfo[0][0] == ';') {
		continue;
	}
	
	list ($name, $title, $lat, $lng, $type, $freq) = $lineinfo;

	$lat = get_coordinates($lat);
	$lng = get_coordinates($lng);
		
	$title = str_replace('_', ' ', $title);
	$title = strtolower($title);
	$title = ucwords($title);
	$title = mysql_escape_string($title);
	
	$type = NAV_VOR;
	
	$res = mysql_query("SELECT id FROM phpvms_navdata WHERE `name`='{$name}'");
	if(mysql_num_rows($res) > 0) {
		
		if(in_array($name, $updated_list)) {
			continue;
		} else {
			$updated_list[] = $name;
		}
		
		// Just do an update on the spot
		$update_sql="UPDATE phpvms_navdata
					 SET `title` = '{$title}', `freq` = '{$freq}'
					 WHERE `name` = '{$name}'";
		
		mysql_query($update_sql);
		
		//echo "updating {$name}\n";
		$updated ++;
		continue;
	} else { 
		//echo "adding {$name}\n";
		$loc = get_earth_location($lat, $lng);
		$list[] = "('{$name}', '{$title}', '', 0, '{$loc}', '{$lat}', '{$lng}', '{$freq}', {$type})";
	}
	
	if($total == $chunks) {
		$values = implode(',', $list);
		$list = array();

		mysql_query($sql.$values);

		if(mysql_errno() != 0) {
			echo "============\n".mysql_error()."\n==========\n";
			die();
		}

		$chunks += $chunks;
	}
	
	$total ++;
}

$values = implode(',', $list);
mysql_query($sql.$values);

fclose($handle);

echo "{$total} VORs added, {$updated} updated\n";
echo "Loading NDBs...";

// Add NDBs

$total = 0;
$chunks = 500;
$list = array();

$sql = "INSERT INTO phpvms_navdata (name, title, seq, loc, lat, lng, type) VALUES ";

$handle = fopen('fsbuild/fsb_ndb.txt', 'r');
while ($lineinfo = fscanf($handle, "%s %s %s %s %s\n"))  {
	
	if($lineinfo[0][0] == ';') {
		continue;
	}
	
	list ($name, $title, $lat, $lng, $type) = $lineinfo;
		
	$lat = get_coordinates($lat);
	$lng = get_coordinates($lng);
	
	$title = strtoupper($title);
	$title = mysql_escape_string($title);
	
	$type = NAV_NDB;
	
	$res = mysql_query("SELECT * FROM phpvms_navdata WHERE `name`='{$name}'");
	if(mysql_num_rows($res) > 0) {
		
		if(in_array($name, $updated_list)) {
			continue;
		} else {
			$updated_list[] = $name;
		}
		
		// Just do an update on the spot
		$update_sql="UPDATE phpvms_navdata
					 SET `title` = '{$title}', `lat`={$lat}, `lng`={$lng}
					 WHERE `name` = '{$name}'";
		
		mysql_query($update_sql);
		
		$updated ++;
		continue;
	} else { 
		$loc = get_earth_location($lat, $lng);
		$list[] = "('{$name}', '{$title}', 0, '{$loc}', '{$lat}', '{$lng}', {$type})";
	}
	
	if($total == $chunks) {
		$values = implode(',', $list);
		$list = array();

		mysql_query($sql.$values);
		
		if(mysql_errno() != 0) {
			echo "============\n".mysql_error()."\n==========\n";
			die();
		}

		$chunks += $chunks;
	}
	
	$total ++;
}

$values = implode(',', $list);
mysql_query($sql.$values);

fclose($handle);


echo "{$total} NDBs added, {$updated} updated\n";

/*
// Load intersections
echo "Loading INTs...";

// Add NDBs

$total = 0;
$chunks = 500;
$list = array();

$sql = "INSERT INTO phpvms_navdata (name, title, seq, lat, lng, type) VALUES ";

$handle = fopen('fsbuild/fsb_int.txt', 'r');
while ($lineinfo = fscanf($handle, "%s %s %s %s %s\n")) 
{
	if($lineinfo[0][0] == ';')
	{
		continue;
	}
	
	list ($name, $title, $lat, $lng, $type) = $lineinfo;
	
	$lat = get_coordinates($lat);
	$lng = get_coordinates($lng);
	
	$title = strtoupper($title);
	$title = mysql_escape_string($title);
	
	if(substr_count($title, '_(') > 0)
	{
		preg_match("/.*\((.*)\)(.*)/", $title, $matches);
		$title = $matches[1];
	}
	
	$type = NAV_FIX;
	
	$res = mysql_query("SELECT * FROM phpvms_navdata WHERE `name`='{$name}'");
	if(mysql_num_rows($res) > 0)
	{
		if(in_array($name, $updated_list))
		{
			continue;
		}
		else
		{
			$updated_list[] = $name;
		}
		
		// Just do an update on the spot
		$update_sql="UPDATE phpvms_navdata
					 SET `title` = '{$title}'
					 WHERE `name` = '{$name}'";
		
		mysql_query($update_sql);
		
		$updated ++;
		continue;
	}
	else
	{ 
		$list[] = "('{$name}', '{$title}', 0, '{$lat}', '{$lng}', {$type})";
	}
	
	if($total == $chunks)
	{
		$values = implode(',', $list);
		$list = array();

		mysql_query($sql.$values);
		
		if(mysql_errno() != 0)
		{
			echo "============\n".mysql_error()."\n==========\n";
			die();
		}

		$chunks += $chunks;
	}
	
	$total ++;
}

$values = implode(',', $list);
mysql_query($sql.$values);

fclose($handle);


echo "{$total} INTs added, {$updated} updated\n";*/

echo "Completed!\n";
