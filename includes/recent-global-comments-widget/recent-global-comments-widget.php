<?php
/*
Plugin Name: Recent Global Comments Widget
Plugin URI: http://premium.wpmudev.org/project/recent-global-comments-widget
Description: Display all the latest comments from across your entire WordPress Multisite network - using a simple but powerful widget!
Author: WPMU DEV
Author URI: http://ivan.sh
Version: 1.0.4.2
Network: true
WDP ID: 65
Text Domain: recent-global-comments-widget
Domain Path: languages
*/

/* 
Copyright 2007-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Support for WPMU DEV Dashboard plugin
include_once( dirname(__FILE__) . '/lib/dash-notices/wpmudev-dash-notification.php');

function recent_global_comment_widget_init_proc() {
	
	/* Setup the tetdomain for i18n language handling see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain */
    load_plugin_textdomain( 'recent-global-comments-widget', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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

function widget_recent_global_comments_init() {
	global $wpdb, $recent_global_comments_widget_main_blog_only;

	// This saves options and prints the widget's config form.
	function widget_recent_global_comments_control() {
		global $wpdb;
		$options = $newoptions = get_option('widget_recent_global_comments');
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

            <label for="recent-global-comments-title" style="line-height:35px;display:block;"><?php _e('Title', 'recent-global-comments-widget'); ?>:<br />
            <input class="widefat" id="recent-global-comments-title" name="recent-global-comments-title" value="<?php echo sanitize_text_field($options['recent-global-comments-title']); ?>" type="text" style="width:95%;">
            </select>
            </label>
            <label for="recent-global-comments-number" style="line-height:35px;display:block;"><?php _e('Number', 'recent-global-comments-widget'); ?>:<br />
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
            <label for="recent-global-comments-content-characters" style="line-height:35px;display:block;"><?php _e('Content Characters', 'recent-global-comments-widget'); ?>:<br />
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
            <label for="recent-global-comments-avatars" style="line-height:35px;display:block;"><?php _e('Avatars', 'recent-global-comments-widget'); ?>:<br />
            <select name="recent-global-comments-avatars" id="recent-global-comments-avatars" style="width:95%;">
            <option value="show" <?php if ( isset( $options['recent-global-comments-avatars'] ) && $options['recent-global-comments-avatars'] == 'show' ){ echo 'selected="selected"'; } ?> ><?php _e('Show', 'recent-global-comments-widget'); ?></option>
            <option value="hide" <?php if ( isset( $options['recent-global-comments-avatars'] ) && $options['recent-global-comments-avatars'] == 'hide' ){ echo 'selected="selected"'; } ?> ><?php _e('Hide', 'recent-global-comments-widget'); ?></option>
            </select>
            </label>
            <label for="recent-global-comments-avatar-size" style="line-height:35px;display:block;"><?php _e('Avatar Size', 'recent-global-comments-widget'); ?>:<br />
            <select name="recent-global-comments-avatar-size" id="recent-global-comments-avatar-size" style="width:95%;">
            <option value="16" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '16'){ echo 'selected="selected"'; } ?> ><?php _e('16px', 'recent-global-comments-widget'); ?></option>
            <option value="32" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '32'){ echo 'selected="selected"'; } ?> ><?php _e('32px', 'recent-global-comments-widget'); ?></option>
            <option value="48" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '48'){ echo 'selected="selected"'; } ?> ><?php _e('48px', 'recent-global-comments-widget'); ?></option>
            <option value="96" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '96'){ echo 'selected="selected"'; } ?> ><?php _e('96px', 'recent-global-comments-widget'); ?></option>
            <option value="128" <?php if ( isset( $options['recent-global-comments-avatar-size'] ) && $options['recent-global-comments-avatar-size'] == '128'){ echo 'selected="selected"'; } ?> ><?php _e('128px', 'recent-global-comments-widget'); ?></option>
            </select>
            </label>
            <input type="hidden" name="recent-global-comments-submit" id="recent-global-comments-submit" value="1" />
        </div>
        <?php
	}
    
    // This prints the widget
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
						echo ' (<a href="' . $comment['comment_post_permalink'] . '#comment-' . $comment['comment_id'] . '">' . __('More', 'recent-global-comments-widget') . '</a>)';
						echo '</li>';
					}
					echo '</ul>';
				}
				//=================================================//
			?>
		<?php echo $after_widget; ?>
        <?php
	}

	// Tell Dynamic Sidebar about our new widget and its control
	if ( $recent_global_comments_widget_main_blog_only == 'yes' ) {
		//if ( $wpdb->blogid == 1 ) {
		if ( is_main_site() ) {
			wp_register_sidebar_widget( 'recent_global_comments_widget', __( 'Recent Global Comments', 'recent-global-comments-widget' ), 'widget_recent_global_comments' );
			wp_register_widget_control( 'recent_global_comments_widget', __( 'Recent Global Comments', 'recent-global-comments-widget' ), 'widget_recent_global_comments_control' );
		}
	} else {
        wp_register_sidebar_widget( 'recent_global_comments_widget', __( 'Recent Global Comments', 'recent-global-comments-widget' ), 'widget_recent_global_comments' );
		wp_register_widget_control( 'recent_global_comments_widget', __( 'Recent Global Comments', 'recent-global-comments-widget' ), 'widget_recent_global_comments_control' );
	}
}

add_action('widgets_init', 'widget_recent_global_comments_init');
