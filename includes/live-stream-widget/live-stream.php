<?php

if (!defined('LIVE_STREAM_VERSION'))
	define('LIVE_STREAM_VERSION', '1.0.4.3');


add_action( 'init', 'live_stream_init_proc' );
add_action( 'widgets_init', 'live_stream_widgets_init_proc' );
add_action( 'wp_enqueue_scripts', 'live_stream_enqueue_scripts_proc' );
add_action( 'admin_init', 'live_stream_admin_init' );

add_action( 'wp_ajax_live_stream_update_ajax', 'live_stream_update_ajax_proc' );
add_action( 'wp_ajax_nopriv_live_stream_update_ajax', 'live_stream_update_ajax_proc' );

function live_stream_init_proc() {
    // Textdomain-Setup entfernt, zentrale Übersetzungslogik übernimmt das Hauptplugin
}

function live_stream_widgets_init_proc() {
    // Integration als Erweiterung für den Beitragsindexer
    if (class_exists('Postindexer_Extensions_Admin')) {
        global $postindexer_extensions_admin;
        if (!isset($postindexer_extensions_admin) && isset($GLOBALS['postindexeradmin']->extensions_admin)) {
            $postindexer_extensions_admin = $GLOBALS['postindexeradmin']->extensions_admin;
        }
        if (isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('live_stream_widget')) {
            register_widget( 'LiveStreamWidget' );
        }
    } else {
        register_widget( 'LiveStreamWidget' ); // Fallback, falls zentrale Logik nicht geladen
    }
}

/**
 * Setup the needed front-end CSS and JS files used by the widget
 *
 * @since 1.0.0
 * @see 
 *
 * @param none
 * @return none
 */

function live_stream_enqueue_scripts_proc() {

	if (!is_admin()) {
	
		wp_register_style( 'live-stream-style', plugins_url('/css/live-stream-style.css', __FILE__), array(), LIVE_STREAM_VERSION );
		wp_enqueue_style( 'live-stream-style' );		
	
    	wp_enqueue_script( 'jquery' );

		wp_enqueue_script('live-stream-js', plugins_url('/js/live-stream.js', __FILE__), array('jquery'), LIVE_STREAM_VERSION);		
		$live_stream_data = array( 
			'ajaxurl' => site_url() ."/wp-admin/admin-ajax.php"
		);
		
		wp_localize_script( 'live-stream-js', 'live_stream_data', $live_stream_data );
	} 
}    	

/**
 * Setup the needed admin CSS files used by the widget
 *
 * @since 1.0.0
 * @see 
 *
 * @param none
 * @return none
 */
function live_stream_admin_init() {
	wp_register_style( 'live-stream-admin-style', plugins_url('/css/live-stream-admin-style.css', __FILE__), array(), LIVE_STREAM_VERSION );
	wp_enqueue_style( 'live-stream-admin-style' );	

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script('live-stream-admin-js', plugins_url('/js/live-stream-admin.js', __FILE__), array('jquery'), LIVE_STREAM_VERSION );
}

/**
 * Wrapper class for our widget per the WordPress coding standards. 
 *
 * @since 1.0.0
 * @see 
 *
 * @param none
 * @return none
 */

class LiveStreamWidget extends WP_Widget {

	/**
 	* Widget setup.
 	*/
 	function __construct() {
 		/* Widget settings. */
 		$widget_ops = array( 
			'classname' => 'live-stream-widget', 
			'description' => __('Zeige Beiträge und Kommentare in einem Twitter-ähnlichen Aktualisierungs-Widget', 'postindexer'),
			'classname' => 'live-stream-widget-admin' );

 		/* Widget control settings. */
 		$control_ops = array( 'width' => 350, 'height' => 350, 'id_base' => 'live-stream-widget' );

 		/* Create the widget. */
 		parent::__construct( 'live-stream-widget', __('Live Stream', 'postindexer'), $widget_ops, $control_ops );
	}

	/**
	 * How to display the widget on the screen.
	 */
	 function widget( $args, $instance ) {

		//echo "args<pre>"; print_r($args); echo "</pre>";
		//echo "instance<pre>"; print_r($instance); echo "</pre>";

		$items = live_stream_get_post_items($instance, $this->number);
		if ( ($items) && (count($items)) ) {
			
			extract( $args );

			/* Our variables from the widget settings. */
		  	$title = apply_filters('widget_title', $instance['title'] );

			/* Before widget (defined by themes). */
			echo $before_widget;

			/* Display the widget title if one was input (before and after defined by themes). */
			if ( $title ) {
				echo $before_title . $title . $after_title;
			}

			krsort($items);
			//$items = array_slice($items, 1, count($items), true);
			
			if (isset($instance['height'])) {
				if ( ($instance['height'] == "other") && (isset($instance['height_other'])) ) {
					$style_height = " height:". $instance['height_other'] ."; ";
				} else {
					$style_height = " height: ". $instance['height'] ."; ";
				}
			}
			else
				$style_height = " height: 200px; ";
								
			if ((isset($instance['show_y_scroll'])) && ($instance['show_y_scroll'] == "on"))
				$style_scroll = " overflow-y:scroll;";
			else
				$style_scroll = ""; 
					
			$container_style = ' style="'. $style_height . $style_scroll .' "';	
			?>
			<ul class="live-stream-items-wrapper" <?php echo $container_style; ?>>
				<?php live_stream_build_display($instance, $items, true); ?>
			</ul>
			<?php
				$timer_interval = 3000;
				if (isset($instance['interval_seconds']))
					$timer_interval = intval($instance['interval_seconds']) * 1000; 
				
				if ($timer_interval < 1000)	 // Less than 1 second 
					$timer_interval = 3000;
					
					
				$max_items = 25;
				if (isset($instance['items_number'])) {
					$max_items = intval($instance['items_number']);
					if ($max_items < 1) 
						$max_items = 25;
				}	
			?>
			<script type='text/javascript'>
			jQuery(document).ready( function($) {

				<?php if ((isset($instance['show_live'])) && ($instance['show_live'] == "live")) { ?>
					jQuery.LiveStreamUpdates('#live-stream-widget-<?php echo $this->number; ?>', {'widget_id': <?php 
						echo $this->number; ?>, 'delay': <?php echo $timer_interval; ?>, max_items: <?php echo $max_items; ?>});
				<?php } else { ?>
					var live_stream_widget_<?php echo $this->number; ?>_function = function() {
						var update_selector = '#live-stream-widget-<?php echo $this->number; ?> .live-stream-items-wrapper';

						// We need to find the ID of the first/latest item displayed. Then pass this to AJAX so we can pull more recent items
						var last_item = jQuery(update_selector+' .live-stream-item').last();
						jQuery(last_item).hide().prependTo(update_selector).slideDown("slow");					
					}
				
					var live_stream_interval_<?php echo $this->number; ?>_ref = setInterval(live_stream_widget_<?php echo $this->number; ?>_function, 
						<?php echo $timer_interval; ?>);	

					jQuery('#live-stream-widget-<?php echo $this->number; ?> .live-stream-items-wrapper').hover(function() {
						clearInterval(live_stream_interval_<?php echo $this->number; ?>_ref);
					}, function() {
						live_stream_interval_<?php echo $this->number; ?>_ref = setInterval(live_stream_widget_<?php echo $this->number; ?>_function, 
							<?php echo $timer_interval; ?>);
					});
				<?php } ?>				
			});				
			</script>
			<?php

			/* After widget (defined by themes). */
			echo $after_widget;
		}
	}

