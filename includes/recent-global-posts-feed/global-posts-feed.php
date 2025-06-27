<?php
/*
Plugin Name: Recent Global Posts Feed
Plugin URI: http://premium.wpmudev.org/project/recent-global-posts-feed/
Description: RSS2 feed showing global posts - to access feed go to http://yoursite.com/feed/globalpostsfeed
Version: 3.1.1
Author: PSOURCE
Author URI: http://premium.wpmudev.org/
WDP ID: 70
Network: true
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

if ( get_current_blog_id() != 1 ) {
	return;
}

add_action( 'init', 'globalpostsfeed_setup_feed' );

add_filter( 'network_admin_plugin_action_links', 'globalpostsfeed_action_links', 10, 2 );

register_activation_hook( __FILE__, 'globalpostsfeed_activation' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function globalpostsfeed_setup_feed() {
	add_feed( 'globalpostsfeed', 'globalpostsfeed_do_feed' );
}

function globalpostsfeed_activation() {
	globalpostsfeed_setup_feed();
	flush_rewrite_rules();
}

function globalpostsfeed_do_feed() {
	global $network_post;

	network_query_posts( array(
		'post_type'      => filter_input( INPUT_GET, 'posttype', FILTER_DEFAULT, array( 'options' => array( 'default' => 'post' ) ) ),
		'posts_per_page' => filter_input( INPUT_GET, 'number', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1, 'default' => 25 ) ) ),
	) );

	// Remove all excerpt more filters
	remove_all_filters( 'excerpt_more' );

	if ( !headers_sent() ) {
		status_header( 200 );
		header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );
	}

	echo '<?xml version="1.0" encoding="', get_option( 'blog_charset' ), '"?>';
	?><rss version="2.0"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:atom="http://www.w3.org/2005/Atom"
		xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
		xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
		<?php do_action( 'rss2_ns' ) ?>
		>

		<channel>
			<title><?php bloginfo_rss( 'name' )?> - <?php _e( 'Recent Global Posts', 'postindexer' ) ?></title>
			<link><?php bloginfo_rss( 'url' ) ?></link>
			<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml"/>
			<description><?php bloginfo_rss( "description" ) ?></description>
			<lastBuildDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', network_get_lastpostmodified( 'GMT' ), false ) ?></lastBuildDate>
			<language><?php bloginfo_rss( 'language' ) ?></language>
			<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ) ?></sy:updatePeriod>
			<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ) ?></sy:updateFrequency>
			<?php do_action( 'rss2_head' ) ?>
			<?php while ( network_have_posts() ) : network_the_post(); ?>
				<item>
					<title><![CDATA[<?php network_the_title_rss() ?>]]></title>
					<link><?php network_the_permalink_rss() ?></link>
					<comments><?php network_comments_link_feed() ?></comments>
					<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', network_get_post_time( 'Y-m-d H:i:s', true ), false ) ?></pubDate>
					<dc:creator><![CDATA[<?php network_the_author() ?>]]></dc:creator>
					<?php network_the_category_rss( 'rss2' ) ?>
					<guid isPermaLink="false"><?php network_the_guid() ?></guid>
					<?php if ( get_option( 'rss_use_excerpt' ) ) : ?>
						<description><![CDATA[<?php network_the_excerpt_rss(); ?>]]></description>
					<?php  else : ?>
						<description><![CDATA[<?php network_the_excerpt_rss() ?>]]></description>
						<?php if ( strlen( $network_post->post_content ) > 0 ) : ?>
							<content:encoded><![CDATA[<?php network_the_content_feed( 'rss2' ) ?>]]></content:encoded>
						<?php else : ?>
							<content:encoded><![CDATA[<?php network_the_excerpt_rss() ?>]]></content:encoded>
						<?php endif; ?>
					<?php endif; ?>
					<wfw:commentRss><?php echo esc_url( network_get_post_comments_feed_link( null, 'rss2' ) ); ?></wfw:commentRss>
					<slash:comments><?php echo network_get_comments_number(); ?></slash:comments>
					<?php network_rss_enclosure(); ?>
					<?php do_action( 'network_rss2_item' ); ?>
				</item>
			<?php endwhile; ?>
		</channel>
	</rss><?php
}

function globalpostsfeed_action_links( $actions, $plugin ) {
    if ( $plugin == plugin_basename( __FILE__ ) ) {
        array_unshift( $actions, sprintf(
            '<a href="%s" target="_blank">%s</a>',
            site_url( '/feed/globalpostsfeed' ),
            esc_html__( 'Feed URL', 'postindexer' )
        ) );
    }
	
	return $actions;
}