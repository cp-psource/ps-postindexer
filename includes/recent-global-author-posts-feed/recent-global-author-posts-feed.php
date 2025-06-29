<?php

// Postindexer-Erweiterung registrieren (immer ganz oben, außerhalb von Bedingungen)
add_filter('postindexer_extensions', function($exts) {
    $exts['recent-global-author-posts-feed'] = array(
        'title' => 'Globaler Autoren-Beitrags-Feed',
        'description' => __('Stellt einen globalen RSS-Feed für Beiträge eines Autors im Netzwerk bereit.', 'postindexer'),
        'file' => __FILE__,
        'author' => 'PSOURCE',
        'version' => '1.0.0',
        'network' => true,
        'has_settings' => false
    );
    return $exts;
}, 1);

// Feed-Logik nur laden, wenn Erweiterung aktiv ist
function rgap_feed_extension_active() {
    $settings = get_site_option('postindexer_extensions_settings', []);
    $site_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
    $ext = $settings['recent-global-author-posts-feed'] ?? null;
    $active = isset($ext['active']) ? (int)$ext['active'] : 0;
    $scope = $ext['scope'] ?? 'main';
    $sites = $ext['sites'] ?? [];
    if ($active) {
        if ($scope === 'network') return true;
        if ($scope === 'main' && $site_id == $main_site) return true;
        if ($scope === 'sites' && in_array($site_id, $sites)) return true;
    }
    return false;
}

if (rgap_feed_extension_active()) {

if (empty($wp)) {
	require_once('../wp-load.php');
	wp('feed=rss2');
}

global $network_query, $network_post;

// Remove all excerpt more filters
remove_all_filters('excerpt_more');

@header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . '>';

$number = isset($_GET['number']) ? $_GET['number'] : 25;

$author = isset($_GET['author']) ? $_GET['author'] : 1;

if(!is_numeric($author)) {
	// We could have the user login of the user so find the id from that
	$theauthor = get_user_by( 'login', $author );
	if(is_object($theauthor)) {
		$author = $theauthor->ID;
	}
} else {
	$theauthor = get_user_by( 'id', $author );
}

$posttype = isset($_GET['posttype']) ? $_GET['posttype'] : 'post';

$network_query_posts = network_query_posts( array( 'post_type' => $posttype, 'posts_per_page' => $number, 'author' => $author ));

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
	<title><?php bloginfo_rss('name'); _e(' – Neueste globale Beiträge von: ', 'postindexer'); echo $theauthor->display_name; ?></title>
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
<?php } // End if extension active ?>