	/**
	 * Update the widget settings.
	 */
	function update( $new_instance, $old_instance ) {
		
		//echo "new_instance<pre>"; print_r($new_instance); echo "</pre>";
		//echo "old_instance<pre>"; print_r($old_instance); echo "</pre>";
		//die();
		
		$instance = $old_instance;

		// First thing we want to do is delete the existing transients
		delete_transient( 'live_stream_widget_content_item_'. $this->number );

		if (isset($instance['content_terms'])) {
			foreach($instance['content_terms'] as $tax_slug => $tax_terms) {
				// Ignore the _select_all_ tax_slug
				if ($tax_slug == "_select_all_") continue;

				delete_transient( 'live_stream_widget_terms_'. $instance['show_users_content'] .'_'. $tax_slug );
			}
		} 

		if (isset($new_instance['title']))
			$instance['title'] 			= strip_tags($new_instance['title']);
		
		if ( (isset($new_instance['height'])) && (strlen($new_instance['height'])) )
			$instance['height'] 		= strip_tags($new_instance['height']);

		if ($instance['height'] == "other") {
			if ( (isset($new_instance['height_other'])) && (strlen($new_instance['height_other'])) ) {
				$instance['height_other'] 		= strip_tags($new_instance['height_other']);
			} else {
				unset($instance['height']);	
			}
		} else {
			unset($instance['height_other']);
		}

		if (isset($new_instance['content_types'])) {			
			$instance['content_types'] = array();
			
			if (count($new_instance['content_types'])) {
				foreach($new_instance['content_types'] as $content_type) {
					if (strlen($content_type))
						$instance['content_types'][]	= $content_type;
				}
			} 
		} else {
			$instance['content_types'] = array();
		}

		if (isset($new_instance['show_live']))
			$instance['show_live']	= esc_attr($new_instance['show_live']);
		else
			$instance['show_live']	= 'loop';
			

		if (isset($new_instance['content_terms']))
			$instance['content_terms']	= $new_instance['content_terms'];
		else
			$instance['content_terms']  = array();
			
		if ((isset($new_instance['show_avatar'])) && ($new_instance['show_avatar'] == "on"))
			$instance['show_avatar']	= $new_instance['show_avatar'];
		else
			$instance['show_avatar']	= '';

		if ((isset($new_instance['link_target'])) && ($new_instance['link_target'] == "on"))
			$instance['link_target']	= $new_instance['link_target'];
		else
			$instance['link_target']	= '';

		if (isset($new_instance['show_users_content'])) {
			$instance['show_users_content']	= esc_attr($new_instance['show_users_content']);
			if (($instance['show_users_content'] != 'local') && ($instance['show_users_content'] != 'site') && ($instance['show_users_content'] != "all"))
				$instance['show_users_content']	= 'local';
		}
		else
			$instance['show_users_content']	= 'local';

		if (isset($new_instance['items_number']))
			$instance['items_number']	= $new_instance['items_number'];

		if (isset($new_instance['content_chars']))
			$instance['content_chars']	= $new_instance['content_chars'];

		if ((isset($new_instance['show_y_scroll'])) && ($new_instance['show_y_scroll'] == "on"))
			$instance['show_y_scroll']	= $new_instance['show_y_scroll'];
		else
			$instance['show_y_scroll']	= '';

		if (isset($new_instance['interval_seconds'])) {
			$instance['interval_seconds'] = intval($new_instance['interval_seconds']);
			if (!$instance['interval_seconds'])
				$instance['interval_seconds'] = 3;
		}
		
		//echo "instance<pre>"; print_r($instance); echo "</pre>";
		//die();
	    return $instance;
	}


	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	function form( $instance ) {

		/* Default widget settings. */
		$defaults = array( 
			'title' 				=> 	'',
			'show_avatar'			=>	'on',
			'link_target'			=>	'',
			'show_users_content'	=>	'local',
			'height'				=>	'200px',
			'height_other'			=>	'',
			'items_number'			=>	'25',
			'content_chars'			=>	'0',
			'show_y_scroll'			=>	'',
			'show_live'				=>	'loop',
			'interval_seconds'		=>	3,
			'content_types'			=>	array('post', 'comment'),
			'content_terms'			=>	array()			
		);

		$instance = wp_parse_args( (array) $instance, $defaults ); 
		
		$this->show_widget_admin_title($instance);
		$this->show_widget_admin_content_source($instance);
		$this->show_widget_admin_content_types($instance);
		$this->show_widget_admin_content_terms($instance);
		$this->show_widget_admin_live_scroll($instance);
		$this->show_widget_admin_height_option($instance);
		$this->show_widget_admin_content_item_count($instance);
		$this->show_widget_admin_content_char_count($instance);
		$this->show_widget_admin_interval_seconds($instance);
		$this->show_widget_admin_avatars($instance);
		$this->show_widget_admin_link_target($instance);
		$this->show_widget_admin_scrollbars($instance);

	}
	
	function show_widget_admin_title($instance) {
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Widget Titel:', 'postindexer'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" 
				value="<?php echo $instance['title']; ?>" class="widefat" type="text" />
		</p>
		<?php
	}
	
	function show_widget_admin_avatars($instance) {
		?>
		<p><input class="checkbox" type="checkbox" <?php checked( $instance['show_avatar'], 'on' ); ?> 
				id="<?php echo $this->get_field_id( 'show_avatar' ); ?>" name="<?php echo $this->get_field_name( 'show_avatar' ); ?>" /> 
			<label for="<?php echo $this->get_field_id( 'show_avatar' ); ?>"><?php _e('Autor Avatar anzeigen?', 'postindexer'); ?></label>
		</p>
		<?php
	}

	function show_widget_admin_link_target($instance) {
		?>
		<p><input class="checkbox" type="checkbox" <?php checked( $instance['link_target'], 'on' ); ?> 
				id="<?php echo $this->get_field_id( 'link_target' ); ?>" name="<?php echo $this->get_field_name( 'link_target' ); ?>" /> 
			<label for="<?php echo $this->get_field_id( 'link_target' ); ?>"><?php _e('Links in neuem Fenster öffnen?', 'postindexer'); ?></label>
		</p>
		<?php
	}
	
