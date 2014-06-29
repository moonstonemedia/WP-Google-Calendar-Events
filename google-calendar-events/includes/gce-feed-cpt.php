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
		'public'             => false, // This removes the 'view' and 'preview' links from what I can tell
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

/**
 * Function called to display post meta
 * 
 * @since 2.0.0
 */
function gce_display_meta() {
	include_once( GCE_DIR . '/views/admin/gce-feed-meta-display.php' );
}

/**
 * Function to save post meta for the feed CPT
 * 
 * @since 2.0.0
 */
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
		'gce_timezone_offset',
		'gce_cache',
		'gce_multi_day_events',
		'gce_display_mode'
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
				// We want max to be set to 25 by default if nothing is entered
				if( $pmf == 'gce_retrieve_max' ) {
					update_post_meta( $post_id, $pmf, 25 );
				} else {
					delete_post_meta( $post_id, $pmf );
				}
			}
		}
	}

	return $post_id;
}
add_action( 'save_post', 'gce_save_meta' );


/**
 * Add column headers to our "All Feeds" CPT page
 * 
 * @since 2.0.0
 */
function gce_add_column_headers( $defaults ) {
		
	$new_columns = array( 
		'cb'           => $defaults['cb'],
		'feed-id'      => 'Feed ID',
		'feed-sc'      => 'Feed Shortcode',
		'max-events'   => 'Max Events',
		'display-type' => 'Display Type'
	);

	return array_merge( $defaults, $new_columns );
}
add_filter( 'manage_gce_feed_posts_columns', 'gce_add_column_headers' );  


/**
 * Fill out the columns
 * 
 * @since 2.0.0
 */
function gce_column_content( $column_name, $post_ID ) {
	
	switch ( $column_name ) {

		case 'feed-id': 
			echo $post_ID;
			break;
		case 'feed-sc':
			echo '<code>[gce-feed id="' . $post_ID . '"]</code>';
			break;
		case 'max-events':
			$max = get_post_meta( $post_ID, 'gce_retrieve_max', true );
			echo $max;
			break;
		case 'display-type':
			$display = get_post_meta( $post_ID, 'gce_display_mode', true );
			
			if( $display == 'grid' ) {
				echo 'Grid';
			} else if( $display == 'list' ) {
				echo 'List';
			} else { 
				echo 'Grouped List';
			}
			break;
	}
}
add_action( 'manage_gce_feed_posts_custom_column', 'gce_column_content', 10, 2 );