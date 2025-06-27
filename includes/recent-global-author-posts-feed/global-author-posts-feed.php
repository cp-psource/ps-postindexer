<?php
/*
Plugin Name: Recent Global Author Posts Feed
Plugin URI: http://premium.wpmudev.org/project/recent-global-author-posts-feed/
Description: RSS2 feeds for global posts by authors - to access feed go to http://yoursite.com/feed/globalauthorpostsfeed
Version: 3.0.2
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
WDP ID: 87
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

class globalauthorpostsfeed {

	var $build = 1;
	var $db;

	function __construct() {
		global $wpdb;

		$this->db = $wpdb;
		if ( $this->db->blogid == 1 ) {
			// Only add the feed for the main site
			add_action( 'init', array( $this, 'initialise_global_author_posts_feed' ) );
		}

		add_filter( 'ms_user_row_actions', array( $this, 'add_user_row_action' ), 10, 2 );
	}

	function initialise_global_author_posts_feed() {
		add_feed( 'globalauthorpostsfeed', array( $this, 'do_global_author_posts_feed' ) );

		$installed = get_option( 'globalauthorpostsfeed_version', false );
		if ( $installed === false || $installed < $this->build ) {
			// We need to flush our rewrites so that the new feed is added and recognised
			flush_rewrite_rules();
			update_option( 'globalauthorpostsfeed_version', $this->build );
		}
	}

	function do_global_author_posts_feed() {
		global $network_query, $network_post;

		// Remove all excerpt more filters
		remove_all_filters( 'excerpt_more' );

		@header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );
		$more = 1;

		echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>';

		$number = isset( $_GET['number'] ) ? $_GET['number'] : 25;
		$author = isset( $_GET['author'] ) ? $_GET['author'] : 1;

		if ( !is_numeric( $author ) ) {
			// We could have the user login of the user so find the id from that
			$theauthor = get_user_by( 'login', $author );
			if ( is_object( $theauthor ) ) {
				$author = $theauthor->ID;
			}
		} else {
			$theauthor = get_user_by( 'id', $author );
		}

		$posttype = isset( $_GET['posttype'] ) ? $_GET['posttype'] : 'post';

		$network_query_posts = network_query_posts( array( 'post_type' => $posttype, 'posts_per_page' => $number, 'author' => $author ) );

		?>
		<rss version="2.0"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:wfw="http://wellformedweb.org/CommentAPI/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:atom="http://www.w3.org/2005/Atom"
			xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
			xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
			<?php do_action('rss2_ns'); ?>
		>

		<channel>
			<title><?php bloginfo_rss('name'); _e( ' - Recent Global Posts For Author : ', 'postindexer' ); echo $theauthor->display_name; ?></title>
			<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
			<link><?php bloginfo_rss('url') ?></link>
			<description><?php bloginfo_rss("description") ?></description>
			<lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', network_get_lastpostmodified('GMT'), false); ?></lastBuildDate>
			<language><?php bloginfo_rss( 'language' ); ?></language>
			<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
			<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
			<?php do_action('rss2_head'); ?>
			<?php while( network_have_posts()) : network_the_post(); ?>
			<item>
				<title><?php network_the_title_rss(); ?></title>
				<link><?php network_the_permalink_rss(); ?></link>
				<comments><?php network_comments_link_feed(); ?></comments>
				<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', network_get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
				<dc:creator><?php network_the_author(); ?></dc:creator>
				<?php network_the_category_rss('rss2'); ?>

				<guid isPermaLink="false"><?php network_the_guid(); ?></guid>
				<?php if (get_option('rss_use_excerpt')) { ?>
					<description><![CDATA[<?php network_the_excerpt_rss(); ?>]]></description>
				<?php } else { ?>
					<description><![CDATA[<?php network_the_excerpt_rss() ?>]]></description>
					<?php if ( strlen( $network_post->post_content ) > 0 ) { ?>
						<content:encoded><![CDATA[<?php network_the_content_feed('rss2'); ?>]]></content:encoded>
					<?php } else { ?>
						<content:encoded><![CDATA[<?php network_the_excerpt_rss(); ?>]]></content:encoded>
					<?php } ?>
				<?php } ?>
				<wfw:commentRss><?php echo esc_url( network_get_post_comments_feed_link(null, 'rss2') ); ?></wfw:commentRss>
				<slash:comments><?php echo network_get_comments_number(); ?></slash:comments>
				<?php network_rss_enclosure(); ?>
				<?php do_action('network_rss2_item'); ?>
			</item>
			<?php endwhile; ?>
		</channel>
		</rss>
		<?php
	}

	function add_user_row_action( $actions, $user ) {
		$actions['authorfeed'] = sprintf(
			'<a href="%s" target="_blank">Feed</a>',
			esc_url( add_query_arg( 'author', $user->ID, site_url( '/feed/globalauthorpostsfeed' ) ) )
		);
		return $actions;
	}

}

$globalauthorpostsfeed = new globalauthorpostsfeed();