	function show_widget_admin_content_item_count($instance) {
		?>
		<p><label for="<?php echo $this->get_field_id( 'items_number' ); ?>"><?php 
				_e('Maximale Anzahl anzuzeigender Elemente.', 'postindexer'); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'items_number' ); ?>" 
				id="<?php echo $this->get_field_id( 'items_number' ); ?>" 
				value="<?php echo $instance['items_number']; ?>" class="widefat" />
		</p>
		<?php
	}

	function show_widget_admin_content_char_count($instance) {
		?>
		<p><label for="<?php echo $this->get_field_id( 'content_chars' ); ?>"><?php 
				_e('Zeige Zeichen aus dem Inhalt des Elements. 0 für keine. Es wird ... angehängt', 'postindexer'); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'content_chars' ); ?>" 
				id="<?php echo $this->get_field_id( 'content_chars' ); ?>" 
				value="<?php echo $instance['content_chars']; ?>" class="widefat" />
		</p>
		<?php
	}
	
	function show_widget_admin_live_scroll($instance) {

		if ( (function_exists('is_edublogs')) && (is_edublogs()) ) {
			return;
		} else {
			?>
			<p><label for="<?php echo $this->get_field_id( 'show_live' ); ?>"><?php 
					_e('Inhalt Laden', 'postindexer'); ?></label>
			
				<select id="<?php echo $this->get_field_id( 'show_live' ); ?>" 
					name="<?php echo $this->get_field_name( 'show_live'); ?>" class="widefat" style="width:100%;">
					<option value="loop" <?php if ($instance['show_live'] == "loop") { echo ' selected="selected" '; }?>><?php 
						_e('Durchlauf - Kontinuierliches Scrollen. Kein neuer Inhalt.', 'postindexer'); ?></option>
					<option value="live" <?php if ($instance['show_live'] == "live") { echo ' selected="selected" '; }?>><?php 
						_e('Live - Neuen Inhalt über AJAX laden. Kein Scrollen.', 'postindexer'); ?></option>
				</select>
			</p>
			<?php
		}
	}

	function show_widget_admin_scrollbars($instance) {
		?>
		<p><input class="checkbox" type="checkbox" <?php checked( $instance['show_y_scroll'], 'on' ); ?> 
				id="<?php echo $this->get_field_id( 'show_y_scroll' ); ?>" name="<?php echo $this->get_field_name( 'show_y_scroll' ); ?>" /> 
			<label for="<?php echo $this->get_field_id( 'show_y_scroll' ); ?>"><?php _e('Vertikalen Scrollbalken anzeigen?', 'postindexer'); ?></label>
		</p>
		<?php
	}

	function show_widget_admin_interval_seconds($instance) {
		?>
		<p><label for="<?php echo $this->get_field_id( 'interval_seconds' ); ?>"><?php 
				_e('Anzahl der Sekunden Verzögerung für Scrollen/Abfragen', 'postindexer'); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'interval_seconds' ); ?>" 
				id="<?php echo $this->get_field_id( 'interval_seconds' ); ?>" 
				value="<?php echo $instance['interval_seconds']; ?>" class="widefat" />
		</p>
		<?php
	}
	
	function show_widget_admin_height_option($instance) {
		?>
		<p><label for="<?php echo $this->get_field_id( 'height' ); ?>"><?php 
				_e('Höhe des Widgets', 'postindexer'); ?></label>
			
			<select id="<?php echo $this->get_field_id( 'height' ); ?>" 
				name="<?php echo $this->get_field_name( 'height'); ?>" class="widefat" style="width:100%;">
				<option value="200px" <?php if ($instance['height'] == "200px") { echo ' selected="selected" '; }?>><?php 
					_e('200px - ca. 2-3 Elemente', 'postindexer'); ?></option>
				<option value="350px" <?php if ($instance['height'] == "350px") { echo ' selected="selected" '; }?>><?php 
					_e('350px - ca. 4-5 Elemente', 'postindexer'); ?></option>
				<option value="500px" <?php if ($instance['height'] == "500px") { echo ' selected="selected" '; }?>><?php 
					_e('500px - ca. 6-8 Elemente', 'postindexer'); ?></option>
				<option value="other" <?php if ($instance['height'] == "other") { echo ' selected="selected" '; }?>><?php 
						_e('sonstiges - eigene Höhe angeben', 'postindexer'); ?></option>
			</select>
			<div id="<?php echo $this->get_field_id( 'height_other' ); ?>-wrapper" 
				<?php if ($instance['height'] != "other") { echo ' style="display:none;" '; } ?> >
				<label for="<?php echo $this->get_field_id( 'height_other' ); ?>"><?php 
					_e('Widget-Höhe angeben: <em>px, em, etc. angeben z.B. 300px</em>', 'postindexer'); ?></label>
				<input id="<?php echo $this->get_field_id( 'height_other' ); ?>" name="<?php echo $this->get_field_name( 'height_other' ); ?>" 
					value="<?php echo $instance['height_other']; ?>" style="width:97%;" />
			</div>
			<script type="text/javascript">
				jQuery('select#<?php echo $this->get_field_id( 'height' ); ?>').change(function() {
					if (jQuery(this).val() == "other") {
						jQuery('#<?php echo $this->get_field_id( 'height_other' ); ?>-wrapper').show();
					} else {
						jQuery('#<?php echo $this->get_field_id( 'height_other' ); ?>-wrapper').hide();							
					}
				});
			</script>
		</p>
		<?php
	}
	
	function show_widget_admin_content_source($instance) {
		
		// We changed 'network' to 'site'. Need to conver the instance var is set.		
		if ($instance['show_users_content'] == 'network') $instance['show_users_content'] = "site";
		
		if ( (has_comment_indexer_plugin()) ) { 
			$source_content_array = array(
				'local'	=>	__('Nur von dieser Website.', 'postindexer'),
				'site'	=>	__('Von allen Websites, die von Benutzern dieser Website erstellt wurden.', 'postindexer'),
				'all'	=>	__('Von allen Benutzern aller Websites', 'postindexer')
			);
			
			if ( (function_exists('is_edublogs')) && (is_edublogs()) ) { 
				unset($source_content_array['all']);
			}
			
			?>
			<p><label><?php _e('Welchen Inhalt anzeigen', 'postindexer');?>:<br />
			<select id="<?php echo $this->get_field_id( 'show_users_content' ); ?>" 
				name="<?php echo $this->get_field_name( 'show_users_content'); ?>" class="widefat" style="width:100%;">
				<?php
					foreach($source_content_array as $_key => $_label) {
						?><option value="<?php echo $_key; ?>" <?php 
							if ($instance['show_users_content'] == $_key) { echo ' selected="selected" '; }?>><?php 
							echo $_label ?></option><?php
					}
				?>
			</select><br />
			<?php 
		} 
	}
		
	function show_widget_admin_content_types($instance) {
		
		$content_types = array('post', 'comment');
		if (($content_types) && (count($content_types))) {
			sort($content_types);
			?>
			<p>
				<label for="<?php echo $this->get_field_id( 'content_types' ); ?>"><?php 
					 _e('Inhaltstypen:', 'postindexer');  ?></label> 
				<select id="<?php echo $this->get_field_id( 'content_types' ); ?>" 
					class="widget-live-stream-content-types" size="1"
					name="<?php echo $this->get_field_name( 'content_types' ); ?>[]" class="widefat">
					<option value="" <?php if (!count($instance['content_types'])) { echo ' selected="selected" '; } ?>><?php 
						_e('Alle Typen', 'postindexer'); ?></option>
					<?php
						foreach($content_types as $content_type) {
							?><option value="<?php echo $content_type; ?>" <?php 
							if (array_search($content_type, $instance['content_types']) !== false) 
							{ echo ' selected="selected" '; } ?>><?php echo $content_type; ?></option><?php						
						}
					?>
				</select>
			</p>
			<?php
		}
	}
	
	function show_widget_admin_content_terms($instance) {
		global $wpdb;
		
		$tax_names = array('category', 'post_tag');
		
		foreach($tax_names as $tax_slug) {

			$tax_terms = get_source_tax_terms($tax_slug, $instance['show_users_content']);

			if (($tax_terms) && (count($tax_terms))) {		
						
				$taxonomy = get_taxonomy( $tax_slug );
				if ($taxonomy) {
					
					// Since we are pulling the terms from our custom table we need to add in the parent field which does not exist!
					if (isset($instance['content_terms'][$tax_slug])) {
						$selected_term_ids 	= $instance['content_terms'][$tax_slug];
					} else {
						$selected_term_ids	= array();
					}
					
					$selected_term_names = array();	

					if (count($selected_term_ids)) {
						if (get_instance_user_content($instance) == "local") {
							$selected_term_names = get_terms($tax_slug, array('fields' => 'names', 'include' => $selected_term_ids, 'hide_empty' => false));
						} else {
							foreach($tax_terms as $idx => $term) {
								if (array_search($term->term_id, $selected_term_ids) !== false) {
									$selected_term_names[]	= $term->name;
								}
							}
						}
					}
					
					// Set our default taxonomy if nothing is yet set for this taxonomy. Will set the 'Select all' checkbox on initial load.
//					if ((!isset($instance['content_terms']['_select_all_'][$tax_slug])) 
//					 && ((!isset($instance['content_terms'][$tax_slug])) || (!count($instance['content_terms'][$tax_slug])))) {
//						$instance['content_terms']['_select_all_'][$tax_slug] = "on";
//					}
					?>
					<p><input class="live-stream-terms-select-all"id="<?php echo $this->get_field_id( 'content_terms' ); ?>_select_all_<?php echo $tax_slug; ?>" 
						<?php if (isset($instance['content_terms']['_select_all_'][$tax_slug])) { echo ' checked="checked" '; } ?>
						name="<?php echo $this->get_field_name( 'content_terms' ); ?>[_select_all_][<?php echo $tax_slug; ?>]" type="checkbox"> <label
							 for="<?php echo $this->get_field_id( 'content_terms' ); ?>_select_all_<?php echo $tax_slug; ?>"><?php _e('Alle auswählen', 'postindexer'); ?></label > - <a class="live-stream-terms-show" href="#" class="" title="<?php _e('Klicken zum Anzeigen/Verstecken der Taxonomie-Begriffe', 'postindexer'); ?>"><?php 
							echo $taxonomy->labels->name; ?></a>
							
						
					<div id="<?php echo $this->get_field_id( 'content_terms' ); ?>-<?php echo $tax_slug; ?>-wrapper" 
						class="terms-wrapper" <?php if (isset($instance['content_terms']['_select_all_'][$tax_slug])) { echo ' style="display:none" '; } ?>>
						<p><span
							 class="selected-terms"><?php 
							if (count($selected_term_names)) {
								echo implode(', ', $selected_term_names); 
							} else {
								_e('Keine Begriffe ausgewählt', 'postindexer');
							}?></span></p>
						<ul class="live-stream-admin-checklist" style="display:none">
						<?php
							$walker = new Walker_Live_Stream_Checklist;
							$walker->field_name_prefix 	= $this->get_field_name( 'content_terms')."[". $tax_slug ."]";
							$walker->field_id_prefix 	= $this->get_field_id( 'content_terms')."-". $tax_slug;
													
							$checklist_args = array(
								'taxonomy'				=> 	$tax_slug,
								'descendants_and_self'	=>	false,
								'walker'				=>	$walker,								
								'selected_cats' 		=> 	$selected_term_ids,
								'popular_cats' 			=> 	array(),
								'checked_ontop' 		=> 	false								
							);
					
							echo call_user_func_array(array(&$walker, 'walk'), array($tax_terms, 0, $checklist_args));
						?>
						</ul>
					</div>
					<?php
				}
			}
		}
	} 
}

