<?php

/* Textdomain für Übersetzungen: postindexer (wird zentral vom Hauptplugin geladen) */

function recent_global_comment_widget_init_proc() {
	
	/* Setup the tetdomain for i18n language handling see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain */
    //load_plugin_textdomain( 'recent-global-comments-widget', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'recent_global_comment_widget_init_proc' );


//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

// Default setting to control user or widget. 'yes' is main blog only. 'no' widget will show for all blogs
$recent_global_comments_widget_main_blog_only = 'yes'; //Either 'yes' or 'no'

// Can also set this in the wp-config.php or theme using 
// define('RECENT_GLOBAL_COMMENTS_WIDGET_MAIN_BLOG_ONLY', 'yes'); // or 'no'
if (defined('RECENT_GLOBAL_COMMENTS_WIDGET_MAIN_BLOG_ONLY'))
	$recent_global_comments_widget_main_blog_only = strtolower(RECENT_GLOBAL_COMMENTS_WIDGET_MAIN_BLOG_ONLY);
		
if ($recent_global_comments_widget_main_blog_only != 'yes')	
	$recent_global_comments_widget_main_blog_only = 'no';

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

if ( !function_exists( 'widget_recent_global_comments_init' ) ) {
function widget_recent_global_comments_init() {
	global $wpdb, $recent_global_comments_widget_main_blog_only;

	// This saves options and prints the widget's config form.
	if ( !function_exists( 'widget_recent_global_comments_control' ) ) {
	function widget_recent_global_comments_control() {
		global $wpdb;
		$options = $newoptions = get_option('widget_recent_global_comments');
		if ( !is_array($options) ) $options = $newoptions = array();
		$defaults = array(
			'recent-global-comments-title' => '',
			'recent-global-comments-number' => 10,
			'recent-global-comments-content-characters' => 50,
			'recent-global-comments-avatars' => 'show',
			'recent-global-comments-avatar-size' => 32
		);
		$options = array_merge($defaults, $options);
		$newoptions = array_merge($defaults, $newoptions);
		if ( isset( $_POST['recent-global-comments-submit'] ) ) {
			$newoptions['recent-global-comments-title'] 				= sanitize_text_field($_POST['recent-global-comments-title']);
			$newoptions['recent-global-comments-number'] 				= intval($_POST['recent-global-comments-number']);
			$newoptions['recent-global-comments-content-characters'] 	= intval($_POST['recent-global-comments-content-characters']);
			$newoptions['recent-global-comments-avatars'] 				= sanitize_text_field($_POST['recent-global-comments-avatars']);
			$newoptions['recent-global-comments-avatar-size'] 			= intval($_POST['recent-global-comments-avatar-size']);
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_recent_global_comments', $options);
		}
        ?>
        <div style="text-align:left">
            <label for="recent-global-comments-title" style="line-height:35px;display:block;"><?php _e('Titel', 'postindexer'); ?>:<br />
            <input class="widefat" id="recent-global-comments-title" name="recent-global-comments-title" value="<?php echo sanitize_text_field($options['recent-global-comments-title']); ?>" type="text" style="width:95%;">
            </select>
            </label>
            <label for="recent-global-comments-number" style="line-height:35px;display:block;"><?php _e('Anzahl', 'postindexer'); ?>:<br />
            <select name="recent-global-comments-number" id="recent-global-comments-number" style="width:95%;">
            <?php
                if ( empty($options['recent-global-comments-number']) ) {
                    $options['recent-global-comments-number'] = 10;
                }
                $counter = 0;
                for ( $counter = 1; $counter <= 25; $counter += 1) {
                    ?>
                    <option value="<?php echo $counter; ?>" <?php if (intval($options['recent-global-comments-number']) == $counter){ echo 'selected="selected"'; } ?> ><?php echo $counter; ?></option>
                    <?php
                }
            ?>
            </select>
            </label>
            <label for="recent-global-comments-content-characters" style="line-height:35px;display:block;"><?php _e('Zeichen im Kommentar', 'postindexer'); ?>:<br />
            <select name="recent-global-comments-content-characters" id="recent-global-comments-content-characters" style="width:95%;">
            <?php
                if ( empty($options['recent-global-comments-content-characters']) ) {
                    $options['recent-global-comments-content-characters'] = 50;
                }
                $counter = 0;
                for ( $counter = 1; $counter <= 500; $counter += 1) {
                    ?>
                    <option value="<?php echo $counter; ?>" <?php if (intval($options['recent-global-comments-content-characters']) == $counter){ echo 'selected="selected"'; } ?> ><?php echo $counter; ?></option>
                    <?php
                }
            ?>
            </select>
            </label>
            <label for="recent-global-comments-avatars" style="line-height:35px;display:block;"><?php _e('Avatare', 'postindexer'); ?>:<br />
            <select name="recent-global-comments-avatars" id="recent-global-comments-avatars" style="width:95%;">
            <option value="show" <?php if ( isset( $options['recent-global-comments-avatars'] ) && $options['recent-global-comments-avatars'] == 'show' ){ echo 'selected="selected"'; } ?> ><?php _e('Anzeigen', 'postindexer'); ?></option>
            <option value="hide" <?php if ( isset( $options['recent-global-comments-avatars'] ) && $options['recent-global-comments-avatars'] == 'hide' ){ echo 'selected="selected"'; } ?> ><?php _e('Ausblenden', 'postindexer'); ?></option>
            </select>
            </label>
            <label for="recent-global-comments-avatar-size" style="line-height:35px;display:block;"><?php _e('Avatar-Größe', 'postindexer'); ?>:<br />
            <select name="recent-global-comments-avatar-size" id="recent-global-comments-avatar-size" style="width:95%;">
            <option value="16" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '16'){ echo 'selected="selected"'; } ?> ><?php _e('16px', 'postindexer'); ?></option>
            <option value="32" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '32'){ echo 'selected="selected"'; } ?> ><?php _e('32px', 'postindexer'); ?></option>
            <option value="48" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '48'){ echo 'selected="selected"'; } ?> ><?php _e('48px', 'postindexer'); ?></option>
            <option value="96" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '96'){ echo 'selected="selected"'; } ?> ><?php _e('96px', 'postindexer'); ?></option>
            <option value="128" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '128'){ echo 'selected="selected"'; } ?> ><?php _e('128px', 'postindexer'); ?></option>
            </select>
            </label>
            <input type="hidden" name="recent-global-comments-submit" id="recent-global-comments-submit" value="1" />
        </div>
        <?php
	}
	}
    // This prints the widget
	if ( !function_exists( 'widget_recent_global_comments' ) ) {
	function widget_recent_global_comments($args) {
		global $wpdb, $current_site;
		extract($args);
		$defaults = array('count' => 10, 'username' => 'wordpress');
		$options = (array) get_option('widget_recent_global_comments');

		foreach ( $defaults as $key => $value ) {
			if ( !isset($options[$key] ) )
				$options[$key] = $defaults[$key];
        }
		?>

		<?php echo $before_widget; ?>
			<?php echo $before_title . $options['recent-global-comments-title'] . $after_title; ?>
            <br />
            <?php
				//=================================================//
				$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "site_comments WHERE blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT %d", $options['recent-global-comments-number']);
				$comments = $wpdb->get_results( $query, ARRAY_A );
				if (count($comments) > 0){
					echo '<ul>';
					foreach ($comments as $comment){
						echo '<li>';
						if ( $options['recent-global-comments-avatars'] == 'show' ) {
							if ( !empty( $comment['comment_author_user_id'] ) ) {
								$id_or_email = $comment['comment_author_user_id'];
							} else {
								$id_or_email = $comment['comment_author_email'];
							}
							echo '<a href="' . $comment['comment_post_permalink'] . '">' . get_avatar( $id_or_email, $options['recent-global-comments-avatar-size'], '' ) . '</a>';
							echo ' ';
						}
						echo substr( strip_tags( $comment['comment_content'] ), 0, $options['recent-global-comments-content-characters'] );
						echo ' (<a href="' . $comment['comment_post_permalink'] . '#comment-' . $comment['comment_id'] . '">' . __('Mehr', 'postindexer') . '</a>)';
						echo '</li>';
					}
					echo '</ul>';
				}
				//=================================================//
			?>
		<?php echo $after_widget; ?>
        <?php
	}
	}
	// Tell Dynamic Sidebar about our new widget and its control
	if ( $recent_global_comments_widget_main_blog_only == 'yes' ) {
		//if ( $wpdb->blogid == 1 ) {
		if ( is_main_site() ) {
			wp_register_sidebar_widget( 'recent_global_comments_widget', __( 'Globale Kommentare', 'postindexer' ), 'widget_recent_global_comments' );
			wp_register_widget_control( 'recent_global_comments_widget', __( 'Globale Kommentare', 'postindexer' ), 'widget_recent_global_comments_control' );
		}
	} else {
        wp_register_sidebar_widget( 'recent_global_comments_widget', __( 'Globale Kommentare', 'postindexer' ), 'widget_recent_global_comments' );
		wp_register_widget_control( 'recent_global_comments_widget', __( 'Globale Kommentare', 'postindexer' ), 'widget_recent_global_comments_control' );
	}
}
}

