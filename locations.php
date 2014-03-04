<?php
/*
Plugin Name: Locations
Plugin Script: locations.php
Plugin URI: http://goldplugins.com/our-plugins/locations/
Description: List your business' locations and show a map for each one.
Version: 1.1
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

class LocationsPlugin extends GoldPlugin
{
	function __construct()
	{
		$this->create_post_types();
		$this->add_hooks();
		$this->add_stylesheets_and_scripts();
		$this->add_settings_page('Locations', 'Locations');		
		parent::__construct();
	}
	
	function add_hooks()
	{
		add_action('init', array($this, 'remove_features_from_locations'));
		add_shortcode('locations', array($this, 'locations_shortcode'));
		add_filter('manage_location_posts_columns', array($this, 'locations_column_head'), 10);  
		add_action('manage_location_posts_custom_column', array($this, 'locations_columns_content'), 10, 2); 
		
		parent::add_hooks();
	}
	
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
		$customFields[] = array('name' => 'fax', 'title' => 'Fax', 'description' => 'Fax number of this location, example: 919-555-3344', 'type' => 'text');
		$customFields[] = array('name' => 'email', 'title' => 'Email', 'description' => 'Email address for this location, example: shopname@ourbrand.com', 'type' => 'text');
		$customFields[] = array('name' => 'website_url', 'title' => 'Website', 'description' => 'Website URL address for this location, example: http://goldplugins.com', 'type' => 'text');
		$customFields[] = array('name' => 'show_map', 'title' => 'Show Map', 'description' => 'If checked, a Google Map with a marker at the above address will be displayed.', 'type' => 'checkbox');
		$this->add_custom_post_type($postType, $customFields);
		
	}
	
	/* Disable some of the normal WordPress features on the Location custom post type (the editor, author, comments, excerpt) */
	function remove_features_from_locations()
	{
		remove_post_type_support( 'location', 'editor' );
		remove_post_type_support( 'location', 'excerpt' );
		remove_post_type_support( 'location', 'comments' );
		remove_post_type_support( 'location', 'author' );
	}

	function add_stylesheets_and_scripts()
	{
		$cssUrl = plugins_url( 'assets/css/locations.css' , __FILE__ );
		$this->add_stylesheet('wp-locations-css',  $cssUrl);
		
/* 		$jsUrl = plugins_url( 'assets/js/wp-banners.js' , __FILE__ );
		$this->add_script('wp-banners-js',  $jsUrl);
 */		
		
	}
	
	function add_settings_page()
	{
	}
	
	/* Shortcodes */
	
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
		if ($all_locations && count($all_locations > 0))
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
		if (strlen($fax) > 1) {
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
			if (strlen($email) > 1) {
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
	
}
$gp_lp = new LocationsPlugin();