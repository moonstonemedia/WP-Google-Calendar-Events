<?php


// TODO is there a way we can just extend this from GCE_Feed so we can make all the methods private?

// This is actually our main class. All other classes should only need to be accessed by this class (I think)

class GCE_Display {
	
	private $feeds, $merged_feeds;
	
	STATIC $calendar_id = 1;
	
	public function __construct( $ids, $title_text = null, $max_events = 0, $sort_order = 'asc' ) {
		$this->id = $ids;
		//$this->feed = $feed;
		
		$this->title = $title_text;
		$this->max_events = $max_events;
		$this->sort = $sort_order;
		
		foreach( $ids as $id ) {
			$this->feeds[$id] = new GCE_Feed( $id );
		}
		
		$this->merged_feeds = array();

		//Merge the feeds together into one array of events
		foreach ( $this->feeds as $feed_id => $feed ) {
			//$errors_occurred = $feed->error();

			//if ( false === $errors_occurred )
				$this->merged_feeds = array_merge( $this->merged_feeds, $feed->events );
			//else
			//	$this->errors[$feed_id] = $errors_occurred;
		}
		
		// TODO Sort events so that they are in correct order
	}
	
	
	// TODO Remove this?
	public function get_ajax() {
		return $this->get_grid( null, null, true );
	}
	
	
	//Returns array of days with events, with sub-arrays of events for that day
	private function get_event_days() {
		$event_days = array();

		//Total number of events retrieved
		$count = count( $this->merged_feeds );

		//If maximum events to display is 0 (unlimited) set $max to 1, otherwise use maximum of events specified by user
		$max = ( 0 == $this->max_events ) ? 1 : $this->max_events;

		//Loop through entire array of events, or until maximum number of events to be displayed has been reached
		for ( $i = 0; $i < $count && $max > 0; $i++ ) {
			$event = $this->merged_feeds[$i];
			
			//echo '<pre>' . print_r( $this->merged_feeds[$i], true ) . '</pre>';
			
			//Check that event ends, or starts (or both) within the required date range. This prevents all-day events from before / after date range from showing up.
			if ( $event->end_time > $event->start_time && $event->start_time < $event->end_time ) {
			//if ( $event[$i]['end_time'] > $event[$i]['start_time'] && $event[$i]['start_time'] < $event[$i]['end_time'] ) {
				foreach ( $event->get_days() as $day ) {
					$event_days[$day][] = $event;
				}

				//If maximum events to display isn't 0 (unlimited) decrement $max counter
				if ( 0 != $this->max_events )
					$max--;
			}
		}

		return $event_days;
	}
	
