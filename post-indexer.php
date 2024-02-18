<?php
/*
Plugin Name: Multisite Beitragsindex
Plugin URI: https://cp-psource.github.io/ps-postindexer/
Description: Indiziert alle Beiträge in Deinem Netzwerk und bringt sie an einen Ort - ein sehr leistungsfähiges Tool, mit dem Du Beiträge auf unterschiedliche Weise anzeigen oder Dein Netzwerk verwalten kannst.
Author: PSOURCE
Version: 3.1.5
Author URI: https://github.com/cp-psource
Requires at least: 4.9
Network: true
Text Domain: postindexer
Domain Path: /languages
*/

// +----------------------------------------------------------------------+
// | Copyright 2018-2024 PSOURCE (https://github.com/cp-psource)                                |
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

require_once POST_INDEXER_PLUGIN_DIR . 'includes/config.php';
require_once POST_INDEXER_PLUGIN_DIR . 'includes/functions.php';

// Include the database model we will be using across classes
require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.model.php';

// Include the network query class for other plugins to use
require_once POST_INDEXER_PLUGIN_DIR . 'classes/networkquery.php';

// Include the rebuild cron class
require_once POST_INDEXER_PLUGIN_DIR . 'classes/cron.postindexerrebuild.php';

if (is_admin()){
	// Include the main class
	require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.postindexeradmin.php';
}

