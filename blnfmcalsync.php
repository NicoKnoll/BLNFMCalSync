<?php
/*
Plugin Name: BLN.FM Cal Sync
Description: 
Author: Nico Knoll
Version: 2.2
Author URI: http://nico.is
*/

$blnfmSyncWarnings = array();

function getSpreadsheets() {
	return array(
		'1o1tSB-z8BWgmz1xifLCOzIGRv12S9cNuaR2D7WJS9Wg',
		'13pRV-83bORr2lTjyvJo_byN093I00ysfx3l6RbdUWJI'
	);
}

function file_get_contents_curl($url) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
	curl_setopt($ch, CURLOPT_URL, $url);

	$data = curl_exec($ch);
	curl_close($ch);

	return $data;
}


function worksheetsToArray($id) {
	$entries = array();
	$i = 1;

	while(true) {
		$url = 'https://spreadsheets.google.com/feeds/list/'.$id.'/'.$i.'/public/full?alt=json';
		$data = @json_decode(file_get_contents_curl($url), true);
		$starttag = substr($data['feed']['title']["\$t"], 0, strpos($data['feed']['title']["\$t"], " "));

		if(!isset($data['feed']['entry'])) break;

		foreach($data['feed']['entry'] as $entryKey => $entry) {
			$data['feed']['entry'][$entryKey]['gsx$starttag']["\$t"] = $starttag;
		}

		$entries = array_merge($entries, $data['feed']['entry']);
		
		$i++;
	}

	return $entries;
}


function spreadsheetToArray($id) {
	$entries = worksheetsToArray($id);
	$return = array();

	foreach($entries  as $event) {
		$return_event = array();
		foreach($event as $key => $value) {
			if(preg_match("%^gsx\\$%Uis", $key)) {
				$key = str_replace('gsx$', '', $key);
				if($key == '_cn6ca') $key = 'starttag';

				if($key == 'starttag' || $key == 'endtag') {
					$tmp = explode('.', $value["\$t"]);
					if($value["\$t"] != '') $return_event[$key] = @date('Y-m-d', mktime(0,0,0,(int)$tmp[1],(int)$tmp[0],(int)$tmp[2]));
					else $return_event[$key] = $value["\$t"];
				} elseif($key == 'startzeit' || $key == 'endzeit') {
					$tmp = explode(':', $value["\$t"]);
					if($value["\$t"] != '') $return_event[$key] = @date('H:i:s', mktime((int)$tmp[0],(int)$tmp[1],0,0,0,0));
					else $return_event[$key] = $value["\$t"];
				} elseif($key == 'ganztägig' || $key == 'fortlaufend' || $key == 'recommended' || $key == 'promoted' || $key == 'team' || $key == 'ausverkauft') {
					$return_event[$key] = ((trim($value["\$t"]) != '' && $value["\$t"] != 0) ? true : false);
				} else {
					$return_event[$key] = $value["\$t"];
				}
			}
		}
		$return[] = processData($return_event);
	}

	return $return;
}

function processData($eventData) {
	global $blnfmSyncWarnings;

	$id = '<b>'.$eventData['id'].'</b>';

	if($eventData['endtag'] == '') {
		$blnfmSyncWarnings[] = $id.': Endtag fehlt. Es wird jetzt angenommen, dass Endtag = Starttag ist.';
		$eventData['endtag'] = $eventData['starttag'];
	}

	if($eventData['startzeit'] == '') {
		$blnfmSyncWarnings[] = $id.': Startzeit fehlt und wird auf 23h gesetzt.';
		// Todo:check which type the event is
		$eventData['startzeit'] = @date('H:i:s', mktime(23,0,0,0,0,0));
	}

	if($eventData['endzeit'] == '') {
		$blnfmSyncWarnings[] = $id.': Endzeit fehlt und wird auf 6h gesetzt.';
		// Todo:check which type the event is
		$eventData['endzeit'] = @date('H:i:s', mktime(6,0,0,0,0,0));
	}

	if(isset($eventData['ausverkauft'])) {
		$blnfmSyncWarnings[] = $id.': Ausverkauft, Status wird auf "ausverkauft" gesetzt.';
		$eventData['status'] = 'ausverkauft';
	}

	if(isset($eventData['verschoben'])) {
		$blnfmSyncWarnings[] = $id.': Verschoben, Status wird auf "verschoben" gesetzt.';
		$eventData['status'] = 'verschoben';
	}

	return $eventData;
}