class Walker_Live_Stream_Checklist extends Walker {
	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this
	var $field_name_prefix;
	var $field_id_prefix;
	
	function start_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el(&$output, $category, $depth = 0, $args = array(), $id = 0) {
		extract($args);
		if ( empty($taxonomy) )
			$taxonomy = 'category';

		$name = $this->field_name_prefix;

		$class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
		$output .= '<li id="'. $this->field_id_prefix .'-'. $category->term_id .'" ". $class .">
			<label for="'. $this->field_id_prefix .'-'. $category->term_id . '">
				<input 
					value="' . $category->term_id . '" 
					type="checkbox" 
					name="'. $this->field_name_prefix .'[]" id="'.$this->field_id_prefix .'-' . $category->term_id . '"' . 
						checked( in_array( $category->term_id, $selected_cats ), true, false ) . 
						disabled( empty( $args['disabled'] ), false, false ) . 
				' /> ' . 
			'<span class="label-text">'. esc_html( apply_filters('the_category', $category->name )) . '</span></label>';
	}

	function end_el(&$output, $category, $depth = 0, $args = array(), $id = 0) {
		$output .= "</li>\n";
	}
}

function get_source_tax_terms($tax_slug, $show_users_content='local') {
	global $wpdb;
	
	$content_source = ''; 

	if (($show_users_content == "local"))
		$content_source = "local";
	else
		$content_source = "site";
	
	$post_indexer_plugin 	= has_post_indexer_plugin();			
	$comment_indexer_plugin = has_comment_indexer_plugin();
	
	$trans_key = 'live_stream_widget_terms_'. $content_source .'_'. $tax_slug;

	if ( ($content_source == "local") 
	 ||  (!$post_indexer_plugin) && (!$comment_indexer_plugin) ) {

		$tax_terms = get_transient( $trans_key );
		if ($tax_terms !== false)
			return $tax_terms;

		$tax_terms = (array) get_terms($tax_slug, array('get' => 'all', 'hide_empty' => 0));
		if ( is_wp_error($tax_terms) )
			return false;

		set_transient( $trans_key, $tax_terms, 300 );
			
		return $tax_terms;

	} else {
		
		$trans_key = 'live_stream_widget_terms_'. $content_source .'_'. $tax_slug;
		$tax_terms = get_site_transient( $trans_key );
		if ($tax_terms !== false)
			return $tax_terms;

		if ($post_indexer_plugin === 2) {

			$select_query_str = "SELECT * FROM ". $wpdb->base_prefix . "site_terms ";
			$where_query_str = " WHERE type IN ('". $tax_slug ."') ";
			$orderby_query_str = " ORDER BY name ";
			$query_str = $select_query_str . $where_query_str . $orderby_query_str;
			$tax_terms = $wpdb->get_results($query_str);
		} else if (($post_indexer_plugin === 3) && (class_exists('postindexermodel'))) {

			$model = new postindexerermodel();
			$select_query_str = "SELECT t.term_id term_id, t.name name, t.slug slug, tt.count count FROM ". 
				$model->network_terms ." AS t INNER JOIN ". $model->network_term_taxonomy 
				." AS tt ON t.term_id = tt.term_id";
			$where_query_str = " WHERE tt.taxonomy IN ('". $tax_slug ."') ";
			$orderby_query_str = " ORDER BY name ";
			$query_str = $select_query_str . $where_query_str . $orderby_query_str;
			$tax_terms = $wpdb->get_results($query_str);			
		}
		
		// When we read from the Post Indexer term table we don't get the parent field. This is required when we call the WP Walker class
		if ((isset($tax_terms)) && (count($tax_terms))) {
			foreach($tax_terms as $idx => $term) {
				$tax_terms[$idx]->parent = 0;
			}
		} else {
			$tax_terms = array();
		}
		set_site_transient( $trans_key, $tax_terms, 300 );

		return $tax_terms;
	}
}

