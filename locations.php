<?php
/*
Plugin Name: Locations
Plugin Script: locations.php
Plugin URI: http://goldplugins.com/our-plugins/locations/
Description: List your business' locations and show a map for each one.
Version: 1.4.1
Author: GoldPlugins
Author URI: http://goldplugins.com/

This file is part of Locations.

Locations is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Locations is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Locations.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once('gold-framework/plugin-base.php');
require_once('gold-framework/loc_p_kg.php');

class LocationsPlugin extends GoldPlugin
{
	var $google_geocoder_api_key = '';
	
	function __construct()
	{
		$this->isValidKey();
		$this->create_post_types();
		$this->add_hooks();
		$this->add_stylesheets_and_scripts();				
		$this->set_google_maps_api_key();		
		parent::__construct();
	}

	function add_hooks()
	{
		/* Remove unneeded meta boxes from the Locations custom post type */
		add_action('init', array($this, 'remove_features_from_locations'));

		/* Create the shortcodes */
		add_shortcode('locations', array($this, 'locations_shortcode'));
		if ($this->isValidKey()) {
			add_shortcode('store_locator', array($this, 'store_locator_shortcode'));
		}
		
		/* Customize the Locations "View All" list */
		add_filter('manage_location_posts_columns', array($this, 'locations_column_head'), 10);  
		add_action('manage_location_posts_custom_column', array($this, 'locations_columns_content'), 10, 2); 

		/* Add a menu item for the settings page */
		add_action('admin_menu', array($this, 'add_locations_settings_page')); 
		
		/* Add a menu item for the Help & Instructions page */
		add_action('admin_menu', array($this, 'add_locations_help_page')); 		

		/* Add any hooks that the base class has setup */
		parent::add_hooks();
	}
	
	/* Creates the Locations custom post type */
	function create_post_types()
	{
		$postType = array('name' => 'Location', 'plural' => 'Locations', 'slug' => 'locations');
		$customFields = array();
		//$customFields[] = array('name' => 'name', 'title' => 'Name', 'description' => 'Name of this location', 'type' => 'text');	
		$customFields[] = array('name' => 'street_address', 'title' => 'Street Address', 'description' => 'Example: 127 North St.', 'type' => 'text');	
		$customFields[] = array('name' => 'street_address_line_2', 'title' => 'Street Address (line 2)', 'description' => 'Example: Suite 420', 'type' => 'text');	
		$customFields[] = array('name' => 'city', 'title' => 'City', 'description' => 'Example: Carrington', 'type' => 'text');
		$customFields[] = array('name' => 'state', 'title' => 'State', 'description' => 'Example: NC', 'type' => 'text');
		$customFields[] = array('name' => 'zipcode', 'title' => 'Zipcode', 'description' => 'Example: 27601', 'type' => 'text');
		$customFields[] = array('name' => 'phone', 'title' => 'Phone', 'description' => 'Primary phone number of this location, example: 919-555-3333', 'type' => 'text');
		$customFields[] = array('name' => 'website_url', 'title' => 'Website', 'description' => 'Website URL address for this location, example: http://goldplugins.com', 'type' => 'text');
		$customFields[] = array('name' => 'show_map', 'title' => 'Show Google Map', 'description' => 'If checked, a Google Map with a marker at the above address will be displayed.', 'type' => 'checkbox');
		$customFields[] = array('name' => 'latitude', 'title' => 'Latitude', 'description' => 'Latitude of this location. You can leave this blank, and we will calculate it for you based on the address you entered (with the Google Maps geocoder).', 'type' => 'text');
		$customFields[] = array('name' => 'longitude', 'title' => 'Longitude', 'description' => 'Longitude of this location. You can ignore this field, and we will calculate it for you based on the address you entered (with the Google Maps geocoder).', 'type' => 'text');


		$showEmail = get_option('loc_p_show_email', true);
		if ($showEmail) {
			$customFields[] = array('name' => 'email', 'title' => 'Email', 'description' => 'Email address for this location, example: shopname@ourbrand.com', 'type' => 'text');
		}

		$showFax = get_option('loc_p_show_fax_number', true);		
		if ($showFax) {
			$customFields[] = array('name' => 'fax', 'title' => 'Fax', 'description' => 'Fax number of this location, example: 919-555-3344', 'type' => 'text');
		}

		$this->add_custom_post_type($postType, $customFields);
		
		// add a hook to geocode addresses if needed, which runs *after* we have already saved the custom fields for this location
		add_action( 'save_post', array( &$this, 'geocode_post_on_save' ), 8, 2 );
		
	}
	
	/* Load the Google Maps API Key from the plugin settings into a member variable. Called on init. */
	function set_google_maps_api_key()
	{
		$this->google_geocoder_api_key = get_option('loc_p_google_maps_api_key', '');		
		// TODO: should we show a warning on the settings page if this has not been set?
	}	
	
	/* Automatically geocodes the addresses of new Locations (uses Google Maps geocoder) */
	function geocode_post_on_save( $post_id, $post )
	{
		if ( !current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
			
		$myLat = get_post_meta($post_id, '_ikcf_latitude', true);
		$myLon = get_post_meta($post_id, '_ikcf_longitude', true);
		$addrHash = get_post_meta($post_id, 'addr_hash', true);
			
		$myAddress = $this->get_address($post_id);
		$addressChanged = ( $addrHash != md5($myAddress) );

		// if either the latitude or the longitude is unknown, or the address has changed, we should geocode it now
		if ( $myLat == "" || $myLon == "" || $addressChanged )
		{
			// calculate the lat and lon based on the address provided
			$myCoordinates = $this->geocode_address($myAddress);
							
			// if the geocode worked, update the latitude and/or the longitude 
			// NOTE: we should only update previously empty values, so that the user can tweak them if needed
			if ($myCoordinates !== FALSE)
			{
				// if latitude was unknown or the address changed, we should update it now
				if ($myLat == "" || $addressChanged) {
					update_post_meta($post_id, '_ikcf_latitude', $myCoordinates['lat']);
				}
					
				// if longitude was unknown or the address changed, we should update it now
				if ($myLon == "" || $addressChanged) {
					update_post_meta($post_id, '_ikcf_longitude', $myCoordinates['lng']);
				}
				
				// update the address hash meta, so we don't geocode again on the next save
				update_post_meta($post_id, 'addr_hash', md5($myAddress));
			}
		}
	}
	
	/* Disables some of the normal WordPress features on the Location custom post type (the editor, author, comments, excerpt) */
	function remove_features_from_locations()
	{
		remove_post_type_support( 'location', 'editor' );
		remove_post_type_support( 'location', 'excerpt' );
		remove_post_type_support( 'location', 'comments' );
		remove_post_type_support( 'location', 'author' );
	}
	
	// have to enqueue built in scripts on wp_enqueue_scripts
	function locations_add_script(){
		wp_enqueue_script( 'jquery' );
	}
	
	/* Enqueue our CSS files and Javascripts. Adds jQuery and Google Maps as well. */
	function add_stylesheets_and_scripts()
	{
		$cssUrl = plugins_url( 'assets/css/locations.css' , __FILE__ );
		$this->add_stylesheet('wp-locations-css',  $cssUrl);
				
		//add JS
		add_action( 'wp_enqueue_scripts', array($this, 'locations_add_script' ));
		
		$gmapsUrl = '//maps.google.com/maps/api/js?sensor=false';
		$this->add_script('gmaps-js',  $gmapsUrl);

		$jsUrl = plugins_url( 'assets/js/locations.js' , __FILE__ );
		$this->add_script('locations-js',  $jsUrl);
	}
	
	/* Shortcodes */

	/* output a store locator search box, and/or a list of results */
	function store_locator_shortcode($atts, $content = '')
	{
		// merge any settings specified by the shortcode with our defaults
		$defaults = array(	'caption' => '',
							'style' =>	'small',
							'show_photos' => 'true',
							'id' => ''
						);
		$atts = shortcode_atts($defaults, $atts);	

		// start the HTML output with a wrapper div
		$id_attr = strlen($atts['id']) > 0 ? ' id="' . htmlentities($atts['id']) . '"' : '';
		$html = '<div class="store_locator"' . $id_attr . '>';

		// add the caption, if one was specified
		if (strlen($atts['caption']) > 1) {
			$html .= '<h2 class="store_locator_caption">' . htmlentities($atts['caption']) . '</h2>';
		}
			
		// if a search was requested, perform it now and show the results
		if (isset($_REQUEST['search_locations']) && isset($_REQUEST['your_location']) && strlen(trim($_REQUEST['your_location'])) > 0)
		{
			// perform the search
			$your_location = $_REQUEST['your_location'];
			$radius_miles = $this->get_search_radius_in_miles();
			$radius_pretty = get_option('loc_p_search_radius');
			$nearest_locations = $this->find_nearest_locations($your_location, $radius, $origin); // second param is radius
			
			// generate the SERP (or the message saying "no results found")
			$html .= $this->store_locator_results_html($your_location, $radius_pretty, $nearest_locations, $origin);
			$html .= '<h3>Try Your Search Again:</h3>';
		}

		// add the search form
		$current_search = isset($your_location) ? htmlentities($your_location) : '';
		$html .= $this->store_locator_search_form_html($current_search);		
		
		// close the store_locator div and return the finished HTML
		$html .= '</div>'; // <!--.store_locator-->
		return $html;		
	}
	
	function get_search_radius_in_miles()
	{
		$m_or_km = get_option('loc_p_miles_or_km', 'miles');
		$radius = intval(get_option('loc_p_search_radius', 0));
		if ($radius < 1) { 
			$radius = 1;			
		} else if ($radius > 250) { 
			$radius = 250;
		}
		if ($m_or_km == 'km') { // convert kilometers to miles if needed
			return ($radius / .621371);
		} else {
			return $radius;
		}
	}
	
	function store_locator_results_html($your_location, $radius, $nearest_locations, $origin)
	{
		$markers = array();
		$html = '';
		
		// if results were found, output the serp
		if ( $nearest_locations  !== FALSE && count($nearest_locations) > 0 )
		{
			$html .= '<p><strong>Locations nearest to ' . htmlentities($your_location) . '</strong></p>';
			$html .= '<div id="map-canvas" style="width: 550px; min-width: 500px; max-width: 800px; height: 400px; border: 1px solid #ccc;"></div>';
			$html .= '<ol class="locations_search_results">';
			foreach ( $nearest_locations as $loc )
			{
				$html .= $this->store_locator_item_html($loc);
				$markers[] = $this->store_locator_item_build_marker_data($loc);
			}
			$html .= '</ol>';									
				
			// pass the origin and markers data to the page, so that it can be rendered on the map
			$html .= $this->location_data_js($markers, $origin);
			
		}
		else
		{
			$miles_or_km = (get_option('loc_p_miles_or_km', 'miles') == 'miles') ? 'miles' : 'kilometers';
			$html .= '<p class="no_locations">No locations found within ' . htmlentities($radius) . ' ' . $miles_or_km . ' of ' . htmlentities($your_location) .'.</p>';
		}	
		return $html;
	}	
	
	function location_data_js($markers, $origin)
	{
		$html = '';
		$html .= '<script type="text/javascript">';
		$html .= 'var $_gp_map_locations = ' . json_encode($markers) . ';';
		$html .= 'var $_gp_map_center = ' . json_encode($origin) . ';';
		$html .= '</script>';	
		return $html;
	}
	
	function store_locator_item_html($loc)
	{
		$html = '';
		$addr = htmlentities($loc['street_address']);
		$miles_or_km = (get_option('loc_p_miles_or_km', 'miles') == 'miles') ? 'miles' : 'kilometers';
		if (isset($loc['street_address_line_2']) && strlen($loc['street_address_line_2']) > 0) {
			$addr .= '<br />' . htmlentities($loc['street_address_line_2']);
		}
		
		$html .= '<li class="location noPhoto">';
			$html .= '<h3>' . $loc['title'] . '</h3>';
			$html .= '<div class="address"><div class="addr">' . $addr . '</div><div class="city_state_zip"><span class="city">' . htmlentities($loc['city']) . ', <span class="state">' . htmlentities($loc['state']) . ' <span class="zipcode">' . htmlentities($loc['zipcode']) . '</span></span></span></div></div>';
			$html .= '<div class="phone-wrapper"><strong>Phone:</strong> <span class="num phone">' . htmlentities($loc['phone']) . '</span></div>';
			$html .= '<div class="distance-wrapper"><em>' . htmlentities($loc['distance']) . ' ' . $miles_or_km . ' away</em></div>';
		$html .= '</li>';	
		return $html;
	}
	
	function store_locator_item_build_marker_data($loc)
	{
		$js_address = $loc['street_address'];
		if (isset($loc['street_address_line_2']) && strlen($loc['street_address_line_2']) > 0) {
			$js_address .= "\n" . $loc['street_address_line_2'];
		}
		$js_address .= "<br />" . $loc['city'] . ", " . $loc['state'] . ' ' . $loc['zipcode'];
		
		$data = array(
				'title' => html_entity_decode($loc['title']),
				'address' => $js_address, 
				'distance' => $loc['distance'], 
				'phone' => $loc['phone'], 
				'lat' => $loc['lat'], 
				'lng' => $loc['lng'] 
		);

		$showEmail = get_option('loc_p_show_email', true);
		if ($showEmail && isset($loc['email']) && strlen($loc['email']) > 0) {
			$data['email'] = $loc['email'];
		}

		$showFax = get_option('loc_p_show_fax_number', true);		
		if ($showFax && isset($loc['fax']) && strlen($loc['fax']) > 0) {
			$data['fax'] = $loc['fax'];
		}
		return $data;
	}
	
	// add the search form
	function store_locator_search_form_html($current_search = '')
	{
		$html = '';
		$search_url = add_query_arg( 'search_locations', '1');
		$html .= '<form method="POST" action="' . $search_url . '">';
			$html .= '<div class="input_wrapper">';
				$html .= '<label for="your_location">Your Location:</label>';
				$html .= '<input name="your_location" id="your_location" type="text" value="' . htmlentities($current_search) . '"/>';
			$html .= '</div>';
			$html .= '<div class="input_wrapper submit_wrapper">';
				$html .= '<button type="submit" class="btn btn-search">Search Now</button>';
			$html .= '</div>';
		$html .= '</form>';
		return $html;
	}
	
	/* Given a starting address, returns all locations within the specified radius, sorted by distance from the starting address (closest location first)
	 * Note: this function assumes that the locations have already been geocoded
 	*/
	function find_nearest_locations($starting_address, $radius_in_miles, &$origin = false)
	{
		global $wpdb;
	
		// get starting coordinates based on the starting address
		$origin = $this->geocode_address($starting_address);
		if ($origin === FALSE) {
			return false; // invalid address! should this raise an error? (TBD)
		}
		
		// calculate the acceptable ranges for latitude/longitude
		$lat_range = $radius_in_miles/69.172;
		$lon_range = abs($radius_in_miles/(cos($origin['lat']) * 69.172));
		$min_lat = number_format($origin['lat'] - $lat_range, "4", ".", "");
		$max_lat = number_format($origin['lat'] + $lat_range, "4", ".", "");
		$min_lng = number_format($origin['lng'] - $lon_range, "4", ".", "");
		$max_lng = number_format($origin['lng'] + $lon_range, "4", ".", "");

		return $this->find_locations_within_bounds($min_lat, $max_lat, $min_lng, $max_lng, $origin);
		
	}
	
	function find_locations_within_bounds($min_lat, $max_lat, $min_lng, $max_lng, $origin)
	{
		// setup the arguments for WP_Query to find locations within this lat/lng range
		$args = array(
			'post_type' => 'location',
			'meta_query' => array(
				array(
					'key' => '_ikcf_latitude',
					'value' => array ($min_lat, $max_lat) ,
					'type' => 'DECIMAL',
					'compare' => 'BETWEEN'
				),
				array(
					'key' => '_ikcf_longitude',
					'value' => array ($min_lng, $max_lng) ,
					'type' => 'DECIMAL',
					'compare' => 'BETWEEN'
				)
			)
		);
		
		// see if any locations match. if so, return the results. if not, return an empty array
		$query = new WP_Query( $args );		
		if ( $query->have_posts() ) {
			// We found some locations! 
			// now, lets pull them out of the WP_Query object and into an array
			// then we'll also sort them by distance from the origin, and add that distance to the array so it can be shown in the SERP
			$all_locations = array();
			while ( $query->have_posts() )
			{
				$query->next_post();
				$myId = $query->post->ID;
				$loc = $this->get_location_metadata($myId);
				$loc['title'] = get_the_title($myId);
				$loc['lat'] = get_post_meta($myId, '_ikcf_latitude', true);
				$loc['lng'] = get_post_meta($myId, '_ikcf_longitude', true);
							
				// calculate the distance, and add it as a key
				$miles_or_km = get_option('loc_p_miles_or_km', 'miles');
				$loc['distance'] = $this->distance_between_coords($loc['lat'], $loc['lng'], $origin['lat'], $origin['lng'], $miles_or_km);

				// add this location to the unsorted list
				$all_locations[] = $loc;				
			}			
			
			// Restore original Post Data, so as not to mess up any other Loops
			wp_reset_postdata();
			
			
			// sort the list of locations by their distance keys, and then return the sorted list
			usort($all_locations, array($this, 'sort_by_distance'));
			return $all_locations;
		} else {
			return array();
		}
	}
	
	/* Sorts an array by its 'distance' key.
	 * Used for sorting the store locator's search results by their distance from the origin 
	 */
	function sort_by_distance($a, $b)
	{
		if ($a['distance'] == $b['distance']) {
			return 0;
		}
		return ($a['distance'] < $b['distance']) ? -1 : 1;
	}
	
	// calculates approximate distance between 2 lat/lng pairs, using the haversine formula
	function distance_between_coords( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $milesOrKm = 'miles')
	{
		// constants
		$earthRadius_meters = 6371000;
		$feetPerMeter = 3.2808399;
		$feetPerMile = 5280;
		
		// convert from degrees to radians
		$latFrom = deg2rad($latitudeFrom);
		$lonFrom = deg2rad($longitudeFrom);
		$latTo = deg2rad($latitudeTo);
		$lonTo = deg2rad($longitudeTo);

		// calculate the distance between the points in radians
		$latDelta = $latTo - $latFrom;
		$lonDelta = $lonTo - $lonFrom;

		// using the haversine formula, calculate the angular distance travled and then convert it into meters
		$angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +	cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
		$distance_meters = $angle * $earthRadius_meters;
		
		// we have distance in meters, but we need it in miles or kilometers), so we convert it now
		if ($milesOrKm == 'miles')
		{
			$distance_feet = floatval($distance_meters) * $feetPerMeter;
			$distance_miles = ($distance_feet / $feetPerMile);
			
			// round the result (in miles) to 2 decimal places, and return it
			$pretty_miles = number_format($distance_miles, "2", ".", "");
			return $pretty_miles;
		}
		else // km
		{
			$distance_kilometers = $distance_meters / 1000;
			$pretty_distance = number_format($distance_kilometers, "2", ".", "");
			return $pretty_distance;
		}		
	}
	
	function geocode_address($address)
	{
		$params = array('address' => urlencode($address),
						'key' => $this->google_geocoder_api_key);
		$param_str = build_query($params);
		$api_url = 'https://maps.googleapis.com/maps/api/geocode/json?' . $param_str;
		$api_response = wp_remote_post($api_url);

		if (is_wp_error($api_response)) {
			return false; // TBD: do we need to throw an error here?
		}
		
		// geocode worked! pull out the lat and lng values from the response, and return them as an array
		$api_json = json_decode($api_response['body']);
		if ($api_json) {
			$lat = isset($api_json->results[0]->geometry->location->lat) ? $api_json->results[0]->geometry->location->lat : '';
			$lng = isset($api_json->results[0]->geometry->location->lng) ? $api_json->results[0]->geometry->location->lng : '';
			if ($lat == '' || $lng == '') {
				return false;
			} else {			
				return array('lat' => $lat, 'lng' => $lng);
			}
		}
		else {
			// something went wrong! TBD: dig into the specific errors Google can return, and see if any should be bubbled up to the user
			return false;
		}
	}
	
	/* output a list of all locations */
	function locations_shortcode($atts, $content = '')
	{
		// merge any settings specified by the shortcode with our defaults
		$defaults = array(	'caption' => '',
							'style' =>	'small',
							'show_photos' => 'true',
							'id' => ''
						);
		$atts = shortcode_atts($defaults, $atts);
						
		if(!is_numeric($atts['id'])){
			// get a list of all the locations
			$all_locations = $this->get_all_locations();
		} else {
			$all_locations = $this->get_a_location($atts['id']);			
			// get a specific location
		}
		
		// start the HTML output with a wrapper div
		$html = '<div class="locations">';

		// add the caption, if one was specified
		if (strlen($atts['caption']) > 1) {
			$html .= '<h2 class="locations-caption">' . htmlentities($atts['caption']) . '</h2>';
		}

		// loop through the locations, and add the generated HTML for each location
		if ($all_locations && count($all_locations) > 0)
		{		
			foreach ($all_locations as $loc) {			
				$html .= $this->build_location_html($loc);
			}
		}
		
		// close the locations div and return the finished HTML
		$html .= '</div>'; // <!--.locations-->
		return $html;
	}
	
	// generates the HTML for a single location. 
	// NOTE: this is a helper function for the locations_shortcode function
	private function build_location_html($loc)
	{
		// load the meta data for this location (name, address, zipcode, etc)
		$title = $loc->post_title;
		$street_address = $this->get_option_value($loc->ID, 'street_address','');
		$street_address_line_2 = $this->get_option_value($loc->ID, 'street_address_line_2','');
		$city = $this->get_option_value($loc->ID, 'city','');
		$state = $this->get_option_value($loc->ID, 'state','');
		$zipcode = $this->get_option_value($loc->ID, 'zipcode','');
		$phone = $this->get_option_value($loc->ID, 'phone','');
		$fax = $this->get_option_value($loc->ID, 'fax','');
		$email = $this->get_option_value($loc->ID, 'email','');
		$website_url = $this->get_option_value($loc->ID, 'website_url','');
		$add_map = $this->get_option_value($loc->ID, 'show_map', false);
		$showEmail = get_option('loc_p_show_email', true);
		$showFax = get_option('loc_p_show_fax_number', true);		
		
		// start building the HTML for this location
		$html = '';
		
		// add the featured image, if one was specified
		$img_html = $this->build_featured_image_html($loc);
		$hasPhoto = (strlen($img_html) > 0);
				
		// start the location div. Add the hasPhoto or noPhoto class, depending on whether a featured image was specified
		$html .= '<div class="location ' . ($hasPhoto ? 'hasPhoto' : 'noPhoto') . '">';
		$html .= $img_html; // $img_html may be empty
						
		// add the location's title
		$html .= '<h3>' . htmlentities($title) . '</h3>';
			
		// add the address, with each part wrapped in its own HTML tag
		$html .= $this->build_address_html($street_address, $street_address_line_2, $city, $state, $zipcode);
		
		// add the phone number and fax (if specified)
		if (strlen($phone) > 1) {
			$html .= '<div class="phone-wrapper"><strong>Phone:</strong> <span class="num phone">' . htmlentities($phone) . '</span></div>';
		}
		if (strlen($fax) > 1 && $showFax) {
			$html .= '<div class="fax-wrapper"><strong>Fax:</strong> <span class="num fax">' . htmlentities($fax) . '</span></div>';
		}				
		
		// add links for Map, Directions, Email, and Website
		$html .= $this->build_links_html($street_address, $street_address_line_2, $city, $state, $zipcode, $email, $website_url, $add_map);
		
		if($add_map){
			$address = htmlentities($street_address . ", " . $street_address_line_2 . ", " . $city . ", " . $state . ", " . $zipcode);
			
			$html .= '<div class="locations_gmap"><iframe width="425" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?f=q&amp;source=s_q&amp;hl=en&amp;geocode=&amp;q=' . $address . '&amp;t=h&amp;ie=UTF8&amp;hq=&amp;hnear=' . $address . '&amp;z=14&amp;output=embed"></iframe></div>';
		}
		
		// close the location div and return the finished HTML
		$html .= '</div>'; // <!--.location-->		
		return $html;
	}
	
	private function build_featured_image_html($loc)
	{
		$img_html = '';
		$post_thumbnail_id = get_post_thumbnail_id( $loc->ID );
		if ($post_thumbnail_id !== '' && $post_thumbnail_id > 0)
		{
			$hasPhoto = true;
			$img_src = wp_get_attachment_image_src($post_thumbnail_id, 'medium');
			$banner_style = "background-image: url('" . $img_src[0] . "');";
			$img_html .= '<div class="location-photo" style="' . $banner_style . '">';
			$img_html .= '</div>'; // <!--.location-photo>
		}
		
		return $img_html;
	
	}
	
	private function build_address_html($street_address, $street_address_line_2, $city, $state, $zipcode)
	{
		$html = '<div class="address">';
			$html .= '<div class="addr">' . htmlentities($street_address) . '</div>';
			if (strlen($street_address_line_2) > 0) {
				$html .= '<div class="addr2">' . htmlentities($street_address_line_2) . '</div>';
			}
			$html .= '<div class="city_state_zip">';
				$html .= '<span class="city">' . htmlentities($city);
				$html .= ', ' . '<span class="state">' . htmlentities($state);
				$html .= ' ' . '<span class="zipcode">' . htmlentities($zipcode) . '</span>';
			$html .= '</div>'; // <!--.city_state_zip-->
		$html .= '</div>'; // <!--.address-->
		return $html;
	}
	
	private function build_links_html($street_address, $street_address_line_2, $city, $state, $zipcode, $email = '', $website_url = '', $add_map = false)
	{
		$showEmail = get_option('loc_p_show_email', true);
	
		// generate the Google Maps links
		if (strlen($street_address_line_2) > 0) {
			$full_address = $street_address . ', ' . $street_address_line_2 . ' ' . $city . ', ' . $state . ' ' . $zipcode;
		} else {
			$full_address = $street_address . ' ' . $city . ', ' . $state . ' ' . $zipcode;		
		}
		$google_maps_url = 'https://maps.google.com/?q=' . urlencode($full_address);
		$google_maps_directions_url = 'https://maps.google.com/maps?saddr=current+location&daddr=' . urlencode($full_address);
		
		// generate the HTML for the actual links
		$html = '<div class="map_link">';
			if(!$add_map){
				$html .= '<a href="' . $google_maps_url. '">Map</a>'; 
				$html .= ' <span class="divider">|</span> ';
			}
			$html .= '<a href="' . $google_maps_directions_url. '">Directions</a>';
			if (strlen($email) > 1 && $showEmail) {
				$html .= ' <span class="divider">|</span> ';
				$html .= '<a class="email" href="mailto:' . $email . '">Email</a>';
			}
			if (strlen($website_url) > 1) {
				$html .= ' <span class="divider">|</span> ';
				$html .= '<a class="website" href="' . $website_url . '">Website</a>';
			}
			if($add_map){
				$html .= ' <span class="divider">|</span> ';
				$html .= '<a href="' . $google_maps_url. '">View Full Map</a>'; 
			}
		$html .= '</div>'; // <!--.map_link-->
		
		
		return $html;

	}
	
	
	// returns a list of all locations in the database, sorted by the title, ascending
	private function get_all_locations()
	{
		$conditions = array('post_type' => 'location',
							'post_count' => -1,
							'order_by' => 'title',
							'order' => 'ASC',
					);
		$all_locations = get_posts($conditions);	
		return $all_locations;
	}
	
	// returns a list of a location in the database, based on the ID passed
	private function get_a_location($id = '')
	{
		$conditions = array('p' => $id,								
							'post_type' => 'location',
							'post_count' => -1,
							'order_by' => 'title',
							'order' => 'ASC',
					);
		$location = get_posts($conditions);	
		return $location;
	}

	/* Returns a formatted address for the specified Location */
	function get_address($post_id)
	{
		// load the required metadata
		$addr['street_address'] = $this->get_option_value($post_id, 'street_address','');
		$addr['street_address_line_2'] = $this->get_option_value($post_id, 'street_address_line_2','');
		$addr['city'] = $this->get_option_value($post_id, 'city','');
		$addr['state'] = $this->get_option_value($post_id, 'state','');
		$addr['zipcode'] = $this->get_option_value($post_id, 'zipcode','');
		
		// build the address string
		$address = '';
		$address .= $addr['street_address'];
		if (strlen($addr['street_address_line_2']) > 0) {
			$address .= ' ' . $street_address_line_2;
		}
		$address .= ' ';
		$address .= $addr['city'];
		$address .= ', ';
		$address .= $addr['state'];
		$address .= ' ';
		$address .= $addr['zipcode'];
		
		// return the completed string
		return $address;
	}
	
	/* Loads the meta data for a given location (name, address, zipcode, etc) and returns it as an array */
	function get_location_metadata($post_id)
	{
		$ret = array();
		$loc = get_post($post_id);
		$ret['title'] = $loc->post_title;
		$ret['street_address'] = $this->get_option_value($loc->ID, 'street_address','');
		$ret['street_address_line_2'] = $this->get_option_value($loc->ID, 'street_address_line_2','');
		$ret['city'] = $this->get_option_value($loc->ID, 'city','');
		$ret['state'] = $this->get_option_value($loc->ID, 'state','');
		$ret['zipcode'] = $this->get_option_value($loc->ID, 'zipcode','');
		$ret['phone'] = $this->get_option_value($loc->ID, 'phone','');
		$ret['fax'] = $this->get_option_value($loc->ID, 'fax','');
		$ret['email'] = $this->get_option_value($loc->ID, 'email','');
		$ret['website_url'] = $this->get_option_value($loc->ID, 'website_url','');
		$ret['add_map'] = $this->get_option_value($loc->ID, 'show_map', false);		
		return $ret;
	}
	
		 
	//this is the heading of the new column we're adding to the locations posts list
	function locations_column_head($defaults) {  
		$defaults = array_slice($defaults, 0, 2, true) +
		array("single_shortcode" => "Shortcode") +
		array_slice($defaults, 2, count($defaults)-2, true);
		return $defaults;  
	}  

	//this content is displayed in the location post list
	function locations_columns_content($column_name, $post_ID) {  
		if ($column_name == 'single_shortcode') {  
			echo "<code>[locations id={$post_ID}]</code>";
		}  
	} 
	
	/* Create a menu item for the Location Settings page */
	function add_locations_settings_page()
	{			
		//$locations_options = new locationsOptions();
		add_submenu_page('edit.php?post_type=location', 'Settings', 'Settings', 'edit_posts', 'locations-settings', array($this, 'output_location_settings_page'));
	}
	
	/* Create a menu item for the Location Help & Instructions page */
	function add_locations_help_page()
	{			
		add_submenu_page('edit.php?post_type=location', 'Help & Instructions', 'Help & Instructions', 'edit_posts', 'help-instructions', array($this, 'output_location_help_page'));
	}
	
	/* Render the Location Help & Instructions Page */
	function output_location_help_page(){
		include("pages/help.html");
	}
	
	/* Render the Location settings page, and save options that may be changed */
	function output_location_settings_page()
	{
		// Check that the user is allowed to update options
		if (!current_user_can('manage_options')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}	

		$plugin_options = array();
		$plugin_options[] = array('name' => 'loc_p_google_maps_api_key', 'label' => 'Google Maps API Key', 'desc' => 'Without a Google Maps API key, your plugin may not work. Get your API key <a href="https://developers.google.com/maps/documentation/javascript/tutorial#api_key" target="_blank">here</a>.' );
		$plugin_options[] = array('name' => 'loc_p_show_fax_number', 'label' => 'Show Fax Number', 'type' => 'checkbox', 'default' => '1');
		$plugin_options[] = array('name' => 'loc_p_show_email', 'label' => 'Show Email', 'type' => 'checkbox', 'default' => '1');
		
		$pro_options = array();
		$pro_options[] = array('name' => 'loc_p_miles_or_km', 'label' => 'Miles or Kilometers', 'desc' => 'Should the store locator show distances in miles or kilometers?', 'type' => 'radio', 'options' => array('miles' => 'Miles', 'km' => 'Kilometers'), 'default' => 'miles' );
		$pro_options[] = array('name' => 'loc_p_search_radius', 'label' => 'Search Radius', 'desc' => 'When a user searches for nearby locations, show stores that are within this distance.' );
		
		// save settings if needed
		if (isset($_POST["update_settings"]))
		{
			foreach($plugin_options as $opt) {
				$name = $opt['name'];
				if (isset($_POST[$name])) {
					$val = esc_attr($_POST[$name]);
					update_option($name, $val);
				}
			}

			if ($this->isValidKey())
			{
				foreach($pro_options as $opt) {
					$name = $opt['name'];
					if (isset($_POST[$name])) {
						$val = esc_attr($_POST[$name]);
						update_option($name, $val);
					}
				}
			}
			else
			{				
				// save registration keys if provided
				$reg_keys = array('loc_p_registration_api_key', 'loc_p_registration_website_url', 'loc_p_registration_email');
				foreach($reg_keys as $name) {
					if (isset($_POST[$name])) {
						$val = esc_attr($_POST[$name]);
						update_option($name, $val);
					}
				}
				$this->isValidKey(true);				
			}
		}

		// load the current setting values from the database (normal options)
		foreach($plugin_options as $index => $opt)
		{
			$name = $opt['name'];
			$def = isset($opt['default']) ? $opt['default'] : '';
			$plugin_options[$index]['val'] = get_option($opt['name'], $def);
		}
		
		// load the current setting values from the database (pro options)
		if ($this->isValidKey())
		{
			foreach($pro_options as $index => $opt)
			{
				$name = $opt['name'];
				$def = isset($opt['default']) ? $opt['default'] : '';
				$pro_options[$index]['val'] = get_option($opt['name'], $def);
			}
		}
?>
		<div class="wrap">
			<h2>Locations Plugin Settings</h2>
			<?php if (!$this->isValidKey()): ?>
			<?php $this->mailing_list_signup(); ?>
			<?php endif; ?>
			<form method="POST" action="">
				<h3>General Settings</h3>
				<table class="form-table">
					<?php foreach($plugin_options as $opt):
							$this->output_option_row($opt);
					endforeach; ?>
				</table>
				<p class="submit"><input type="submit" value="Save Changes" class="button button-primary" id="submit" name="submit"></p>				
				<h3>Registration Settings</h3>
				<style type="text/css">
				.locations_registered {
					background-color: #90EE90;
					font-weight: bold;
					padding: 20px;
					width: 860px;
				}
				.locations_not_registered {
					background-color: #FF8C00;
					font-weight: bold;
					padding: 20px;
					width: 860px;
				}
				</style>
				<?php if($this->isValidKey()): ?>	
				<p class="locations_registered">Your plugin is succesfully registered and activated!</p>
				<?php else: ?>
				<p class="locations_not_registered">Your plugin is not succesfully registered and activated. <a href="http://goldplugins.com/our-plugins/locations/" target="_blank">Click here</a> to upgrade today!</p>
				<?php endif; ?>	
				<?php if(!$this->isValidMSKey()): ?>
				<?php $this->pro_registration_form(); ?>		
				<p class="submit"><input type="submit" value="Save Changes" class="button button-primary" id="submit" name="submit"></p>
				<?php endif; ?>
				<h3>Store Locator Settings</h3>				
				<?php if(!$this->isValidKey()): ?>	
				<p class="upgrade"><strong><a href="http://goldplugins.com/our-plugins/locations/?utm_source=plugin&utm_campaign=upgrade_api_key">Haven't upgraded yet? Click Here to Purchase Locations Pro.</a></strong></p>		
				<?php else: ?>
				<table class="form-table">
				<?php foreach($pro_options as $opt):
							$this->output_option_row($opt);
				endforeach; ?>
				</table>
				<p class="submit"><input type="submit" value="Save Changes" class="button button-primary" id="submit" name="submit"></p>
				<?php endif; ?>
				<input type="hidden" name="update_settings" value="true" />				
			</form>
		</div>
<?php		
	}
	
	/* Helper function: Outputs a table row containing an input which can edit the given option */
	private function output_option_row($opt)
	{
		$def = isset($opt['default']) ? $opt['default'] : '';
		$val = isset($opt['val']) ? $opt['val'] : $def;
		$type = isset($opt['type']) ? $opt['type'] : 'text';
		$desc = isset($opt['desc']) ? $opt['desc'] : '';
?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo $opt['name'] ?>">
					<?php echo htmlentities($opt['label']) ?>
				</label>
			</th>
			<td>
			<?php
				$def = isset($opt['default']) ? $opt['default'] : '';
				$val = isset($opt['val']) ? $opt['val'] : $def;
				switch ($type):
					case 'checkbox':
			?>
					<input type="hidden" id="<?php echo $opt['name'] ?>" name="<?php echo $opt['name'] ?>" value="0" />
					<?php if ($val == '1'): ?>
					<input type="checkbox" id="<?php echo $opt['name'] ?>" name="<?php echo $opt['name'] ?>" checked="checked" value="1" />
					<?php else: ?>
					<input type="checkbox" id="<?php echo $opt['name'] ?>" name="<?php echo $opt['name'] ?>" value="1" />
					<?php endif; ?>
			<?php	
					break;

					case 'radio':
			?>
					<fieldset>
					<?php foreach($opt['options'] as $choice_val => $choice_display): ?>
						<label title="<?php echo $choice_val ?>">
							<?php if ( $val == $choice_val ) : ?>
							<input type="radio" name="<?php echo $opt['name'] ?>" value="<?php echo $choice_val ?>" checked="checked" />
							<?php else: ?>
							<input type="radio" name="<?php echo $opt['name'] ?>" value="<?php echo $choice_val ?>" />
							<?php endif; ?>
							<span><?php echo $choice_display; ?></span>
						</label>
						<br />
					<?php endforeach; ?>
					</fieldset>
			<?php
					break;

					case 'text':
					default:
			?>
					<input type="text" id="<?php echo $opt['name'] ?>" name="<?php echo $opt['name'] ?>" size="25" value="<?php echo $val ?>" />
			<?php
					break;
			
				endswitch;
			?>
			
			<?php if (strlen($desc) > 0): ?>
			<p class="description"><?php echo $opt['desc'] ?></p>
			<?php endif; ?>
			
			</td>
		</tr>
<?php	
	}
	
	/* Outputs the mailing list sign-up form */
	private function mailing_list_signup()
	{
?>
		<!-- Begin MailChimp Signup Form -->
		<style type="text/css">
			/* MailChimp Form Embed Code - Slim - 08/17/2011 */
			#mc_embed_signup form {display:block; position:relative; text-align:left; padding:10px 0 10px 3%}
			#mc_embed_signup h2 {font-weight:bold; padding:0; margin:15px 0; font-size:1.4em;}
			#mc_embed_signup input {border:1px solid #999; -webkit-appearance:none;}
			#mc_embed_signup input[type=checkbox]{-webkit-appearance:checkbox;}
			#mc_embed_signup input[type=radio]{-webkit-appearance:radio;}
			#mc_embed_signup input:focus {border-color:#333;}
			#mc_embed_signup .button {clear:both; background-color: #aaa; border: 0 none; border-radius:4px; color: #FFFFFF; cursor: pointer; display: inline-block; font-size:15px; font-weight: bold; height: 32px; line-height: 32px; margin: 0 5px 10px 0; padding:0; text-align: center; text-decoration: none; vertical-align: top; white-space: nowrap; width: auto;}
			#mc_embed_signup .button:hover {background-color:#777;}
			#mc_embed_signup .small-meta {font-size: 11px;}
			#mc_embed_signup .nowrap {white-space:nowrap;}     
			#mc_embed_signup .clear {clear:none; display:inline;}

			#mc_embed_signup h3 { color: #008000; display:block; font-size:19px; padding-bottom:10px; font-weight:bold; margin: 0 0 10px;}
			#mc_embed_signup .explain {
				color: #808080;
				width: 600px;
			}
			#mc_embed_signup label {
				color: #000000;
				display: block;
				font-size: 15px;
				font-weight: bold;
				padding-bottom: 10px;
			}
			#mc_embed_signup input.email {display:block; padding:8px 0; margin:0 4% 10px 0; text-indent:5px; width:58%; min-width:130px;}

			#mc_embed_signup div#mce-responses {float:left; top:-1.4em; padding:0em .5em 0em .5em; overflow:hidden; width:90%;margin: 0 5%; clear: both;}
			#mc_embed_signup div.response {margin:1em 0; padding:1em .5em .5em 0; font-weight:bold; float:left; top:-1.5em; z-index:1; width:80%;}
			#mc_embed_signup #mce-error-response {display:none;}
			#mc_embed_signup #mce-success-response {color:#529214; display:none;}
			#mc_embed_signup label.error {display:block; float:none; width:auto; margin-left:1.05em; text-align:left; padding:.5em 0;}		
			#mc_embed_signup{background:#fff; clear:left; font:14px Helvetica,Arial,sans-serif; }
				#mc_embed_signup{    
						background-color: white;
						border: 1px solid #DCDCDC;
						clear: left;
						color: #008000;
						font: 14px Helvetica,Arial,sans-serif;
						margin-top: 10px;
						margin-bottom: 0px;
						max-width: 800px;
						padding: 5px 12px 0px;
			}
			#mc_embed_signup form{padding: 10px}

			#mc_embed_signup .special-offer {
				color: #808080;
				margin: 0;
				padding: 0 0 3px;
				text-transform: uppercase;
			}
			#mc_embed_signup .button {
			  background: #5dd934;
			  background-image: -webkit-linear-gradient(top, #5dd934, #549e18);
			  background-image: -moz-linear-gradient(top, #5dd934, #549e18);
			  background-image: -ms-linear-gradient(top, #5dd934, #549e18);
			  background-image: -o-linear-gradient(top, #5dd934, #549e18);
			  background-image: linear-gradient(to bottom, #5dd934, #549e18);
			  -webkit-border-radius: 5;
			  -moz-border-radius: 5;
			  border-radius: 5px;
			  font-family: Arial;
			  color: #ffffff;
			  font-size: 20px;
			  padding: 10px 20px 10px 20px;
			  line-height: 1.5;
			  height: auto;
			  margin-top: 7px;
			  text-decoration: none;
			}

			#mc_embed_signup .button:hover {
			  background: #65e831;
			  background-image: -webkit-linear-gradient(top, #65e831, #5dd934);
			  background-image: -moz-linear-gradient(top, #65e831, #5dd934);
			  background-image: -ms-linear-gradient(top, #65e831, #5dd934);
			  background-image: -o-linear-gradient(top, #65e831, #5dd934);
			  background-image: linear-gradient(to bottom, #65e831, #5dd934);
			  text-decoration: none;
			}
			#signup_wrapper {
				max-width: 800px;
				margin-bottom: 20px;
			}
			#signup_wrapper .u_to_p
			{
				font-size: 10px;
				margin: 0;
				padding: 2px 0 0 3px;				
			]
		</style>
		<div id="signup_wrapper">
			<div id="mc_embed_signup">
				<form action="http://illuminatikarate.us2.list-manage2.com/subscribe/post?u=403e206455845b3b4bd0c08dc&amp;id=6ad78db648" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
					<p class="special-offer">Special Offer:</p>
					<h3>Sign-up for our mailing list now, and we'll give you a discount on Locations Pro!</h3>
					<label for="mce-EMAIL">Your Email:</label>
					<input type="email" value="" name="EMAIL" class="email" id="mce-EMAIL" placeholder="email address" required>
					<!-- real people should not fill this in and expect good things - do not remove this or risk form bot signups-->
					<div style="position: absolute; left: -5000px;"><input type="text" name="b_403e206455845b3b4bd0c08dc_6ad78db648" tabindex="-1" value=""></div>
					<div class="clear"><input type="submit" value="Subscribe Now" name="subscribe" id="mc-embedded-subscribe" class="button"></div>
					<p class="explain"><strong>What To Expect:</strong> <br/> As soon as you've confirmed your subscription, you'll receive a coupon code for a big discount on Locations Pro. After that, you'll receive about one email from us each month, jam-packed with special offers and tips for getting the most out of WordPress. Of course, you can unsubscribe at any time.</p>
				</form>
			</div>
			<p class="u_to_p"><a href="http://goldplugins.com/our-plugins/locations/?utm_source=plugin&utm_campaign=upgrade_now">Upgrade to Locations Pro now</a> to remove banners like this one.</p>
		</div>
		<!--End mc_embed_signup-->
<?php
	}
	
	/* Renders the Locations Pro registration form */
	function pro_registration_form()
	{
?>
		<div id="api_keys">
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="loc_p_registration_email">Email:</label>
					</th>
					<td>
						<input type="text" id="loc_p_registration_email" name="loc_p_registration_email" size="25" value="" />
						<!--<p class="description"></p>-->
					</td>
				</tr>
				<tr valign="top" style="display: none;">
					<th scope="row">
						<label for="loc_p_registration_website_url">Website URL:</label>
					</th>
					<td>
						<input type="text" id="loc_p_registration_website_url" name="loc_p_registration_website_url" size="25" value="" />
						<!--<p class="description"></p>-->
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="loc_p_registration_api_key">API Key:</label>
					</th>
					<td>
						<input type="text" id="loc_p_registration_api_key" name="loc_p_registration_api_key" size="25" value="" />
						<!--<p class="description"></p>-->
					</td>
				</tr>
			</table>
		</div>
<?php

	}

	/* Returns true/false indicating whether or not this is the Pro version */
	function isValidKey($skipCache = false)
	{
		// "cache" the key check status with a member variable
		if (!$skipCache && isset($this->valid_key)) {
			return $this->valid_key;
		}
		
		// first time running, so check the key and cache the result
		$email = get_option('loc_p_registration_email', '');
		$webaddress = get_option('loc_p_registration_website_url', '');
		$key = get_option('loc_p_registration_api_key', '');
		
		$checker = new LOC_P_KG();
		$computedKey = $checker->computeKey($webaddress, $email);
		$computedKeyEJ = $checker->computeKeyEJ($email);

		if ($key == $computedKey || $key == $computedKeyEJ) {
			$this->valid_key = true;
			return true;
		} else {
			$plugin = "locations-pro/locations-pro.php";			
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			
			if(is_plugin_active($plugin)){
				$this->valid_key = true;
				return true;
			}
		}
		$this->valid_key = false;
		return false;
	}

	/* Returns true/false indicating whether or not this is the Pro version */
	function isValidMSKey($skipCache = false)
	{
		$plugin = "locations-pro/locations-pro.php";			
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		
		if(is_plugin_active($plugin)){
			$this->valid_key = true;
			return true;
		}
			
		return false;
	}

}
$gp_lp = new LocationsPlugin();