	/**
	 * Return the markup for the 'Grid' display
	 * 
	 * @since 2.0.0
	 */
	public function get_grid ( $year = null, $month = null, $ajaxified = false ) {
		require_once 'php-calendar.php';

		$time_now = current_time( 'timestamp' );

		//If year and month have not been passed as paramaters, use current month and year
		if( ! isset( $year ) )
			$year = date( 'Y', $time_now );

		if( ! isset( $month ) )
			$month = date( 'm', $time_now );

		//Get timestamps for the start and end of current month
		$current_month_start = mktime( 0, 0, 0, date( 'm', $time_now ), 1, date( 'Y', $time_now ) );
		$current_month_end = mktime( 0, 0, 0, date( 'm', $time_now ) + 1, 1, date( 'Y', $time_now ) );

		//Get timestamps for the start and end of the month to be displayed in the grid
		$display_month_start = mktime( 0, 0, 0, $month, 1, $year );
		$display_month_end = mktime( 0, 0, 0, $month + 1, 1, $year );

		//It should always be possible to navigate to the current month, even if it doesn't have any events
		//So, if the display month is before the current month, set $nav_next to true, otherwise false
		//If the display month is after the current month, set $nav_prev to true, otherwise false
		$nav_next = ( $display_month_start < $current_month_start );
		$nav_prev = ( $display_month_start >= $current_month_end );

		//Get events data
		$event_days = $this->get_event_days();

		//If event_days is empty, then there are no events in the feed(s), so set ajaxified to false (Prevents AJAX calendar from allowing to endlessly click through months with no events)
		if ( empty( $event_days ) )
			$ajaxified = false;

		$today = mktime( 0, 0, 0, date( 'm', $time_now ), date( 'd', $time_now ), date( 'Y', $time_now ) );

		$i = 1;

		foreach ( $event_days as $key => $event_day ) {
			//If event day is in the month and year specified (by $month and $year)
			if ( $key >= $display_month_start && $key < $display_month_end ) {
				//Create array of CSS classes. Add gce-has-events
				$css_classes = array( 'gce-has-events' );

				//Create markup for display
				$markup = '<div class="gce-event-info">';

				//If title option has been set for display, add it
				if ( isset( $this->title ) )
					$markup .= '<div class="gce-tooltip-title">' . esc_html( $this->title ) . ' ' . date_i18n( $event_day[0]->get_feed()->get_date_format(), $key ) . '</div>';

				$markup .= '<ul>';

				foreach ( $event_day as $num_in_day => $event ) {
					$feed_id = absint( $this->id );
					$markup .= '<li class="gce-tooltip-feed-' . $feed_id . '">' . $event->get_event_markup( 'tooltip', $num_in_day, $i ) . '</li>';

					//Add CSS class for the feed from which this event comes. If there are multiple events from the same feed on the same day, the CSS class will only be added once.
					$css_classes['feed-' . $feed_id] = 'gce-feed-' . $feed_id;

					$i++;
				}

				$markup .= '</ul></div>';

				//If number of CSS classes is greater than 2 ('gce-has-events' plus one specific feed class) then there must be events from multiple feeds on this day, so add gce-multiple CSS class
				if ( count( $css_classes ) > 2 )
					$css_classes[] = 'gce-multiple';

				//If event day is today, add gce-today CSS class, otherwise add past or future class
				if ( $key == $today )
					$css_classes[] = 'gce-today gce-today-has-events';
				elseif ( $key < $today )
					$css_classes[] = 'gce-day-past';
				else
					$css_classes[] = 'gce-day-future';

				//Change array entry to array of link href, CSS classes, and markup for use in gce_generate_calendar (below)
				$event_days[$key] = array( null, implode( ' ', $css_classes ), $markup );
			} elseif ( $key < $display_month_start ) {
				//This day is before the display month, so set $nav_prev to true. Remove the day from $event_days, as it's no use for displaying this month
				$nav_prev = true;
				unset( $event_days[$key] );
			} else {
				//This day is after the display month, so set $nav_next to true. Remove the day from $event_days, as it's no use for displaying this month
				$nav_next = true;
				unset( $event_days[$key] );
			}
		}

		//Ensures that gce-today CSS class is added even if there are no events for 'today'. A bit messy :(
		if ( ! isset( $event_days[$today] ) )
			$event_days[$today] = array( null, 'gce-today gce-today-no-events', null );

		$pn = array();

		//Only add previous / next functionality if AJAX grid is enabled
		if ( $ajaxified ) {
			//If there are events to display in a previous month, add previous month link
			$prev_key = ( $nav_prev ) ? '&laquo;' : '&nbsp;';
			$prev = ( $nav_prev ) ? date( 'm-Y', mktime( 0, 0, 0, $month - 1, 1, $year ) ) : null;

			//If there are events to display in a future month, add next month link
			$next_key = ( $nav_next ) ? '&raquo;' : '&nbsp;';
			$next = ( $nav_next ) ? date( 'm-Y', mktime( 0, 0, 0, $month + 1, 1, $year ) ) : null;

			//Array of previous and next link stuff for use in gce_generate_calendar (below)
			$pn = array( $prev_key => $prev, $next_key => $next );
		}
		
		
		if( $ajaxified ) {
		//Generate the calendar markup and return it
			// target, feed_ids, max_events, title_text, type
			$markup = '<script type="text/javascript">jQuery(document).ready(function($){gce_ajaxify("gce-page-grid-' . self::$calendar_id . '", "' . self::$calendar_id . '", "' . absint( $this->max_events ) . '", "' . 'Test Title Placeholder' . '", "page");});</script>';
			return $markup . gce_generate_calendar( $year, $month, $event_days, 1, null, 0, $pn );
		} else {
			return gce_generate_calendar( $year, $month, $event_days, 1, null, 0, $pn );
		}
	}
	
