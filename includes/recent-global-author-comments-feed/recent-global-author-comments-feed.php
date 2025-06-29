<?php

// Postindexer-Erweiterung registrieren (immer ganz oben, außerhalb von Bedingungen)
add_filter('postindexer_extensions', function($exts) {
    $exts['recent_global_author_comments_feed'] = array(
        'title' => 'Globaler Autoren-Kommentar-Feed',
        'description' => __('Stellt einen globalen RSS-Feed für Kommentare eines Autors im Netzwerk bereit.', 'postindexer'),
        'file' => __FILE__,
        'author' => 'PSOURCE',
        'version' => '1.0.3.3',
        'network' => true,
        'has_settings' => false
    );
    return $exts;
}, 1);

// Feed-Logik nur laden, wenn Erweiterung aktiv ist
function rga_feed_extension_active() {
    $settings = get_site_option('postindexer_extensions_settings', []);
    $site_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
    $ext = $settings['recent_global_author_comments_feed'] ?? null;
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

if (rga_feed_extension_active()) {

/**
 * Create author comments feed
 */
function recent_global_author_comments_feed() {
    global $wpdb, $current_site;

    $number = ( empty( $_GET['number'] ) ) ? '25' : intval($_GET['number']);
    $author = ( empty( $_GET['uid'] ) ) ? '0'  : intval($_GET['uid']);

    $query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "site_comments WHERE site_id = '" . $current_site->id . "' AND comment_author_user_id = %d AND blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT %d", $author, $number);
    $comments = $wpdb->get_results( $query, ARRAY_A );

    if ( count( $comments ) > 0 ) {
        $last_published_post_date_time = $wpdb->get_var($wpdb->prepare("SELECT comment_date_gmt FROM " . $wpdb->base_prefix . "site_comments WHERE site_id = %d AND comment_author_user_id = %d AND blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT 1", $current_site->id, $author));
    } else {
        $last_published_post_date_time = time();
    }

    if ( $author > 0 ) {
        $author_user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $author));
    }
    
    header( 'HTTP/1.0 200 OK' );
    header( 'Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true );
    $more = 1;

    echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>

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
        <title><?php bloginfo_rss('name'); ?> <?php echo $author_user_login . ' '; ?><?php _e('Kommentare', 'postindexer'); ?></title>
        <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
        <link><?php bloginfo_rss('url') ?></link>
        <description><?php bloginfo_rss("description") ?></description>
        <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $last_published_post_date_time, false); ?></pubDate>
        <?php the_generator( 'rss2' ); ?>
        <language><?php bloginfo_rss( 'language' ); ?></language>
        <?php
        if ( count( $comments ) > 0 ) {
            foreach ($comments as $comment) {
				if (intval($comment['blog_id']) < 2) 
					$table_name = $wpdb->base_prefix . "posts";
				else
					$table_name = $wpdb->base_prefix . $comment['blog_id'] . "_posts";
						
				$sql_str = $wpdb->prepare("SELECT post_title FROM " . $table_name ." WHERE ID = %d", $comment['comment_post_id']);
				
                $post_title = $wpdb->get_var( $sql_str );
                if ( !empty( $comment['comment_author_user_id'] ) && $comment['comment_author_user_id'] > 0 ) {
                    $author_display_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $comment['comment_author_user_id']));
                }
                if ( !empty( $author_user_login ) ) {
                    $comment_author = $author_display_name;
                } else {
                    $comment_author = $comment['comment_author_email'];
                }
                ?>
                <item>
                    <title><?php _e('Kommentare zu', 'postindexer'); ?>: <?php echo stripslashes( $post_title ); ?></title>
                    <link><?php echo $comment['comment_post_permalink']; ?>#comment-<?php echo $comment['comment_id']; ?></link>

                    <dc:creator><?php echo $comment['comment_author']; ?></dc:creator>
                    <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $comment['comment_date_gmt'], false); ?></pubDate>

                    <guid isPermaLink="false"><?php echo $comment['comment_post_permalink']; ?>#comment-<?php echo $comment['comment_id']; ?></guid>
                    <description><![CDATA[<?php echo stripslashes( strip_tags( $comment['comment_content'] ) ); ?>]]></description>
                </item>
                <?php
            }
        }
        ?>
    </channel>
    </rss>
    <?php
}
add_action( 'do_feed_recent-global-author-comments', 'recent_global_author_comments_feed' );

/**
 * Custom rewrite rules for the feed
 *
 * @param <type> $wp_rewrite
 * @return void
 */
function recent_global_author_comments_feed_rewrite( $wp_rewrite ) {
    $feed_rules = array(
        'feed/(.+)' => 'index.php?feed=' . $wp_rewrite->preg_index(1),
        '(.+).xml'  => 'index.php?feed='. $wp_rewrite->preg_index(1),
    );
    $wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;
}
add_filter( 'generate_rewrite_rules', 'recent_global_author_comments_feed_rewrite' );

}