/*
function addLocation($name) {
	$em_location = new EM_Location();

	$em_location->location_name = $name;
	$em_location->location_address = $name;
	$em_location->location_town = 'Berlin';
	$em_location->location_state = 'Berlin';
	$em_location->location_country = 'DE';

	$em_location->save();
	$em_location->save_meta();

	return $em_location->location_id;
}

function getLocationIDs() {
	$EM_Locations = EM_Locations::get();
	$return = array();

	foreach($EM_Locations as $EM_Location){
	  	@$return[$EM_Location->name] = @$EM_Location->id; 
	}

	return $return;
}
*/
function getPostIdByMetaValue($key, $value) {
	global $wpdb;
	$meta = $wpdb->get_results("SELECT * FROM `".$wpdb->postmeta."` WHERE meta_key='".esc_sql($key)."' AND meta_value='".esc_sql($value)."'");
	
	if (is_array($meta) && !empty($meta) && isset($meta[0])) {
		$meta = $meta[0];
	}	
	
	if (is_object($meta)) {
		return $meta->post_id;
	} else {
		return false;
	}
}


function updateEvent($data) {
	$em_event = em_get_event(getPostIdByMetaValue('_ss_id', $data['id']), 'post_id');
	$check = true;

	if(isset($data['command']) && $data['command'] == 'UPDATE') {
		if(!($em_event->event_id)) $em_event = new EM_Event();

		$em_event->event_start_date = $data["starttag"];
		$em_event->event_start_time = $data["startzeit"];
		$em_event->event_end_date = $data["endtag"];
		$em_event->event_end_time = $data["endzeit"];

		$em_event->start = strtotime($em_event->event_start_date." ".$em_event->event_start_time);
		$em_event->end = strtotime($em_event->event_end_date." ".$em_event->event_end_time);

		$em_event->location_id = (isset($data["venueid"]) ? $data["venueid"] : '');

		$em_event->post_title = $data["titel"];
		$em_event->event_name = $data["titel"];

		//$em_event->body = (($data["kurzbeschreibung"]) ? $data["kurzbeschreibung"] : '');
		$em_event->post_content = (isset($data["kurzbeschreibung"]) ? $data["kurzbeschreibung"] : '');
		$em_event->post_excerpt = (isset($data["auszug"]) ? $data["auszug"] : '');
		$em_event->post_tags = @$data["tags"];

		// meta
		$em_event->event_attributes = array(
			'Status' 			=> $data['status'],
			'Line Up' 			=> $data['lineup'],
			'Stil' 				=> (isset($data["stil"]) ? $data["stil"] : ''),
			'Preis' 			=> $data['preis'],
			'Parent' 			=> (isset($data["parentid"]) ? $data["parentid"] : ''),
			'Team' 				=> $data['team'],
			'Recommended' 		=> $data['recommended'],
			'Promoted' 			=> $data['promoted'],
			'Gewinnspiel' 		=> $data['gewinnspiel'],
			'Kurzbeschreibung'	=> $data["kurzbeschreibung"]
		);
		$em_event->group_id = 0;
		$em_event->event_date_modified = date('Y-m-d H:i:s', time());
		$em_event->event_all_day = ($data['ganztägig'] ? 1 : 0);
		$em_event->event_rsvp = 0;

		$check = $em_event->save();

		add_post_meta($em_event->post_id, '_ss_id', $data['id']);

		// add category
		$categories = array();
		$type = new EM_Category(strtolower($data["veranstaltungstyp"]));
		if(!$type->term_id) $type = new EM_Category("sonstiges");

		array_push($categories, ($type->term_id));
		if($data["recommended"]) 	array_push($categories, get_cat_ID('tipp'));
		if($data["promoted"]) 		array_push($categories, get_cat_ID('sponsored'));
		if($data["team"]) 			array_push($categories, get_cat_ID('team'));
		if($data["veranstaltungstyp"]) array_push($categories, get_cat_ID(strtolower($data["veranstaltungstyp"])));
		if($data["gewinnspiel"]) 			array_push($categories, get_cat_ID('gewinnspiel'));
		if($data["preis"] != '' && $data["preis"] == 0) 			array_push($categories, get_cat_ID('kostenlos'));

		if(count($categories)) wp_set_post_terms($em_event->post_id, $categories, 'event-categories', false);

		// add tags
		$tags = array();
		if($data["tags"]) 			array_push($tags, $data["tags"]);
		//if($data["ausverkauft"]) 	array_push($tags, "ausverkauft");
		if($data["openair"]) 		array_push($tags, "open air");
		if($data['lineup'])			$tags = array_merge($tags, explode(',', $data['lineup']));
		if($data['tags'])			$tags = array_merge($tags, explode(',', $data['tags']));

		if(count($tags)) wp_set_post_terms($em_event->post_id, $tags, 'event-tags', false);

	} elseif(isset($data['command']) && $data['command'] == 'DELETE') {
		if($em_event->event_id) $check = $em_event->delete(true);
	}

	return $check;
}