function get_instance_user_content($instance) {
	
 	if ( (!has_post_indexer_plugin()) && (!has_comment_indexer_plugin()) ) {
		return 'local';
	}
	
	return $instance['show_users_content']; 
}

/**
 * This utility function checks if the Post Indexer plugin is installed. 
 *
 * @since 1.0.1
 * @see 
 *
 * @param none
 * @return true if Post Indexer plugin is installed. false is not
 */

function has_post_indexer_plugin() {
	global $post_indexer_current_version;
	
	if ((isset($post_indexer_current_version)) && (!empty($post_indexer_current_version))) {
		return 2;
	}

	else if (class_exists('postindexermodel')) {
		return 3;
	}
	return false;
}

/**
 * This utility function checks if the Comment Indexer plugin is installed. 
 *
 * @since 1.0.1
 * @see 
 *
 * @param none
 * @return true if Comment Indexer plugin is installed. false is not
 */
function has_comment_indexer_plugin() {
	if (function_exists('comment_indexer_comment_insert_update'))
		return true;

	return false;	
}

/**
 * Get the user_id of users for current blog. This will be used to filter the displayed items. 
 *
 * @since 1.0.0
 * @see 
 *
 * @param none
 * @return array of post_terms
 */

function live_stream_get_site_user_ids($instance, $widget_id) {
	global $wpdb;
	
	if ( $user_ids = get_site_transient( 'live_stream_widget_user_ids_'. $widget_id ) ) {
		return $user_ids;
	}

	$site_admin_ids = array();

	$site_admins_logins = get_super_admins();
	if ($site_admins_logins) {
		foreach($site_admins_logins as $site_admins_login) {
			$user = get_user_by('login', $site_admins_login);
			if (intval($user->ID)) {
				
				$blogs = get_blogs_of_user( $user->ID );
				
				if (($blogs) && (isset($blogs[$wpdb->blogid]))) { 
					$site_admin_ids[] = $user->ID;
				}
			}
		}
	}	
	
	$user_args = array(
		'number' 	=> 	0,
		'blog_id'	=> 	$wpdb->blogid,
		'fields'	=>	array('ID')
	);
	$wp_user_search = new WP_User_Query( $user_args );
	$users_tmp = $wp_user_search->get_results();
	if ($users_tmp) {
		$user_ids = array();
		foreach($users_tmp as $user) {
			$user_ids[] = $user->ID;
		}
	}
	//echo "site_admin_ids<pre>"; print_r($site_admin_ids); echo "</pre>";
	//echo "user_ids<pre>"; print_r($user_ids); echo "</pre>";
	
	$all_user_ids = array_unique(array_merge($user_ids, $site_admin_ids));
	//echo "all_user_ids<pre>"; print_r($all_user_ids); echo "</pre>";
	set_site_transient( 'live_stream_widget_user_ids_'. $widget_id, $all_user_ids, 300);
	return $all_user_ids;	
}

/**
 * This function queries the site post and comments tables populated by the PostIndexer and CommentIndexer plugins
 * The queries result is used to display the front-end items to the users. 
 *
 * @since 1.0.0
 * @see 
 *
 * @param $instance Widget instance
 * @return array of post_terms
 */

