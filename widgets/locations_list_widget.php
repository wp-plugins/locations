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

class locationsListWidget extends WP_Widget
{
	function __construct(){
		$widget_ops = array('classname' => 'locationsListWidget', 'description' => 'Displays a list of all Locations.' );
		parent::__construct('locationsListWidget', 'Locations - All Locations', $widget_ops);
	}

	// PHP4 style constructor for backwards compatibility
	function locationsListWidget() {
		$this->__construct();
	}

	function form($instance){
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'show_location_image' => false, 'style' => 'small', 'category' => '') );
		$title = $instance['title'];
		$show_location_image = $instance['show_location_image'];	
		$style = $instance['style'];
		$category = $instance['category'];
		?>
			<p><label for="<?php echo $this->get_field_id('title'); ?>">Widget Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
			<p><input class="widefat" id="<?php echo $this->get_field_id('show_location_image'); ?>" name="<?php echo $this->get_field_name('show_location_image'); ?>" type="checkbox" value="1" <?php if($show_location_image){ ?>checked="CHECKED"<?php } ?>/><label for="<?php echo $this->get_field_id('show_location_image'); ?>">Show Featured Image</label></p>

			<p><label for="<?php echo $this->get_field_id('category'); ?>">Filter By Category:</label><br/>
			<?php $categories = get_terms( 'location-categories', 'orderby=title&hide_empty=0' ); ?>
			<select id="<?php echo $this->get_field_id('category'); ?>" name="<?php echo $this->get_field_name('category'); ?>">
				<option value="all">All Categories</option>
				<?php foreach($categories as $cat):?>
				<option value="<?php echo $cat->term_id; ?>" <?php if($category == $cat->term_id):?>selected="SELECTED"<?php endif; ?>><?php echo htmlentities($cat->name); ?></option>
				<?php endforeach; ?>
			</select><br/><em><a href="<?php echo admin_url('edit-tags.php?taxonomy=location-categories&post_type=location'); ?>">Manage Categories</a></em></p>
			
			<?php
	}

	function update($new_instance, $old_instance){
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['show_location_image'] = $new_instance['show_location_image'];
		$instance['style'] = $new_instance['style'];
		$instance['category'] = $new_instance['category'];
		
		return $instance;
	}

	function widget($args, $instance){
		$gp_lp = new LocationsPlugin();
		
		extract($args, EXTR_SKIP);

		echo $before_widget;
		$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
		$show_location_image = empty($instance['show_location_image']) ? false : $instance['show_location_image'];
		$style = empty($instance['style']) ? 'small' : $instance['style'];
		$caption = empty($instance['caption']) ? '' : $instance['caption'];
		$category = empty($instance['category']) ? '' : $instance['category'];
		
		if (!empty($title)){
			echo $before_title . $title . $after_title;;
		}
		
		echo $gp_lp->locations_shortcode(array('show_photos' => $show_location_image, 'caption' => $caption, 'style' => $style, 'category' => $category));


		echo $after_widget;
	} 
}
?>