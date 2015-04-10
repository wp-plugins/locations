<?php
class LocationsPlugin_Importer
{
	var $root;
	
    public function __construct($root)
    {
		$this->root = $root;
	}
	
	//convert CSV to array
	private function csv_to_array($filename='', $delimiter=','){
		if(!file_exists($filename) || !is_readable($filename))
			return FALSE;

		$header = NULL;
		$data = array();
		
		if (($handle = fopen($filename, 'r')) !== FALSE)
		{
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
			{
				if(!$header){
					$header = $row;
				} else {
					$data[] = array_combine($header, $row);
				}
			}
			fclose($handle);
		}
		return $data;
	}
	
	//process data from CSV import
	private function import_locations_from_csv($locations_file){	
		//increase execution time before beginning import, as this could take a while
		set_time_limit(0);		
		
		$locations = $this->csv_to_array($locations_file);
		
		foreach($locations as $location){		
			
			//look for a location with the same address and phone number
			//if not found, insert this one
			$args = array(
				'post_type' => 'location',
				'meta_query' => array(
					array(
						'key' => '_ikcf_street_address',
						'value' => $location['Street Address'],
					),
					array(
						'key' => '_ikcf_zipcode',
						'value' => $location['Zipcode'],
					)
				)
			 );
			$postslist = get_posts( $args );
				
			//defaults
			$the_name = '';

			if (isset ($location['Name'])) {
				$the_name = $location['Name'];
			}
			
			//if this is empty, a match wasn't found and therefore we are safe to insert
			if(empty($postslist)){				
				//insert the locations
				
				$tags = array();
			   
				$post = array(
					'post_title'    => $the_name,
					'post_category' => array(1),  // custom taxonomies too, needs to be an array
					'tags_input'    => $tags,
					'post_status'   => 'publish',
					'post_type'     => 'location'
				);
			
				$new_id = wp_insert_post($post);
			   
				//defaults, in case certain data wasn't in the CSV
				$street_address = isset($location['Street Address']) ? $location['Street Address'] : "";
				$street_address_line_two = isset($location['Street Address (line 2)']) ? $location['Street Address (line 2)'] : "";
				$city = isset($location['City']) ? $location['City'] : "";
				$state = isset($location['State']) ? $location['State'] : "";
				$zipcode = isset($location['Zipcode']) ? $location['Zipcode'] : "";
				$phone = isset($location['Phone']) ? $location['Phone'] : "";
				$email = isset($location['Email']) ? $location['Email'] : "";
				$fax = isset($location['Fax']) ? $location['Fax'] : "";
				$website_url = isset($location['Website']) ? $location['Website'] : "";
				$latitude = isset($location['Latitude']) ? $location['Latitude'] : "";
				$longitude = isset($location['Longitude']) ? $location['Longitude'] : "";
			   
				update_post_meta( $new_id, '_ikcf_street_address', $street_address );
				update_post_meta( $new_id, '_ikcf_street_address_line_2', $street_address_line_two );
				update_post_meta( $new_id, '_ikcf_city', $city );
				update_post_meta( $new_id, '_ikcf_state', $state );
				update_post_meta( $new_id, '_ikcf_zipcode', $zipcode );
				update_post_meta( $new_id, '_ikcf_phone', $phone );
				update_post_meta( $new_id, '_ikcf_email', $email );
				update_post_meta( $new_id, '_ikcf_fax', $fax );
				update_post_meta( $new_id, '_ikcf_website_url', $website_url );
				update_post_meta( $new_id, '_ikcf_latitude', $latitude );
				update_post_meta( $new_id, '_ikcf_longitude', $longitude );
			   
				$this->root->geocode_post_on_save($new_id, $post);
			   
				$inserted = true;
				echo "<p>Successfully imported '{$the_name}'!</p>";
			} else { //rejected as duplicate
				echo "<p>Could not import <em>{$the_name}</em>; rejected as Duplicate</p>";
			}
		}
	}
	
	//displays fields to allow user to upload and import a CSV of locations
	//if a file has been uploaded, this will dispatch the file to the import function
	public function csv_importer(){
		echo '<form method="POST" action="" enctype="multipart/form-data">';
		
		// Load Importer API
		require_once ABSPATH . 'wp-admin/includes/import.php';

		if ( !class_exists( 'WP_Importer' ) ) {
			$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
			if ( file_exists( $class_wp_importer ) )
				require_once $class_wp_importer;
		}		
		
		if(empty($_FILES)){		
			echo "<p>Use the below form to upload your CSV file for importing.</p>";
			echo "<p><strong>Example CSV Data:</strong></p>";
			echo "<p><code>Name,Street Address,Street Address (line 2),City,State,Zipcode,Phone,Website,Email,Fax,Latitude,Longitude</code></p>";
			echo "<p><strong>Please Note:</strong> the first line of the CSV will need to match the text in the above example, for the Import to work.  Depending on your server settings, you may need to run the import several times if your script times out.</p>";

			echo '<div class="gp_upload_file_wrapper">';
			wp_import_upload_form( add_query_arg('step', 1) );
			echo '</div>';
		} else {
			$file = wp_import_handle_upload();

			if ( isset( $file['error'] ) ) {
				echo '<p><strong>' . 'Sorry, there has been an error.' . '</strong><br />';
				echo esc_html( $file['error'] ) . '</p>';
				return false;
			} else if ( ! file_exists( $file['file'] ) ) {
				echo '<p><strong>' . 'Sorry, there has been an error.' . '</strong><br />';
				printf( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', esc_html( $file['file'] ) );
				echo '</p>';
				return false;
			}
			
			$fileid = (int) $file['id'];
			$file = get_attached_file($fileid);
			$result = $this->import_locations_from_csv($file);
			
			if ( is_wp_error( $result ) ){
				echo $result;
			} else {
				echo "<p>Locations successfully imported!</p>";
			}
		}
		echo '</form>';
	}
}