function updateEvents() {
	foreach(getSpreadsheets() as $spreadsheet) {
		$events = spreadsheetToArray($spreadsheet);
		foreach($events as $event) {
			// reload locations as we dynamically create them if missing
			/*
			$locations = getLocationIDs();

			$event['locationid'] = @$locations[$event['venue']];
			if($event['locationid'] == '') $event['locationid'] = addLocation($event['venue']);
			*/
			updateEvent($event);
		}
	}
	
}


function blnfmcalsync_page_function() {
	global $blnfmSyncWarnings;

	echo '<div class="wrap">';

	if(@$_POST['syncnow']) {
		echo '<div class="updated">
		<p>Veranstaltungen sollten jetzt synchronisiert sein.</p>
		</div>';

		update_option( 'blnfmcalsync-lastsync', time());
		updateEvents();
	}

	foreach($blnfmSyncWarnings as $blnfmSyncWarning) {
		echo '<div class="notice notice-warning">
		<p>'.$blnfmSyncWarning.'</p>
		</div>';
	}

	echo '</div>';


	$lastSynced = get_option( 'blnfmcalsync-lastsync' );

	echo '<div class="wrap">
	<h1>Mit Google Spreadsheet synchronisieren</h1>
	<p><b>Spreadsheet URLs:</b> </p>
	<ul>
	';

	foreach(getSpreadsheets() as $spreadsheet) echo '<li>'.$spreadsheet.'</li>';

	echo '
	<p>Letztes mal synchronisiert: '.date('d.m.Y H:i', $lastSynced).' Uhr</p>
	<p>Button klicken um die Synchronisation zu starten.</p>
	<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
	<input type="submit" id="dbem_options_submit" class="button-primary" name="syncnow" value="Jetzt synchronisieren">
	</form>';
}



function blnfmcalsync_page() {
	add_plugins_page( 'BLN.FM Cal Sync', 'BLN.FM Cal Sync', 'None', 'BLNFMCalSync', 'blnfmcalsync_page_function');
	add_submenu_page( 'edit.php?post_type=event', 'BLN.FM Cal Sync', 'BLN.FM Cal Sync', 'manage_options', 'BLNFMCalSync', 'blnfmcalsync_page_function');
}

add_action('admin_menu', 'blnfmcalsync_page');


?>