function live_stream_get_post_items($instance, $widget_id=0) {
	
	global $wpdb;
	
	if ( $all_items = get_transient( 'live_stream_widget_content_item_'. $widget_id ) ) {
		return $all_items;
	}
		
	$post_items 	= array();
	$comment_items 	= array();
	$all_items		= array();
	$post_ids 		= array();
	$tax_query 		= array();
	
	// Some defaults for us. 
	if (!isset($instance['show_users_content']))
		$instance['show_users_content'] = "local";

	if ((!isset($instance['content_types'])) || (!count($instance['content_types'])))
		$instance['content_types'] = array('post', 'comment');
	
	if ((!isset($instance['items_number'])) || (intval($instance['items_number']) < 2))
		$instance['items_number'] = 1;

	$content_source = 'local';
	if (($instance['show_users_content'] == '') || ($instance['show_users_content'] == "local"))
		$content_source = "local";
	else
		$content_source = "site";

	if ($instance['show_users_content'] == "site")
		$user_ids = live_stream_get_site_user_ids($instance, $widget_id);
	
	$post_indexer_plugin 	= has_post_indexer_plugin();	
	if ((!$post_indexer_plugin) && ($content_source != "local"))
		$content_source = 'local';
	
	$tax_terms_query_str = '';
	if ( (isset($instance['content_terms'])) && (count($instance['content_terms']))) {
//echo "content_terms<pre>"; print_r($instance['content_terms']); echo "</pre>";
//die();

		$tax_terms_array = array();
		
		foreach($instance['content_terms'] as $tax_slug => $tax_terms) {

			//echo "tax_slug=[". $tax_slug ."]<br />";
			//echo "tax_terms<pre>"; print_r($tax_terms); echo "</pre>";
			//continue;
			
			// Ignore our secret tax_slug. This is where we store the option to select all terms
			if ($tax_slug == "_select_all_")
				continue;
			
			// If the user selected the 'select all' checkbox on the terms set then we can ignore the taxonomy terms selected
			if (!isset($instance['content_terms']['_select_all_'][$tax_slug])) {

				if (!isset($tax_terms_array[$tax_slug]))
					$tax_terms_array[$tax_slug] = array();
			
				$tax_terms_array[$tax_slug] = $tax_terms;
			}
		}

		if (count($tax_terms_array)) {
			foreach($tax_terms_array as $tax_slug => $tax_set) {
				if (!count($tax_set)) continue;
				
				if (($content_source == "local") || (($post_indexer_plugin === 3) && (class_exists('Network_Query')))) {
					$tax_query_item = array(
						'taxonomy' 	=> $tax_slug,
						'field' 	=> 'id',
						'terms' 	=> $tax_set
					);
					$tax_query[] = $tax_query_item;
				} else {		
					foreach($tax_set as $term_id) {
						if (strlen($tax_terms_query_str)) $tax_terms_query_str .= " OR ";
						$tax_terms_query_str .= " p.post_terms like '%|". $term_id ."|%' ";		
					}
				}				
			}
		}
	}

	if ( (isset($instance['content_types'])) && (array_search('post', $instance['content_types']) !== false) ) {
	
		if ($content_source == "local") {
			
			$post_query_args = array( 
				'post_type' 		=> $instance['content_types'], 
				'post_status'		=>	'publish',
				'posts_per_page'	=>	$instance['items_number'],
				'orderby'			=>	'date',
				'order'				=>	'DESC'
			);
						
			if ((isset($tax_query)) && (count($tax_query))) {
				$tax_query['relation'] = 'OR';
				$post_query_args['tax_query'] = $tax_query;
			}
			
			// There is no provision in the WP_Query object to say "greater than date". So have to hack the WHERE
			if ((isset($instance['timekey'])) && (intval($instance['timekey']))) {
				add_filter( 'posts_where', 'live_stream_filter_posts_timekey_where' );
			}
			//echo "post_query_args<pre>"; print_r($post_query_args); echo "</pre>";
			
			$post_query = new WP_Query($post_query_args);

			if ((isset($instance['timekey'])) && (intval($instance['timekey']))) {
				remove_filter( 'posts_where', 'live_stream_filter_posts_timekey_where' );
			}

			if (($post_query->posts) && (count($post_query->posts))) {
				foreach($post_query->posts as $post_item) {
					$post_item->post_id 				= $post_item->ID;
					$post_item->blog_id					= $wpdb->blogid;
					$post_item->post_author_id 			= $post_item->post_author;
					$post_item->post_permalink 			= get_permalink($post_item->ID);
					$post_item->post_published_stamp	= strtotime($post_item->post_date_gmt);

					$post_ids[] = $post_item->post_id;			
					$all_items[$post_item->post_published_stamp] = $post_item;
				}
			}
		} else {
			
			if ($post_indexer_plugin === 2) {
				$select_query_str 	= "SELECT 
					p.site_post_id, 
					p.blog_id as blog_id, 
					p.post_id as post_id, 
					p.post_author as post_author_id, 
					p.post_type as post_type, 
					p.post_title as post_title, 
					p.post_content as post_content,
					p.post_permalink as post_permalink, 
					p.post_published_stamp as post_published_stamp 
					FROM ". $wpdb->base_prefix . "site_posts p";		

				$where_query_str 	= "WHERE 1";

				if ((isset($instance['timekey'])) && (intval($instance['timekey']))) {
					$where_query_str .= " AND p.post_published_stamp > ". $instance['timekey'];		
				}

				if (($instance['show_users_content'] == "site") && (isset($user_ids)) && (count($user_ids))) {
					$where_query_str .= " AND p.post_author IN (". implode(',', $user_ids) .") ";			
				}

				if (strlen($tax_terms_query_str)) {
					$where_query_str .= " AND (". $tax_terms_query_str .") ";
				}

				if ($instance['show_users_content'] == "local") {
					$where_query_str .= " AND p.blog_id=". $wpdb->blogid ." ";
				}

				$content_types_str = '';
				if ((isset($instance['content_types'])) && (count($instance['content_types']))) {
					foreach($instance['content_types'] as $type) {
						if (strlen($content_types_str)) 
							$content_types_str .= ",";

						$content_types_str .= "'". $type ."'";
					}

					if (strlen($content_types_str)) {
						$where_query_str .= " AND p.post_type IN (". $content_types_str .") ";
					}
				}

				$orderby_query_str 	= " ORDER BY p.post_published_stamp DESC";
				$limit_query_str = " LIMIT ". $instance['items_number'];

				$query_str = $select_query_str ." ". $where_query_str ." ". $orderby_query_str ." ". $limit_query_str;
				$post_items = $wpdb->get_results($query_str);
				if ((isset($post_items)) && (count($post_items))) {
					foreach($post_items as $item) {
						$post_ids[] = $item->post_id;			
						$all_items[$item->post_published_stamp] = $item;
					}
				}
				
			} else if (($post_indexer_plugin === 3) && (class_exists('Network_Query'))) {

				$post_query_args = array( 
					'post_type' 		=> $instance['content_types'], 
					'post_status'		=>	'publish',
					'posts_per_page'	=>	$instance['items_number'],
					'orderby'			=>	'date',
					'order'				=>	'DESC'
				);

				if ($instance['show_users_content'] == "local") {
					$post_query_args['blog_id'] = $wpdb->blogid;
				}

				if ((isset($tax_query)) && (count($tax_query))) {
					$tax_query['relation'] = 'OR';
					$post_query_args['tax_query'] = $tax_query;
				}

				// There is n provision in the WP_Query object to say "greater than date". So have to hack the WHERE
				if ((isset($instance['timekey'])) && (intval($instance['timekey']))) {
					add_filter( 'posts_where', 'live_stream_filter_posts_timekey_where' );
				}

				// We need to turn off error_reporting to prevent Notices showing in case user has full reporting turned on. 
				$current_error_reporting = error_reporting();
				error_reporting(0);
				$post_query = new Network_Query($post_query_args);
				error_reporting($current_error_reporting);
				
				if ((isset($instance['timekey'])) && (intval($instance['timekey']))) {
					remove_filter( 'posts_where', 'live_stream_filter_posts_timekey_where' );
				}
				if (($post_query->posts) && (count($post_query->posts))) {
					foreach($post_query->posts as $post_item) {
						$post_item->post_id 				= $post_item->ID;
						$post_item->blog_id					= $post_item->BLOG_ID;						
						$post_item->post_author_id 			= $post_item->post_author;
						$post_item->post_permalink 			= network_get_permalink( $post_item->BLOG_ID, $post_item->ID);
						$post_item->post_published_stamp	= strtotime($post_item->post_date_gmt);
						if (strlen($post_item->post_excerpt))
							$post->post_content = $post_item->post_excerpt;

						$post_ids[] = $post_item->post_id;			
						$all_items[$post_item->post_published_stamp] = $post_item;
					}
				}
			}
		}
	}

	/* Get the comments */
	if ( (isset($instance['content_types'])) && (array_search('comment', $instance['content_types']) !== false) ) {
		
		if ($content_source == "local") {
			$select_query_str = "SELECT 
				c.comment_post_ID as post_id, 
				c.user_id as post_author_id, 
				c.comment_content as comment_content,				
				c.comment_author as post_author_name, 
				c.comment_author_email as post_author_email, 
				c.comment_date_gmt as post_published_stamp, 
				c.comment_ID as comment_id 
				
			 FROM ". $wpdb->prefix ."comments c";			
			$where_query_str = ' WHERE 1 ';
			$where_query_str .= " AND c.comment_approved = 1 ";
			
			if ((isset($instance['timekey'])) && (intval($instance['timekey']))) {
				$where_query_str .= " AND post_published_stamp > ". $instance['timekey'];		
			}
			
			$orderby_query_str = ' ORDER BY c.comment_date_gmt DESC';
			$limit_query_str = " LIMIT ". $instance['items_number'];
			
			$query_str = $select_query_str ." ". $where_query_str ." ". $orderby_query_str ." ". $limit_query_str;
			//echo "query_str=[". $query_str ."]<br />";			
			$comment_items = $wpdb->get_results($query_str);

			if ($comment_items) {
				foreach($comment_items as $item) {

					$item->post_type 			= 	"comment";
					$item->blog_id				=	$wpdb->blogid; 
					$item->post_title 			=	get_the_title($item->post_id);
					$item->post_permalink		=	get_permalink($item->post_id);
					$item->post_published_stamp	= 	strtotime($item->post_published_stamp);
					
					$all_items[$item->post_published_stamp]		= $item;
				}
			}
		} else {
			if ($post_indexer_plugin === 2) {
			
				$select_query_str = "SELECT 
					c.blog_id as blog_id, 
					c.comment_post_id as post_id, 
					c.comment_author_user_id as post_author_id, 
					c.comment_author as post_author_name, 
					c.comment_author_email as post_author_email, 
					p.post_title as post_title, 
					c.comment_content as comment_content,
					c.comment_post_permalink as post_permalink, 
					c.comment_date_stamp as post_published_stamp, 
					c.comment_id as comment_id 
					FROM ". $wpdb->base_prefix . "site_comments c INNER JOIN ". $wpdb->base_prefix . 
						"site_posts p ON c.comment_post_id=p.post_id AND c.blog_id=p.blog_id "; 
					
			} else if (($post_indexer_plugin === 3) && (class_exists('postindexermodel'))) {
				
				$model = new postindexermodel();
				
				$select_query_str = "SELECT 
					c.blog_id as blog_id, 
					c.comment_post_id as post_id, 
					c.comment_author_user_id as post_author_id, 
					c.comment_author as post_author_name, 
					c.comment_author_email as post_author_email, 
					p.post_title as post_title, 
					c.comment_content as comment_content,					
					c.comment_post_permalink as post_permalink, 
					c.comment_date_stamp as post_published_stamp, 
					c.comment_id as comment_id 
					FROM ". $wpdb->base_prefix . "site_comments c INNER JOIN ". 
					$model->network_posts . " p ON c.comment_post_id=p.ID AND c.blog_id=p.BLOG_ID ";
			}
			if ((isset($select_query_str)) && (!empty($select_query_str))) {
				
				$where_query_str = 'WHERE 1 ';
				$where_query_str .= " AND c.comment_approved = 1 ";

				if ((isset($instance['timekey'])) && (intval($instance['timekey']))) {
					$where_query_str .= " AND p.post_published_stamp > ". $instance['timekey'];		
				}

				if (($instance['show_users_content'] == "site") && (isset($user_ids)) && (count($user_ids))) 
					$where_query_str .= " AND c.comment_author_user_id IN (". implode(',', $user_ids) .") ";

//				if ($instance['show_users_content'] == "local")
//					$where_query_str .= " AND c.blog_id=". $wpdb->blogid ." ";
//				else
//					$where_query_str .= " AND c.blog_id = p.blog_id ";
				
				if ( (isset($terms_query_str)) && (strlen($terms_query_str)) ) {
					$where_query_str .= " AND (". $terms_query_str .") ";
				}

				$orderby_query_str = ' ORDER BY c.comment_date_stamp DESC';
				$limit_query_str = " LIMIT ". $instance['items_number'];

				$query_str = $select_query_str ." ". $where_query_str ." ". $orderby_query_str ." ". $limit_query_str;
				$comment_items = $wpdb->get_results($query_str);
				if ($comment_items) {
					foreach($comment_items as $item) {

						$item->post_type 							= "comment";
						$all_items[$item->post_published_stamp]		= $item;
					}
				}

			}
		}
	}
	
	if (($all_items) && (count($all_items))) {
		ksort($all_items);
		krsort($all_items);
	}
	
	if (count($all_items) > $instance['items_number']) {
		$all_items = array_slice($all_items, 0, $instance['items_number'], true);
	}

	if ( (isset($instance['show_live'])) && ($instance['show_live'] == "live") ) {	
		// If we are showing 'live' content (AJAX) polling we set the transient timeout low. 
		set_transient( 'live_stream_widget_content_item_'. $widget_id, $all_items, intval($instance['interval_seconds'])+1 );
	} else {
		// But for looping we set this longer. 
		set_transient( 'live_stream_widget_content_item_'. $widget_id, $all_items, 30 );
	}
	
	return $all_items;	
}

