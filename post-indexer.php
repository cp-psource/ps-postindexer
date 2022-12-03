<?php
/*
Plugin Name: PS Multisite Beitragsindex
Plugin URI: https://n3rds.work/piestingtal_source/multisite-beitragsindex-plugin/
Description: Indiziert alle Beiträge in Deinem Netzwerk und bringt sie an einen Ort - ein sehr leistungsfähiges Tool, mit dem Du Beiträge auf unterschiedliche Weise anzeigen oder Dein Netzwerk verwalten kannst.
Author: WMS N@W
Version: 3.1.3
Author URI: https://n3rds.work
Requires at least: 4.9
Network: true
Text Domain: postindexer
Domain Path: /languages


*/

// +----------------------------------------------------------------------+
// | Copyright 2018-2022 WMS N@W (https://n3rds.work/)                                |
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
require 'psource/psource-plugin-update/psource-plugin-updater.php';
$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://n3rds.work//wp-update-server/?action=get_metadata&slug=ps-postindexer', 
	__FILE__, 
	'ps-postindexer' 
);

define( 'POST_INDEXER_PLUGIN_DIR', plugin_dir_path( __FILE__) );

require_once POST_INDEXER_PLUGIN_DIR . 'includes/config.php';
require_once POST_INDEXER_PLUGIN_DIR . 'includes/functions.php';

// Include the database model we will be using across classes
require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.model.php';

// Include the network query class for other plugins to use
require_once POST_INDEXER_PLUGIN_DIR . 'classes/networkquery.php';

// Include the rebuild cron class
require_once POST_INDEXER_PLUGIN_DIR . 'classes/cron.postindexerrebuild.php';

// Include the main class
require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.postindexeradmin.php';