add_action('widgets_init', 'widget_recent_global_comments_init');

// Shortcode-Registrierung nur, wenn die Erweiterung aktiv ist
add_action('plugins_loaded', function() {
    if ( !class_exists('Postindexer_Extensions_Admin') ) return;
    global $postindexer_extensions_admin;
    if ( !isset($postindexer_extensions_admin) ) {
        if ( isset($GLOBALS['postindexeradmin']) && isset($GLOBALS['postindexeradmin']->extensions_admin) ) {
            $postindexer_extensions_admin = $GLOBALS['postindexeradmin']->extensions_admin;
        }
    }
    // Korrekte Prüfung auf den Extension-Slug
    if ( isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('recent_global_comments_widget') ) {
        add_shortcode('network_comments', function($atts = []) {
            $defaults = array(
                'title' => '',
                'number' => 10,
                'content_characters' => 50,
                'avatars' => 'show',
                'avatar_size' => 32,
                'global_before' => '',
                'global_after' => '',
                'before' => '',
                'after' => '',
                'link' => ''
            );
            $settings = get_site_option('recent_global_comments_settings', []);
            $settings = is_array($settings) ? array_merge($defaults, $settings) : $defaults;
            $atts = shortcode_atts($settings, $atts);
            global $wpdb;
            $html = $atts['global_before'];
            if (!empty($atts['title'])) {
                $html .= '<h3>' . esc_html($atts['title']) . '</h3>';
            }
            $query = $wpdb->prepare("SELECT * FROM {$wpdb->base_prefix}site_comments WHERE blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT %d", intval($atts['number']));
            $comments = $wpdb->get_results($query, ARRAY_A);
            if ($comments && count($comments) > 0) {
                $html .= '<ul class="network-comments-list">';
                foreach ($comments as $comment) {
                    $html .= $atts['before'] . '<li>';
                    if ($atts['avatars'] === 'show') {
                        $id_or_email = !empty($comment['comment_author_user_id']) ? $comment['comment_author_user_id'] : $comment['comment_author_email'];
                        $html .= '<a href="' . esc_url($comment['comment_post_permalink']) . '">' . get_avatar($id_or_email, $atts['avatar_size'], '') . '</a> ';
                    }
                    $content = strip_tags($comment['comment_content']);
                    $excerpt = mb_strlen($content) > $atts['content_characters'] ? mb_substr($content, 0, $atts['content_characters']) . '...' : $content;
                    $html .= esc_html($excerpt);
                    $link_text = $atts['link'] !== '' ? $atts['link'] : __('Mehr', 'postindexer');
                    $html .= ' (<a href="' . esc_url($comment['comment_post_permalink']) . '#comment-' . intval($comment['comment_id']) . '">' . esc_html($link_text) . '</a>)';
                    $html .= '</li>' . $atts['after'];
                }
                $html .= '</ul>';
            } else {
                $html .= '<p>' . __('Keine Kommentare gefunden.', 'postindexer') . '</p>';
            }
            $html .= $atts['global_after'];
            return $html;
        });
    }
});