/**
 * This filter is used when the source is 'local'. Since that option uses the WP_Query to access Posts we needed
 * a way to tell WP_Query to only pull posts with GMT post_data newer than a given timestamp ($_POST['timekey'])
 *
 * @since 1.0.1
 * @see 
 *
 * @param string $where from WP_Query
 * @return string $$where modified.
 */
function live_stream_filter_posts_timekey_where( $where = '' ) {
	if (isset($_POST['timekey'])) {
		$timekey = intval($_POST['timekey']);

		$where .= " AND post_date_gmt > '" . date('Y-m-d H:i:s', $timekey) . "' ";
	}
	return $where;
}


/**
 * This function is given an array of items in which will be build the output list items for display
 *
 * @since 1.0.0
 * @see 
 *
 * @param object $instance This is the Widget instance. 
 * @param array $items The items result from the live_stream_get_post_items(); return;
 * @param bool $echo true to echo the output or false to return the output
 * @return string $items_output returned IF $echo is false.
 */

function live_stream_build_display($instance, $items, $echo = true) {
	
	if ( (!$items) || (!is_array($items)) || (!count($items)) ) return;
//	echo "instance<pre>"; print_r($instance); echo "</pre>";
//	echo "items<pre>"; print_r($items); echo "</pre>";
//	die();
	
	krsort($items);
	$items_output = '';
	
	$blogs = array();
	
	//echo "instance<pre>"; print_r($instance); echo "</pre>";	
	foreach($items as $key => $item) {

		if (isset($_POST['timekey'])) {
			if (intval( intval( $key ) <= $_POST['timekey'] ) )
				continue;
		}

		if (is_multisite()) {
			if ((isset($item->blog_id)) && (intval($item->blog_id))) {
				$blog_id = $item->blog_id;
			
				if (isset($blogs[intval($item->blog_id)])) {
					$blog = $blogs[intval($item->blog_id)];
				} else {
					$blog = get_blog_details($item->blog_id);
					if ($blog) {
						$blogs[intval($item->blog_id)] = $blog;
					} else {
						unset($blog);
					}
				} 
			}
		} else {
			$blog = new stdClass;
			$blog->blogname		= get_option( 'blogname' );
			$blog->siteurl		= get_option( 'siteurl' );													
			//echo "blog<pre>"; print_r($blog); echo "</pre>";
		}
		
		$wrapper_class = "live-stream-item-". $item->post_type;
		if ((isset($instance['show_avatar'])) && ($instance['show_avatar'] == "on")) {
			$wrapper_class = " live-stream-text-has-avatar";
		} 

		if ((isset($instance['link_target'])) && ($instance['link_target'] == "on")) {
			$link_target = ' target="_blank" ';
		} else {
			$link_target = '';
		}

		$item_output = '<li id="live-stream-item-'. $key .'" class="live-stream-item '. $wrapper_class .'">';
		
		$user_data = array();

		if (intval($item->post_author_id) ) {
			$userdata = get_userdata( intval($item->post_author_id) );
			if ($userdata) {
				$user_data['ID'] 			= $userdata->ID;
				$user_data['user_email'] 	= $userdata->user_email;
				$user_data['display_name'] 	= $userdata->display_name;			
			}			
		} 

		if (!isset($user_data['ID']))
			$user_data['ID'] 			= 0;

		if (!isset($user_data['user_email'])) {
			if ((isset($item->post_author_email)) && (strlen($item->post_author_email))) {
				$user_data['user_email'] 	= $item->post_author_email;
			} else {
				$user_data['user_email']	= '';
			}			
		}

		if (!isset($user_data['display_name'])) {
			
			if ((isset($item->post_author_name)) && (strlen($item->post_author_name))) {
				$user_data['display_name'] 	= $item->post_author_name;
			} else {
				$user_data['display_name']	= '';
			}
		}
		
		/* Build an anchor wrapper for the author which is used in multiple places */
		if ((isset($blog->siteurl)) && (intval($item->post_author_id) )) {
			$author_anchor_begin 	= '<a '. $link_target .' class="live-stream-item-author" href="'. $blog->siteurl .'?author='
				. $item->post_author_id .'">';
			$author_anchor_end 		= '</a>';
			
		} else {
			if ($item->post_type == "comment") {
				$author_anchor_begin 	= '<a '. $link_target .' class="live-stream-item-author" href="'. $item->post_permalink .'#comment-'. $item->comment_id .'">';
				$author_anchor_end 		= '</a>';
			} else {			
				$author_anchor_begin 	= '';
				$author_anchor_end 		= '';
			}
		}
		
		/* User Avatar */
		if ((isset($instance['show_avatar'])) && ($instance['show_avatar'] == "on")) {
			if ((isset($user_data['user_email'])) && (strlen($user_data['user_email']))) {
				$avatar = get_avatar($user_data['user_email'], 30, null, $user_data['display_name']);
			} else {
				$avatar = get_avatar("dummy@dummy.com", 30, null, $user_data['display_name']);				
			}
			$avatar = get_avatar($user_data['user_email'], 30, null, $user_data['display_name']);
			if (!empty($avatar)) {
				$item_output .= '<div class="live-stream-avatar">';
				$item_output .= $author_anchor_begin . $avatar . $author_anchor_end;
				$item_output .= '</div>';	
			}
		}
		
		/* Begin text container wrapper */
		$item_output .= '<div class="live-stream-text">';


			/* Show the User Name */						
			if (isset($user_data['display_name'])) 
				$item_output .= $author_anchor_begin . $user_data['display_name'] . $author_anchor_end ." ";

			$item_content = '';
			if ((isset($instance['content_chars'])) && (intval($instance['content_chars']))) {
				if ( ($item->post_type == "comment") && (isset($item->comment_content)) && (strlen($item->comment_content)) ) {
					$item_content = strip_tags($item->comment_content);
				} else if ((isset($item->post_content)) && (strlen($item->post_content))) {
					$item_content = strip_tags($item->post_content);					
				}
				
				if (strlen($item_content)) {
					$item_content = substr($item_content, 0, $instance['content_chars']);
					$item_content = '<span class="live-stream-item-content">'. $item_content .'...</span> ';
				}
			}

			if ($item->post_type == "comment") {
				if (strlen($item_output)) 
					$item_output .= " ";
				$item_output .= $item_content;

				$item_output .= ' <span class="live-stream-item-action">'. __("kommentierte auf", 'postindexer') .'</span> ';
				
				/* Show the Post Title */
				if (isset($blogs[$item->blog_id])) {
					if (strlen($item_output)) $item_output .= " "; ;
					$post_anchor_begin 	= '<a '. $link_target .' class="live-stream-item-title" href="'. $item->post_permalink .'#comment-'. $item->comment_id .'">';
					$post_anchor_end 	= '</a>';

				} else {
					$post_anchor_begin 	= '';
					$post_anchor_end 	= '';
				}
				
				$item_output .= $post_anchor_begin . $item->post_title . $post_anchor_end ." ";
				
			} else {
				if (strlen($item_output)) 
					$item_output .= " "; 
				$item_output .= $item_content;
				
				$item_output .= ' <span class="live-stream-item-action">'. __('veröffentlicht', 'postindexer') .'</span> ';

				/* Show the Post Title */
				if (strlen($item_output)) $item_output .= " ";
				$item_output .= '<a '. $link_target .' class="live-stream-item-title" href="'. $item->post_permalink .'">'. $item->post_title ."</a> ";
				
				//if (strlen($item_content)) $item_output .= " ". $item_content;
			}

	
			/* Show the Blog domain */
			if ((isset($instance['show_users_content'])) && ($instance['show_users_content'] != "local")) {
				if (isset($blog->siteurl)) {
					$site_anchor_begin = '<a '. $link_target .' class="live-stream-item-blog" href="'. $blog->siteurl .'">';
					$site_anchor_end	= '</a>';
					$item_output .= __("via", 'postindexer') ." ". $site_anchor_begin . $blog->blogname . $site_anchor_end ." ";
				}
			}
		
		
			/* Show the Post/Comment human time */
			$item_output .= '<div class="live-stream-text-footer">';
			$item_output .= '<span class="live-stream-text-footer-date">'. sprintf( __( '%s ago ', 'postindexer' ), 
				human_time_diff( $item->post_published_stamp ) ) .'</span>';
			$item_output .= ' &middot; ';
			
			if ($item->post_type == "comment") {
				if ($instance['show_users_content'] == "local") {
					$comment_count = get_comments_number( $item->post_id );
					$comment_label = __('Kommentar', 'postindexer') ." (". $comment_count .")";

					$item_output .= '<a '. $link_target .' class="live-stream-text-footer-date" href="'. 
						$item->post_permalink .'#comments">'. $comment_label .'</a>';
				} else {
					$comment_label = __('Kommentare', 'postindexer');					
					$item_output .= '<a '. $link_target .' class="live-stream-text-footer-date" href="'. 
						$item->post_permalink .'#comments">'. $comment_label .'</a>';
				}
			} else {
				if (($instance['show_users_content'] == "local") && (array_search("post", $instance['content_types']) !== false)) {
					$comment_count = get_comments_number( $item->post_id );
					$comment_label = __('Kommentar', 'postindexer') ." (". $comment_count .")";
					$item_output .= '<a '. $link_target .' class="live-stream-text-footer-date" href="'. $item->post_permalink .'#comments">'. $comment_label .'</a>';
					
				} else {
					$item_output .= '<a '. $link_target.' class="live-stream-text-footer-date" href="'. $item->post_permalink .'">'. __('besuchen', 'postindexer') .'</a>';					
				}
			}
			$item_output .= '</div>';


			
		/* Closing the item text wrapper */
		$item_output .= '</div>';

		$item_output .= '</li>';
			
		$items_output .= $item_output;
	}
	
	if (strlen($items_output)) {
		if ($echo == true)
			echo $items_output;
		else
			return $items_output;
	}		
}

