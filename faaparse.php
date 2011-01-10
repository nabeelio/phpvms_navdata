<?php

include dirname(__FILE__).'/db.php';

function get_coordinates($line) {
	/* Get the lat/long */
	preg_match('/^(\d*)-(\d*)-(\d*)\.(\d*)([A-Za-z])/', $line, $coords);

	$lat_deg = $coords[1];
	$lat_min = $coords[2];
	$lat_dir = $coords[5];

	$lat_deg = ($lat_deg*1.0) + ($lat_min/60.0);

	if(strtolower($lat_dir) == 's')
		$lat_deg = '-'.$lat_deg;

	if(strtolower($lat_dir) == 'w')
		$lat_deg = $lat_deg*-1;

	return number_format($lat_deg, 6);
}


/* parse airways data */

$fp = fopen('faa/AWY.txt', 'r');

$airways = array();

$total = 0;
$updated = 0;
$skipped = 0;
while($line = fgets($fp)) {
	$type = substr($line, 0, 4);
	if($type == 'AWY2') {
		$airway_name = trim(substr($line, 4, 5));
		$fix_name = trim(substr($line, 113, 5));
		
		$pub_cat = trim(substr($line, 64, 15));
		
		$lat = trim(substr($line, 81, 14));
		$lng = trim(substr($line, 95, 14));
		$lat = get_coordinates($lat);
		$lng = get_coordinates($lng);
		
		if(empty($fix_name) || empty($lat) || empty($lng)) {
			//$skipped++;
			continue;
		}
		
		$sql = "SELECT * 
				FROM `phpvms_navdata`
				WHERE `name`='{$fix_name}' AND `airway`='{$airway_name}'";
				
		$res = mysql_query($sql);
		if(mysql_num_rows($res) == 0) {
			continue;
		}
		
		$row = mysql_fetch_object($res);		
		if(!$row) {
			continue;
		}
		
		// Only update is there a within a 2% difference
		$lat_diff = abs((abs($lat / $row->lat)) * 100 - 100);
		$lng_diff = abs((abs($lng / $row->lng)) * 100 - 100);
		
		if($lat_diff > 1 || $lng_diff > 1) {
			/*echo "{$airway_name} - {$fix_name}: lat is $lat lng is $lng\n";
			echo "difference is $lat_diff and $lng_diff\n";
			print_r($row);*/
			$skipped++;
			continue;
		}
			
		// Update it
		$sql = "UPDATE `phpvms_navdata`
				SET `lat`={$lat} AND `lng`={$lng}
				WHERE `id`={$row->id}";
				
		mysql_query($sql);
		
		$updated++;
	}

	$total++;
	/*if($total == 500)
		break;*/
	
}

echo "Entries parsed: $total, {$updated} updated, {$skipped} skipped\n";
