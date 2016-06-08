<?php

/**
 * This function modifies Gravity Forms submissions and saves them as normal post-type posts.
 *
 * @link https://bitbucket.org/projectlargo/theme-rns/src/7548e331ff6720b2055c83425ba45b55e5ceb428/inc/taxonomies.php?at=master&fileviewer=file-view-default#taxonomies.php-303
 * @link https://www.gravityhelp.com/documentation/article/gform_after_submission/
 */
function signcasts_video_form_submit( $entry, $form ){
	// making this accessible
	$post_id = $entry['post_id'];

	// this is the url for the video
	// it gets put in the 'youtube_url' post meta by Gravity Forms
	// Your Gravity Forms field must have the "Custom Field Name" option set to the existing field youtube_url
	$url = get_post_meta( $post_id, 'youtube_url', true );

	// abort if there is no video.
	if ( empty( $url ) ) {
		return;
	}

	/**
	 * Get the featured media's data
	 * This essentially re-implements largo_fetch_video_oembed
	 * @link https://github.com/INN/Largo/blob/ddfd8d739c949aaabdab8cf40dd013ed62e670bd/inc/featured-media.php#L608
	 */
	require_once( ABSPATH . WPINC . '/class-oembed.php' );
	$oembed = _wp_oembed_get_object();
	$provider = $oembed->get_provider( $url );
	$data = $oembed->fetch( $provider, $url );
		// Data contains:
		//     thumbnail_width
		//     html ( the embed code )
		//     thumbnail_height
		//     height
		//     width
		//     title
		//     thumbnail_url
		//     author_name
		//     provider_url
		//     type
		//     version
		//     provider_name
		//     author_url
	$embed = $oembed->data2html($data, $url);
	$data = array_merge(array('embed' => $embed), (array) $data);
	$data['id'] = $post_id;
	$data['thumbnail_type'] = 'oembed'; // this is a hack; normally this would be set by the template loaded in the browser after the user had pasted the embed url: https://github.com/INN/Largo/blob/ddfd8d739c949aaabdab8cf40dd013ed62e670bd/inc/featured-media.php#L479


	/**
	 * Here we copy a bunch of stuff fron largo_featured_media_save
	 * @link https://github.com/INN/Largo/blob/ddfd8d739c949aaabdab8cf40dd013ed62e670bd/inc/featured-media.php#L538
	 */
	if ( !empty( $data['attachment'] ) ) {
		set_post_thumbnail( $data['id'], $data['attachment'] );
	} else {
		delete_post_thumbnail( $data['id'] );
	}

	// Get rid of the old youtube_url post metadata while we're saving
	// Largo keeps it around for compatibility, but setting it here doesn't actually affect the featured media.
	if ( !empty( $url ) ) {
		delete_post_meta( $data['id'], 'youtube_url' );
	}

	// Set the featured image for embed or oembed types
	if ( isset( $data['thumbnail_url'] ) && isset( $data['thumbnail_type'] ) && $data['thumbnail_type'] == 'oembed' ) {
		$thumbnail_id = largo_media_sideload_image( $data['thumbnail_url'], null );
	} else if ( isset( $data['attachment'] ) ) {
		$thumbnail_id = $data['attachment'];
	}
	
	// Skip the part of largo_featured_media_save dealing with galleries; it's not needed here

	// set the attachment
	// This is the image that is shown as the post thumbnail
	if ( isset( $thumbnail_id ) ) {
		update_post_meta( $data['id'], '_thumbnail_id', $thumbnail_id );
		$data['attachment_data'] = wp_prepare_attachment_for_js( $thumbnail_id );
	}

	// Don't save the post ID in post meta
	$save = $data;
	unset( $save['id'] );

	// Save what's sent over the wire as `featured_media` post meta
	$ret = update_post_meta( $data['id'], 'featured_media', $save );
}
add_filter( 'gform_after_submission_5', 'signcasts_video_form_submit', 10, 2 ); // '5' here should be replaced with the ID of the form you are using this on. The form ID can be found at /wp-admin/admin.php?page=gf_edit_forms