	/**
	 * Return the markup for the 'List' display
	 * 
	 * @since 2.0.0
	 */
	public function get_list( $grouped = false ) {
		$time_now = current_time( 'timestamp' );
		
		// Get all the event days
		$event_days = $this->get_event_days();

		//If event_days is empty, there are no events in the feed(s), so return a message indicating this
		if( empty( $event_days) )
			return '<p>' . __( 'There are currently no events to display.', 'gce' ) . '</p>';
		
		$today = mktime( 0, 0, 0, date( 'm', $time_now ), date( 'd', $time_now ), date( 'Y', $time_now ) );

		$i = 1;

		$markup = '<ul class="gce-list">';

		foreach ( $event_days as $key => $event_day ) {
			//If this is a grouped list, add the date title and begin the nested list for this day
			if ( $grouped ) {
				$markup .=
					'<li' . ( ( $key == $today ) ? ' class="gce-today"' : '' ) . '>' .
					'<div class="gce-list-title">' . date_i18n( $event_day[0]->merged_feeds->date_format, $key ) . 'test1</div>' .
					'<ul>';
			}

			foreach ( $event_day as $num_in_day => $event ) {
				//Create the markup for this event
				$markup .=
					'<li class="gce-feed-' . $event->feed->id . '">' .
					//If this isn't a grouped list and a date title should be displayed, add the date title
					//
					// TODO Removing this for now, seems to just add a double event title to the list display
					//( ( ! $grouped && isset( $event->title ) ) ? '<div class="gce-list-title">' . esc_html( $event->title ) . 'test2</div>' : '' ) .
					
					//Add the event markup
					$event->get_event_markup( 'list', $num_in_day, $i ) .
					'</li>';

				$i++;
			}

			//If this is a grouped list, close the nested list for this day
			if ( $grouped ) {
				$markup .= '</ul></li>';
			}
		}

		$markup .= '</ul>';

		return $markup;
	}
	
	public function print_calendar( $display_type, $year = null, $month = null ) {
		
		switch( $display_type ) {
			//case 'grid':
			//	return '<div class="gce-page-grid" id="gce-page-grid-' . $this->id . '">' . $display->get_grid( $year, $month, $ajax ) . '</div>';
			case 'widget-grid':
				return '<div class="gce-widget-grid" id="gce-widget-' . self::$calendar_id . '-container">' . $this->get_grid( $year, $month, true ) . '</div>';
			case 'grid':
				return '<div class="gce-page-grid" id="gce-page-grid-' . self::$calendar_id . '">' . $this->get_grid( $year, $month, true ) . '</div>';
			case 'list':
				return '<div class="gce-page-list" id="gce-page-list-' . self::$calendar_id . '">' . $this->get_list( false ) . '</div>';
			case 'list-grouped':
				return '<div class="gce-page-list-gouped" id="gce-page-list-' . self::$calendar_id . '">' . $this->get_list( true ) . '</div>';
			case 'widget-list':
				return '<div class="gce-widget-list" id="' . self::$calendar_id . '-container">' . $this->get_list( false ) . '</div>';
			case 'widget-list-grouped':
				return '<div class="gce-widget-list" id="' . self::$calendar_id . '-container">' . $this->get_list( true ) . '</div>';
		}
		
		$calendar_id++;
	}
}
