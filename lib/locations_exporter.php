<?php
class LocationsPlugin_Exporter
{	
	public function output_form()
	{
		?>
		<form method="POST" action="">			
			<p>Click the "Export My Locations" button below to download a CSV file of your locations.</p>
			<input type="hidden" name="_gp_do_export" value="_gp_do_export" />
			<p class="submit">
				<input type="submit" class="button" value="Export My Locations" />
			</p>
		</form>
		<?php
	}
	
	public function process_export($filename = "locations-export.csv")
	{
		//load locations
		$args = array(
			'posts_per_page'   => -1,
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'post_date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_key'         => '',
			'meta_value'       => '',
			'post_type'        => 'location',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'publish',
			'suppress_filters' => true 				
		);
		
		$locations = get_posts($args);
		
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Description: File Transfer');
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename={$filename}");
		header("Expires: 0");
		header("Pragma: public");
		
		
		// open file handle to STDOUT
		$fh = @fopen( 'php://output', 'w' );
		
		// output the headers first
		fputcsv($fh, array('Name','Street Address','Street Address (line 2)','City','State','Zipcode','Phone','Website','Email','Fax','Latitude','Longitude'));
			
		// now output one row for each location
		foreach($locations as $location)
		{
			$street_address = get_post_meta( $location->ID, '_ikcf_street_address', true);
			$street_address_line_two = get_post_meta( $location->ID, '_ikcf_street_address_line_2', true);
			$city = get_post_meta( $location->ID, '_ikcf_city', true);
			$state = get_post_meta( $location->ID, '_ikcf_state', true);
			$zipcode = get_post_meta( $location->ID, '_ikcf_zipcode', true);
			$phone = get_post_meta( $location->ID, '_ikcf_phone', true);
			$email = get_post_meta( $location->ID, '_ikcf_email', true);
			$fax = get_post_meta( $location->ID, '_ikcf_fax', true);
			$website_url = get_post_meta( $location->ID, '_ikcf_website_url', true);
			$latitude = get_post_meta( $location->ID, '_ikcf_latitude', true);
			$longitude = get_post_meta( $location->ID, '_ikcf_longitude', true);
			
			fputcsv($fh, array($location->post_title, $street_address, $street_address_line_two, $city, $state, $zipcode, $phone, $website_url, $email, $fax, $latitude, $longitude));		
		}
		
		// Close the file handle
		fclose($fh);
	}
}