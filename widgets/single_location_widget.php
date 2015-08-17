<?php
/*
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

Shout out to http://www.makeuseof.com/tag/how-to-create-wordpress-widgets/ for the help
*/

class singleLocationWidget extends WP_Widget
{
	function __construct(){
		$widget_ops = array('classname' => 'singleLocationWidget', 'description' => 'Displays a single Location.' );
		parent::__construct('singleLocationWidget', 'Locations - Single Location', $widget_ops);
	}

	// PHP4 style constructor for backwards compatibility
	function singleLocationWidget() {
		$this->__construct();
	}
	
	function form($instance){
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'locationid' => null, 'show_location_image' => false, 'caption' => '', 'style' => 'small') );
		$title = $instance['title'];
		$locationid = $instance['locationid'];
		$show_location_image = $instance['show_location_image'];
		$caption = $instance['caption'];		
		$style = $instance['style'];
		?>
			<p><label for="<?php echo $this->get_field_id('title'); ?>">Widget Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
			<?php
				$locations = get_posts('post_type=location&posts_per_page=-1&nopaging=true');
			?>
				<label for="<?php echo $this->get_field_id('locationid'); ?>">Location to Display</label>
				<select id="<?php echo $this->get_field_id('locationid'); ?>" name="<?php echo $this->get_field_name('locationid'); ?>">
					<option selected="SELECTED" disabled="DISABLED">Pick a Location</option>
					
				<?php if($locations) : foreach ( $locations as $location  ) : ?>
					<option value="<?php echo $location->ID; ?>"  <?php if($locationid == $location->ID): ?> selected="SELECTED" <?php endif; ?>><?php echo $location->post_title; ?></option>
				<?php endforeach; endif;?>
				 </select>
			<p><input class="widefat" id="<?php echo $this->get_field_id('show_location_image'); ?>" name="<?php echo $this->get_field_name('show_location_image'); ?>" type="checkbox" value="1" <?php if($show_location_image){ ?>checked="CHECKED"<?php } ?>/><label for="<?php echo $this->get_field_id('show_location_image'); ?>">Show Featured Image</label></p>
			<p><label for="<?php echo $this->get_field_id('caption'); ?>">Caption: <input class="widefat" id="<?php echo $this->get_field_id('caption'); ?>" name="<?php echo $this->get_field_name('caption'); ?>" type="text" value="<?php echo esc_attr($caption); ?>" /></label></p>
			<?php
	}

	function update($new_instance, $old_instance){
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['locationid'] = $new_instance['locationid'];
		$instance['show_location_image'] = $new_instance['show_location_image'];
		$instance['style'] = $new_instance['style'];
		$instance['caption'] = $new_instance['caption'];
		
		return $instance;
	}

	function widget($args, $instance){
		$gp_lp = new LocationsPlugin();
		
		extract($args, EXTR_SKIP);

		echo $before_widget;
		$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
		$locationid = empty($instance['locationid']) ? false : $instance['locationid'];
		$show_location_image = empty($instance['show_location_image']) ? false : $instance['show_location_image'];
		$caption = empty($instance['caption']) ? '' : $instance['caption'];
		$style = empty($instance['style']) ? 'small' : $instance['style'];

		if (!empty($title)){
			echo $before_title . $title . $after_title;;
		}
		
		//this function will output a list of locations if the ID isn't passed
		//so we check to be sure an ID has been set (ie, that this isn't the first time the widget was selected)
		//before we output anything, otherwise we end up with a dump of all locations in the widget area
		if ($locationid != false){
			echo $gp_lp->locations_shortcode(array('id' => $locationid, 'show_photos' => $show_location_image, 'caption' => $caption, 'style' => $style));
		}

		echo $after_widget;
	} 
}
?>