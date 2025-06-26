<?php
/*
Plugin Name: Multisite Suche
Plugin URI: https://cp-psource.github.io/global-site-search/
Description: Ein großartiges Plugin, das eine globale Suche in allen Blogs auf Deiner WordPress Multisite/BuddyPress-Installation mit Leichtigkeit ermöglicht!
Author: PSOURCE
Version: 3.2.4
Requires at least: 4.9
Author URI: https://github.com/cp-psource
Network: true
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


// do not remove dirname( __FILE__ ) because it could case to loading wrong file
require_once dirname( __FILE__ ) . '/functions.php' ;
require_once dirname( __FILE__ ) . '/widgets.php';

class global_site_search {

	/** @var wpdb */
	var $db;

	var $global_site_search_base = 'site-search'; //domain.tld/BASE/ Ex: domain.tld/user/

	function __construct() {
		global $wpdb;

		$this->db = $wpdb;

		if ( in_array( get_current_blog_id(), global_site_search_get_allowed_blogs() ) ) {
			add_action( 'init', array( $this, 'global_site_search_page_setup' ) );

			// Add the rewrites
			add_action( 'generate_rewrite_rules', array( $this, 'add_rewrite' ) );
			add_filter( 'query_vars', array( $this, 'add_queryvars' ) );

			add_filter( 'the_content', array( $this, 'global_site_search_output' ), 20 );
			//add_filter( 'the_title', array( $this, 'global_site_search_title_output'), 99, 2 );
		}

		add_action( 'wpmu_options', array( $this, 'global_site_search_site_admin_options' ) );
		add_action( 'update_wpmu_options', array( $this, 'global_site_search_site_admin_options_process' ) );
	}

	function add_queryvars( $vars ) {
		// This function add the namespace (if it hasn't already been added) and the
		// eventperiod queryvars to the list that WordPress is looking for.
		// Note: Namespace provides a means to do a quick check to see if we should be doing anything

		return array_unique( array_merge( $vars, array( 'namespace', 'search', 'paged', 'type' ) ) );
	}

	function add_rewrite( $wp_rewrite ) {

		// This function adds in the api rewrite rules
		// Note the addition of the namespace variable so that we know these are vent based
		// calls
		$new_rules = array();

		$new_rules[$this->global_site_search_base . '/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=gss&search=' . $wp_rewrite->preg_index( 1 ) . '&paged=' . $wp_rewrite->preg_index( 2 ) . '&type=search&pagename=' . $this->global_site_search_base;
		$new_rules[$this->global_site_search_base . '/(.+)'] = 'index.php?namespace=gss&search=' . $wp_rewrite->preg_index( 1 ) . '&type=search&pagename=' . $this->global_site_search_base;
		$new_rules[$this->global_site_search_base] = 'index.php?namespace=gss&type=search&pagename=' . $this->global_site_search_base;

		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

		return $wp_rewrite;
	}

	function global_site_search_page_setup() {
		$page_id = get_option( 'global_site_search_page', false );
		if ( empty( $page_id ) || !is_object( get_post( $page_id ) ) && is_super_admin() ) {
			// a page hasn't been set - so check if there is already one with the base name
			$page_id = $this->db->get_var( $this->db->prepare(
				"SELECT ID FROM {$this->db->posts} WHERE post_name = %s AND post_type = 'page'",
				$this->global_site_search_base
			) );

			if ( empty( $page_id ) ) {
				// Doesn't exist so create the page
				$page_id = wp_insert_post( array(
					"post_content"   => '',
					"post_title"     => __( 'Netzwerksuche', 'globalsitesearch' ),
					"post_excerpt"   => '',
					"post_status"    => 'publish',
					"comment_status" => 'closed',
					"ping_status"    => 'closed',
					"post_name"      => $this->global_site_search_base,
					"post_type"      => 'page',
				) );

				flush_rewrite_rules();
			}

			update_option( 'global_site_search_page', $page_id );
		}
	}

	function global_site_search_site_admin_options() {
		$global_site_search_per_page = get_site_option( 'global_site_search_per_page', 10 );
		$global_site_search_post_type = get_site_option( 'global_site_search_post_type', 'post' );

		$post_types = $this->db->get_col( "SELECT post_type FROM {$this->db->base_prefix}network_posts GROUP BY post_type" );

		?><h3><?php _e( 'Netzwerksuche', 'globalsitesearch' ) ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Auflistung pro Seite', 'globalsitesearch' ) ?></th>
				<td>
					<select name="global_site_search_per_page" id="global_site_search_per_page">
						<?php for ( $i = 5; $i <= 50; $i += 5 ) : ?>
						<option<?php selected( $global_site_search_per_page, $i ) ?>><?php echo $i ?></option>
						<?php endfor; ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Hintergrundfarbe', 'globalsitesearch' ) ?></th>
				<td>
					<input name="global_site_search_background_color" type="text" id="global_site_search_background_color" value="<?php echo esc_attr( get_site_option( 'global_site_search_background_color', '#F2F2EA' ) ) ?>" size="20">
					<br><?php _e( 'Standard', 'globalsitesearch' ) ?>: #F2F2EA
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Alternative Hintergrundfarbe', 'globalsitesearch' ) ?></th>
				<td>
					<input name="global_site_search_alternate_background_color" type="text" id="global_site_search_alternate_background_color" value="<?php echo esc_attr( get_site_option( 'global_site_search_alternate_background_color', '#FFFFFF' ) ) ?>" size="20">
					<br><?php _e( 'Standard', 'globalsitesearch' ) ?>: #FFFFFF
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Rahmenfarbe', 'globalsitesearch' ) ?></th>
				<td>
					<input name="global_site_search_border_color" type="text" id="global_site_search_border_color" value="<?php echo esc_attr( get_site_option( 'global_site_search_border_color', '#CFD0CB' ) ) ?>" size="20">
					<br><?php _e( 'Standard', 'globalsitesearch' ) ?>: #CFD0CB
				</td>
			</tr>

			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Beitragstyp auflisten', 'globalsitesearch' ) ?></th>
				<td>
					<select name="global_site_search_post_type" id="global_site_search_post_type">
						<option value="all"><?php _e( 'alle', 'globalsitesearch' ) ?></option>
						<?php foreach ( $post_types as $r ) : ?>
						<option value="<?php echo esc_attr( $r ) ?>"<?php selected( $global_site_search_post_type, $r ) ?> ><?php _e( $r, 'globalsitesearch' ) ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table><?php
	}

	function global_site_search_site_admin_options_process() {
		update_site_option( 'global_site_search_per_page', filter_input( INPUT_POST, 'global_site_search_per_page', FILTER_VALIDATE_INT, array(
			'options' => array(
				'min_range' => 5,
				'max_range' => 50,
				'default'   => 10,
			),
		) ) );

		update_site_option( 'global_site_search_background_color', trim( filter_input( INPUT_POST, 'global_site_search_background_color' ) ) );
		update_site_option( 'global_site_search_alternate_background_color', trim( filter_input( INPUT_POST, 'global_site_search_alternate_background_color' ) ) );
		update_site_option( 'global_site_search_border_color', trim( filter_input( INPUT_POST, 'global_site_search_border_color' ) ) );
		update_site_option( 'global_site_search_post_type', filter_input( INPUT_POST, 'global_site_search_post_type' ) );
	}

	function global_site_search_title_output( $title ) {
		global $current_site, $wp_query;

		if ( isset( $wp_query->query_vars['namespace'] ) && $wp_query->query_vars['namespace'] == 'gss' && $wp_query->query_vars['type'] == 'search' ) {
			$title = '<a href="http://' . $current_site->domain . $current_site->path . $this->global_site_search_base . '/">' . __( 'Netzwerksuche', 'globalsitesearch' ) . '</a>';
			if ( isset( $wp_query->query_vars['paged'] ) && $wp_query->query_vars['paged'] > 1 ) {
				$search = isset( $wp_query->query_vars['search'] ) ? $wp_query->query_vars['search'] : '';
				$title .= ' &raquo; <a href="http://' . $current_site->domain . $current_site->path . $this->global_site_search_base . '/' . urlencode( $search ) . '/page/' . $wp_query->query_vars['paged'] . '/">' . $wp_query->query_vars['paged'] . '</a>';
			}
		}

		return $title;
	}

	function global_site_search_output( $content ) {
		global $wp_query;

		if ( !isset( $wp_query->query_vars['namespace'] ) || $wp_query->query_vars['namespace'] != 'gss' || $wp_query->query_vars['type'] != 'search' ) {
			return $content;
		}

		// We are on a search results page

		$global_site_search_per_page = get_site_option( 'global_site_search_per_page', '10' );
		$global_site_search_post_type = get_site_option( 'global_site_search_post_type', 'post' );

		//=====================================//
		//
		$phrase = isset( $wp_query->query_vars['search'] ) ? urldecode( $wp_query->query_vars['search'] ) : '';
		if ( empty( $phrase ) && isset( $_REQUEST['phrase'] ) ) {
			$phrase = trim( $_REQUEST['phrase'] );
		}

		if ( empty( $phrase ) ) {
			ob_start();
			global_site_search_form();
			$content .= ob_get_clean();

			return $content;
		}

		$theauthor = get_user_by( 'login', $phrase );
		if ( is_object( $theauthor ) ) {
			$author_id = $theauthor->ID;
		}

		$parameters = array();
		if ( isset( $author_id ) && is_numeric( $author_id ) && $author_id != 0 ) {
			$parameters['author'] = $author_id;
		} else {
			$parameters['s'] = $phrase;
		}

		$parameters['post_type'] = $global_site_search_post_type != 'all'
			? $global_site_search_post_type
			: $this->db->get_col( "SELECT post_type FROM {$this->db->base_prefix}network_posts GROUP BY post_type" );

		// Add in the start and end numbers
		$parameters['posts_per_page'] = absint( $global_site_search_per_page );

		// Set the page number
		if ( !isset( $wp_query->query_vars['paged'] ) || $wp_query->query_vars['paged'] <= 1 ) {
			$parameters['paged'] = 1;
			$start = 0;
		} else {
			$parameters['paged'] = absint( $wp_query->query_vars['paged'] );
			$start = $global_site_search_per_page * ( $wp_query->query_vars['paged'] - 1 );
		}

		//=====================================//
		
		ob_start();
		$network_query_posts = network_query_posts( $parameters );
		include global_site_search_locate_template( 'global-site-search.php' );
		$content .= ob_get_clean();

		return $content;
	}

}

