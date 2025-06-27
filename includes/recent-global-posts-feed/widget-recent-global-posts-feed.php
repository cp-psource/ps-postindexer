<?php
/*
Plugin Name: Recent Posts Feed Icon Widget
Description: Allows a link to the Global Posts Feed to be placed in the sidebar
Author: PSOURCE
Author URI: http://premium.wpmudev.org/
Version: 3.1
*/

// +----------------------------------------------------------------------+
// | Copyright Incsub (http://incsub.com/)                                |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License, version 2, as  |
// | published by the Free Software Foundation.                           |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               |
// | MA 02110-1301 USA                                                    |
// +----------------------------------------------------------------------+

if ( !defined( 'RECENT_GLOBAL_POSTS_FEED_WIDGET_MAIN_BLOG_ONLY' ) ) {
	define( 'RECENT_GLOBAL_POSTS_FEED_WIDGET_MAIN_BLOG_ONLY', true );
}

class widget_recent_global_posts_feed extends WP_Widget {

	function widget_recent_global_posts_feed() {
		load_plugin_textdomain( 'rpgpfwidgets', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		$widget_ops = array( 'classname' => 'rgpwidget', 'description' => __( 'Recent Global Posts Feed', 'rpgpfwidgets' ) );
		$control_ops = array( 'id_base' => 'rpgpfwidget' );
		$this->WP_Widget( 'rpgpfwidget', __( 'Recent Global Posts Feed', 'rpgpfwidgets' ), $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {
		global $current_site;

		extract( $args );

		$defaults = array(
			'recentglobalpostsfeedtitle'     => '',
			'recentglobalpostsfeedrssimage'  => '',
			'recentglobalpostsfeedpoststype' => 'post'
		);

		foreach ( array_keys( $defaults ) as $key ) {
			if ( isset( $instance[$key] ) ) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract( $defaults );

		$recentglobalpostsfeedtitle = apply_filters( 'widget_title', $recentglobalpostsfeedtitle );

		echo $before_widget;
			echo $before_title;
				echo '<a href="http://', $current_site->domain, $current_site->path, 'feed/globalpostsfeed?posttype=', $recentglobalpostsfeedpoststype, '" >';
					if ( $recentglobalpostsfeedrssimage != 'hide' ) {
						echo '<img src="http://', $current_site->domain, $current_site->path, 'wp-includes/images/rss.png"> ';
					}
					echo esc_html( $recentglobalpostsfeedtitle );
				echo '</a>';
			echo $after_title;
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$defaults = array(
			'recentglobalpostsfeedtitle'     => '',
			'recentglobalpostsfeedrssimage'  => '',
			'recentglobalpostsfeedpoststype' => 'post'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {
		$defaults = array(
			'recentglobalpostsfeedtitle' => '',
			'recentglobalpostsfeedrssimage' => '',
			'recentglobalpostsfeedpoststype' => 'post'
		);

		$instance = wp_parse_args( (array)$instance, $defaults );
		extract( $instance );

		?><div>
			<p>
				<label for="<?php echo $this->get_field_id( 'recentglobalpostsfeedtitle' ) ?>"><?php _e( 'Title', 'rpgpfwidgets' ) ?>:</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'recentglobalpostsfeedtitle' ); ?>" name="<?php echo $this->get_field_name( 'recentglobalpostsfeedtitle' ) ?>" value="<?php echo esc_attr( stripslashes( $instance['recentglobalpostsfeedtitle'] ) ) ?>" type="text">
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'recentglobalpostsfeedrssimage' ) ?>"><?php _e( 'RSS Image', 'rpgpfwidgets' ) ?>:</label>
				<select name="<?php echo $this->get_field_name( 'recentglobalpostsfeedrssimage' ) ?>" id="<?php echo $this->get_field_id( 'recentglobalpostsfeedrssimage' ) ?>" class="widefat">
					<option value="show"<?php selected( $instance['recentglobalpostsfeedrssimage'], 'show' ) ?>><?php _e( 'Show', 'rpgpfwidgets' ) ?></option>
					<option value="hide"<?php selected( $instance['recentglobalpostsfeedrssimage'], 'hide' ) ?>><?php _e( 'Hide', 'rpgpfwidgets' ) ?></option>
				</select>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'recentglobalpostsfeedpoststype' ) ?>"><?php _e( 'Post type', 'rpgpfwidgets' ) ?>:</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'recentglobalpostsfeedpoststype' ) ?>" name="<?php echo $this->get_field_name( 'recentglobalpostsfeedpoststype' ) ?>" value="<?php echo esc_attr( stripslashes( $instance['recentglobalpostsfeedpoststype'] ) ) ?>" type="text">
			</p>

			<input type="hidden" name="<?php echo $this->get_field_name( 'recentglobalpostsfeedsubmit' ) ?>" id="<?php echo $this->get_field_id( 'recentglobalpostsfeedsubmit' ) ?>" value="1">
		</div><?php
	}

}

add_action( 'widgets_init', 'widget_recent_global_posts_feed_register' );
function widget_recent_global_posts_feed_register() {
	if ( !RECENT_GLOBAL_POSTS_FEED_WIDGET_MAIN_BLOG_ONLY || get_current_blog_id() == 1 ) {
		register_widget( 'widget_recent_global_posts_feed' );
	}
}
