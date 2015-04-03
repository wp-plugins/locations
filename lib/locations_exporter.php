<?php
class locationsExporter
{	
	public function output_form(){
		?>
		<form method="POST" action="">			
			<label>CSV Filename</label>
			<div class="bikeshed bikeshed_text">
				<div class="text_wrapper">
					<input type="text" class="" value="locations-export.csv" id="csv_filename" name="csv_filename">
				</div>
				<p class="description">This is the desired filename of the export.  This will default to "locations-export.csv".</p>
			</div>
				
			<p class="submit">
				<input type="submit" class="button" value="Export locations" />
			</p>
		</form>
		<?php
	}
	
	public function process_export($filename = "locations-export.csv"){
		ob_start();
					
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
		
		$fh = @fopen( 'php://output', 'w' );
		
		$headerDisplayed = false;
			
		foreach($locations as $location){
			if ( !$headerDisplayed ) {
				// Use the keys from $data as the titles
				fputcsv($fh, array('Name','Street Address','Street Address (line 2)','City','State','Zipcode','Phone','Website','Email','Fax','Latitude','Longitude'));
				$headerDisplayed = true;
			}
			
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
		
		// Close the file
		fclose($fh);
		
		ob_end_flush();
	}
	
	//displays interface to allow user to download a CSV export of their locations
	public function csv_exporter(){
		$filename = empty($_POST['csv_filename']) ? false : $_POST['csv_filename'];
		
		//form not yet submitted, present user with form
		if(!$filename){		
			$this->output_form();
		} else { //form has been submitted, generate export!
			?>
				<p>Your download should begin momentarily...</p>
				<?php $this->output_form(); ?>
				<iframe style="width:100%;height:100%" src="<?php echo plugins_url( 'locations_exporter_view.php?filename='.$filename, __FILE__ ); ?>" />
			<?php
		}
	}
}