/**
 * This function handles the AJAX update requests from the front-end widget. The instance ID ($_POST['widget_id']) is passed 
 * via $_POST so this means the function can support multiple widgets if needed. 
 *
 * @since 1.0.0
 * @see 
 *
 * @param none
 * @return none
 */

function live_stream_update_ajax_proc() {
	
	if (isset($_POST['widget_id']))
		$widget_id = intval($_POST['widget_id']);

	if (isset($_POST['timekey']))
		$timekey = intval($_POST['timekey']);
	
	if ((isset($widget_id)) && (isset($timekey))) {
		$live_stream_widgets = get_option('widget_live-stream-widget');
		if (($live_stream_widgets) && (isset($live_stream_widgets[$widget_id]))) {
			$instance = $live_stream_widgets[$widget_id];
			
			$instance['timekey'] 		= $timekey;
			$instance['doing_ajax'] 	= true;
			//$instance['items_number']	= 1;
			
			$items = live_stream_get_post_items($instance);
			if (($items) && (count($items))) {
				//ksort($items);					

				// We only want to update a single row per a request. Don't want to overwhelm the user. 
				$items = array_slice($items, 0, 1, true);
				
				live_stream_build_display($instance, $items, true);
			}
		}
	}
	
	die();
}

//function is_edublogs() { return true;}
