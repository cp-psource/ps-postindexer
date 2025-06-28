<?php
/*
Plugin Name: Multisite Index
Plugin URI: https://cp-psource.github.io/ps-postindexer/
Description: Ein mächtiges Multisite-Index Plugin - Bringe deinen Content dahin wo du ihn brauchst!
Author: PSOURCE
Version: 3.1.7
Author URI: https://github.com/cp-psource
Requires at least: 4.9
Network: true
Text Domain: postindexer
Domain Path: /languages
*/

// +----------------------------------------------------------------------+
// | Copyright 2018-2025 PSOURCE (https://github.com/cp-psource)                                |
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

require 'psource/psource-plugin-update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
 
$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/cp-psource/ps-postindexer',
	__FILE__,
	'ps-postindexer'
);
 
//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('master');

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Es tut uns leid, aber Du kannst nicht direkt auf diese Datei zugreifen.' );
}

define( 'POST_INDEXER_PLUGIN_DIR', plugin_dir_path( __FILE__) );
// Erweiterung: Comment Form Text IMMER laden (Frontend & Backend)
require_once POST_INDEXER_PLUGIN_DIR . 'includes/comment-form-text/comment-form-text.php';

require_once POST_INDEXER_PLUGIN_DIR . 'includes/config.php';
require_once POST_INDEXER_PLUGIN_DIR . 'includes/functions.php';

add_action('init', function() {
    require_once POST_INDEXER_PLUGIN_DIR . 'includes/global-site-tags/global-site-tags.php';
    require_once POST_INDEXER_PLUGIN_DIR . 'includes/global-site-tags/widget-global-site-tags.php';
    require_once POST_INDEXER_PLUGIN_DIR . 'includes/live-stream-widget/live-stream.php';
});

// Widget-Loader für Neueste Netzwerk Beiträge (immer laden, aber Registrierung nach Scope)
require_once POST_INDEXER_PLUGIN_DIR . 'includes/recent-global-posts-widget/widget-recent-global-posts.php';
add_action('widgets_init', function() {
    global $postindexer_extensions_admin;
    if (isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('recent_global_posts_widget')) {
        if (function_exists('rgpwidget_register_widget')) {
            rgpwidget_register_widget();
        }
    }
}, 20);

// Widget-Loader für Global Comments Widget (immer laden, aber Registrierung nach Scope)
require_once POST_INDEXER_PLUGIN_DIR . 'includes/recent-global-comments-widget/recent-global-comments-widget.php';
add_action('widgets_init', function() {
    global $postindexer_extensions_admin;
    if (isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('recent_global_comments_widget')) {
        if (function_exists('widget_recent_global_comments_init')) {
            widget_recent_global_comments_init();
        }
    }
}, 21);

// Include the database model we will be using across classes
require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.model.php';

// Include the network query class for other plugins to use
require_once POST_INDEXER_PLUGIN_DIR . 'classes/networkquery.php';

// Include the rebuild cron class
require_once POST_INDEXER_PLUGIN_DIR . 'classes/cron.postindexerrebuild.php';

// Initialisiere Erweiterungsverwaltung IMMER, auch im Frontend
require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.postindexerextensionsadmin.php';
global $postindexer_extensions_admin;
if ( !isset($postindexer_extensions_admin) ) {
    $postindexer_extensions_admin = new Postindexer_Extensions_Admin();
}

if (is_admin()){
	// Include the main class
	require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.postindexeradmin.php';
}

// Modul: Comment Indexer IMMER laden, damit Hooks überall aktiv sind
require_once POST_INDEXER_PLUGIN_DIR . 'comment-indexer.php';

// Admin-Menü und Klasse NUR im Netzwerk-Admin laden
if (is_multisite() && is_network_admin()) {
    require_once POST_INDEXER_PLUGIN_DIR . 'admin/class.commentindexeradmin.php';
    new Comment_Indexer_Admin();
}

// Erweiterung: Comment Form Text IMMER laden (Frontend & Backend)
require_once POST_INDEXER_PLUGIN_DIR . 'includes/comment-form-text/comment-form-text.php';

// Erweiterung: Recent Global Author Posts Feed IMMER laden
require_once POST_INDEXER_PLUGIN_DIR . 'includes/recent-global-author-posts-feed/recent-global-author-posts-feed.php';

add_action('plugins_loaded', function() {
    load_plugin_textdomain('postindexer', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