if ( ! defined( 'ABSPATH' ) ) exit;

// Integration als Erweiterung für den Beitragsindexer
add_action('plugins_loaded', function() {
	if ( !class_exists('Postindexer_Extensions_Admin') ) return;
	global $postindexer_extensions_admin;
	if ( !isset($postindexer_extensions_admin) ) {
		if ( isset($GLOBALS['postindexeradmin']) && isset($GLOBALS['postindexeradmin']->extensions_admin) ) {
			$postindexer_extensions_admin = $GLOBALS['postindexeradmin']->extensions_admin;
		}
	}
	if ( isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('global_site_search') ) {
		// Hier die eigentliche Initialisierung der Such-Erweiterung
		if (class_exists('Global_Site_Search')) {
			new Global_Site_Search();
		}
	}
});

register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

if (class_exists('global_site_search')) {
	class Global_Site_Search_Settings_Renderer extends global_site_search {
		public function render_settings_form() {
			// Werte laden
			$per_page = get_site_option( 'global_site_search_per_page', 10 );
			$background = get_site_option( 'global_site_search_background_color', '#F2F2EA' );
			$alt_background = get_site_option( 'global_site_search_alternate_background_color', '#FFFFFF' );
			$border = get_site_option( 'global_site_search_border_color', '#CFD0CB' );
			$post_type = get_site_option( 'global_site_search_post_type', 'post' );
			$post_types = $this->db->get_col( "SELECT post_type FROM {$this->db->base_prefix}network_posts GROUP BY post_type" );
			ob_start();
			echo '<form method="post" action="network/settings.php?page=ps-multisite-index-extensions" style="max-width:700px;">';
			wp_nonce_field('ps_gss_settings_save','ps_gss_settings_nonce');
			echo '<div style="background:#fff;border:1px solid #e5e5e5;padding:2em 2em 1em 2em;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:2em;">';
			echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2em;">';
			// Auflistung pro Seite
			echo '<div><label for="gss_per_page" style="font-weight:bold;">Auflistung pro Seite</label><br>';
			echo '<select name="global_site_search_per_page" id="gss_per_page" style="min-width:120px;">';
			for ( $i = 5; $i <= 50; $i += 5 ) {
				echo '<option value="'.$i.'"'.selected($per_page,$i,false).'>'.$i.'</option>';
			}
			echo '</select></div>';
			// Hintergrundfarbe
			echo '<div><label for="gss_bg" style="font-weight:bold;">Hintergrundfarbe</label><br>';
			echo '<input name="global_site_search_background_color" type="text" id="gss_bg" value="'.esc_attr($background).'" size="20"> <span style="color:#888;font-size:0.95em;">Standard: #F2F2EA</span></div>';
			// Alternative Hintergrundfarbe
			echo '<div><label for="gss_alt_bg" style="font-weight:bold;">Alternative Hintergrundfarbe</label><br>';
			echo '<input name="global_site_search_alternate_background_color" type="text" id="gss_alt_bg" value="'.esc_attr($alt_background).'" size="20"> <span style="color:#888;font-size:0.95em;">Standard: #FFFFFF</span></div>';
			// Rahmenfarbe
			echo '<div><label for="gss_border" style="font-weight:bold;">Rahmenfarbe</label><br>';
			echo '<input name="global_site_search_border_color" type="text" id="gss_border" value="'.esc_attr($border).'" size="20"> <span style="color:#888;font-size:0.95em;">Standard: #CFD0CB</span></div>';
			// Beitragstyp
			echo '<div style="grid-column:1/3;"><label for="gss_post_type" style="font-weight:bold;">Beitragstyp auflisten</label><br>';
			echo '<select name="global_site_search_post_type" id="gss_post_type" style="min-width:160px;">';
			echo '<option value="all"'.selected($post_type,'all',false).'>alle</option>';
			foreach ($post_types as $r) {
				echo '<option value="'.esc_attr($r).'"'.selected($post_type,$r,false).'>'.esc_html($r).'</option>';
			}
			echo '</select></div>';
			echo '</div>';
			submit_button();
			echo '</div>';
			echo '</form>';
			return ob_get_clean();
		}
	}
}