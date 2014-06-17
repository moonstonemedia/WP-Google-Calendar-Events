<?php

/**
 * Register Google Calendar Events custom post type
 * 
 * @since 2.0.0
 */
function gce_setup_cpt() {

	$labels = array(
		'name'               => __( 'Feeds', 'gce' ),
		'singular_name'      => __( 'Feed', 'gce' ),
		'menu_name'          => __( 'Google Calendar Events', 'gce' ),
		'name_admin_bar'     => __( 'Feed', 'gce' ),
		'add_new'            => __( 'Add New', 'gce' ),
		'add_new_item'       => __( 'Add New Feed', 'gce' ),
		'new_item'           => __( 'New Feed', 'gce' ),
		'edit_item'          => __( 'Edit Feed', 'gce' ),
		'view_item'          => __( 'View Feed', 'gce' ),
		'all_items'          => __( 'All Feeds', 'gce' ),
		'search_items'       => __( 'Search Feeds', 'gce' ),
		'not_found'          => __( 'No feeds found.', 'gce' ),
		'not_found_in_trash' => __( 'No feeds found in Trash.', 'gce' )
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'revisions' )
	);
	
	register_post_type( 'gce_feed', $args );
}
add_action( 'init', 'gce_setup_cpt' );


/**
 * Add post meta to tie in with the Google Calendar Events custom post type
 * 
 * @since 2.0.0
 */
function gce_cpt_meta() {
	add_meta_box( 'gce_feed_meta', 'Feed Settings', 'gce_display_meta', 'gce_feed', 'advanced', 'core' );
}
add_action( 'add_meta_boxes', 'gce_cpt_meta' );


function gce_display_meta() {
	include_once( GCE_DIR . '/views/admin/gce-feed-meta-display.php' );
}


function gce_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
	}

		// An array to hold all of our post meta ids so we can run them through a loop
		$post_meta_fields = array(
			'gce_feed_url',
			'gce_retrieve_from',
			'gce_retrieve_until',
			'gce_retrieve_max',
			'gce_date_format',
			'gce_time_format',
			'gce_timezone',
			'gce_cache',
			'gce_multi_day_events'
		);
		
		$post_meta_fields = apply_filters( 'gce_feed_meta', $post_meta_fields );

		if ( current_user_can( 'edit_post', $post_id ) ) {
			// Loop through our array and make sure it is posted and not empty in order to update it, otherwise we delete it
			foreach ( $post_meta_fields as $pmf ) {
				if ( isset( $_POST[$pmf] ) && ! empty( $_POST[$pmf] ) ) {
					if( $pmf == 'gce_feed_url' ) {
						update_post_meta( $post_id, $pmf, esc_url( $_POST[$pmf] ) );
					} else {
						update_post_meta( $post_id, $pmf, sanitize_text_field( stripslashes( $_POST[$pmf] ) ) );
					}
				} else {
					delete_post_meta( $post_id, $pmf );
				}
			}
		}
		
		// Should we just create the URL right here and then save the URL to the post meta?

		return $post_id;
}
add_action( 'save_post', 'gce_save_meta' );


/*
 * When the post is saved we will create the feed for it using the post meta options
 * 
 * @since 2.0.0
 */
function gce_create_feed( $post_id ) {
	// If autosaving or is not a 'feed' post type then we don't need to run
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || get_post_type( $post_id ) != 'gce_feed' ) {
			return $post_id;
	}
	
	// Setup a new Feed from the post meta
	$gce_feed_url         = get_post_meta( $post_id, 'gce_feed_url', true );
	$gce_retrieve_from    = get_post_meta( $post_id, 'gce_retrieve_from', true );
	$gce_retrieve_until   = get_post_meta( $post_id, 'gce_retrieve_until', true );
	$gce_retrieve_max     = get_post_meta( $post_id, 'gce_retrieve_max', true );
	$gce_date_format      = get_post_meta( $post_id, 'gce_date_format', true );
	$gce_time_format      = get_post_meta( $post_id, 'gce_time_format', true );
	$gce_timezone         = get_post_meta( $post_id, 'gce_timezone', true );
	$gce_cache            = get_post_meta( $post_id, 'gce_cache', true );
	$gce_multi_day_events = get_post_meta( $post_id, 'gce_multi_day_events', true );
	
	$feed_url = new GCE_Feed( $post_id, $gce_feed_url, $gce_retrieve_from, $gce_retrieve_until, $gce_retrieve_max, $gce_date_format, $gce_time_format, $gce_timezone, $gce_cache, $gce_multi_day_events );
	
	$feeds = get_option( 'gce_feeds' );
	$events = get_option( 'gce_events' );
	
	$feeds[$post_id] = $feed_url->get_display_url();
	$events[$post_id] = $feed_url->get_events();
	
	update_option( 'gce_feeds', $feeds );
	update_option( 'gce_events', $events );
}
add_action( 'save_post', 'gce_create_feed', 20 );


function content_test( $content ) {
	global $post;
	
	$new_content = $content;
	
	if( get_post_type( $post->ID ) == 'gce_feed' ) {
		
		$feed = get_option( 'gce_feeds' );
		$events = get_option( 'gce_events' );
		
		if( ! empty( $feed[$post->ID] ) ) {
			//$new_content .= '<pre>' . print_r( $events, true ) . '</pre>';
			//$new_content .= $feed[$post->ID];
			
			// Setup output for the calendar here
		}
	}
	
	return $new_content;
}
add_filter( 'the_content', 'content_test' );