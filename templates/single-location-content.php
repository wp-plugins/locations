<?php
global $location_data;
?>
<div class="locations location_single">

	<!-- Location Title -->
	<!--<h3 class="location_title"><?php echo htmlentities($location_data['title']); ?></h3>-->
	
	<!-- Featured Image -->
	<?php $post_thumbnail_src = get_the_post_thumbnail($location_data['ID'], 'thumbnail'); ?>
	<?php if (!empty($post_thumbnail_src)): ?>
	<div class="location_image_wrapper">
		<?php echo $post_thumbnail_src; ?>
	</div>
	<?php endif; ?>

	<!-- Address -->
	<div class="address adr">
		<p class="street_address street-address"><?php echo htmlentities($location_data['street_address']); ?></p>
		<?php if (!empty($location_data['street_address_line_2'])): ?>
		<p class="street_address_line_2 extended-address"><?php echo htmlentities($location_data['street_address_line_2']); ?></p>
		<?php endif;?>
		<p class="city_state_zipcode">
			<span class="city locality"><?php echo htmlentities($location_data['city']); ?></span>
			<span class="state region"><?php echo htmlentities($location_data['state']); ?></span>
			<span class="zipcode postal-code"><?php echo htmlentities($location_data['zipcode']); ?></span>
		</p>
	</div>

	<!-- Phone -->
	<?php if (!empty($location_data['phone'])): ?>
	<p class="phone"><strong>Phone:</strong> <span class="tel"><?php echo htmlentities($location_data['phone']); ?></span></p>
	<?php endif;?>

	<!-- Fax -->
	<?php if ($location_data['show_fax'] && !empty($location_data['fax'])): ?>
	<p class="fax tel"><strong><span class="type">Fax</span>:</strong> <span class="value"><?php echo htmlentities($location_data['fax']); ?></span></p>
	<?php endif;?>

	<!-- Email -->
	<?php if ($location_data['show_email'] && !empty($location_data['email'])): ?>
	<p class="email"><strong>Email:</strong> <span class="value"><?php echo htmlentities($location_data['email']); ?></span></p>
	<?php endif;?>
	
	<!-- Google Map -->
	<?php if ($location_data['show_map'] && !empty($location_data['google_maps_iframe_url'])): ?>
	<div class="locations_gmap">
		<iframe width="100%" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="<?php echo $location_data['google_maps_iframe_url']; ?>"></iframe>
	</div>
	<?php endif; ?>
</div>