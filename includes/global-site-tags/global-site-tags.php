<?php

if ( !defined( 'GLOBAL_SITE_TAGS_BLOG' ) ) define( 'GLOBAL_SITE_TAGS_BLOG', 1 );

class globalsitetags {

	var $build = 5;

	/** @var wpdb */
	var $db;

	var $global_site_tags_base = 'tags'; //domain.tld/BASE/ Ex: domain.tld/tags/

	function __construct() {
		global $wpdb;

		// Get a local handle to the database
		$this->db = $wpdb;

		add_action( 'wpmu_options', array( $this, 'global_site_tags_site_admin_options' ) );
		add_action( 'update_wpmu_options', array( $this, 'global_site_tags_site_admin_options_process' ) );

		if ( GLOBAL_SITE_TAGS_BLOG == get_current_blog_id() ) {
			$installed = get_option( 'gst_installed', 0 );
			if ( $installed < $this->build || $installed == 'yes' ) {
				add_action( 'init', array( $this, 'initialise_plugin' ) );
			}

			// Add the rewrites
			add_action( 'generate_rewrite_rules', array( $this, 'add_rewrite' ) );
			add_filter( 'query_vars', array( $this, 'add_queryvars' ) );

			add_filter( 'the_content', array( $this, 'global_site_tags_output' ), 20 );
			add_filter( 'the_title', array( $this, 'global_site_tags_title_output' ), 99, 2 );
		}
	}

	function initialise_plugin() {
		// Flush the rules to add our pages
		flush_rewrite_rules();
		// Add the default tag page
		$this->global_site_tags_page_setup();
		// Set the option to say we are installed
		update_option( 'gst_installed', $this->build );
	}

	function add_queryvars( $vars ) {
		// This function add the namespace (if it hasn't already been added) and the
		// eventperiod queryvars to the list that WordPress is looking for.
		// Note: Namespace provides a means to do a quick check to see if we should be doing anything

		if ( !in_array( 'namespace', $vars ) ) $vars[] = 'namespace';
		if ( !in_array( 'tag', $vars ) ) $vars[] = 'tag';
		if ( !in_array( 'paged', $vars ) ) $vars[] = 'paged';
		if ( !in_array( 'type', $vars ) ) $vars[] = 'type';

		return $vars;
	}

	function add_rewrite( $wp_rewrite ) {

		// This function adds in the api rewrite rules
		// Note the addition of the namespace variable so that we know these are vent based
		// calls
		$new_rules = array();

		$new_rules[$this->global_site_tags_base . '/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=gst&tag=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=tag&pagename=' . $this->global_site_tags_base;
		$new_rules[$this->global_site_tags_base . '/(.+)'] = 'index.php?namespace=gst&tag=' . $wp_rewrite->preg_index(1) . '&type=tag&pagename=' . $this->global_site_tags_base;
		$new_rules[$this->global_site_tags_base . ''] = 'index.php?namespace=gst&type=tag&pagename=' . $this->global_site_tags_base;

		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

		return $wp_rewrite;

	}

	function global_site_tags_page_setup() {
		if ( get_option( 'global_site_tags_page_setup' ) != 'complete' && is_super_admin() ) {
			$page_id = get_option( 'global_site_tags_page' );
			if ( empty( $page_id ) ) {
				// a page hasn't been set - so check if there is already one with the base name
				$page_id = $this->db->get_var( "SELECT ID FROM {$this->db->posts} WHERE post_name = '{$this->global_site_tags_base}' AND post_type = 'page'" );
				if ( !empty( $page_id ) ) {
					$post = get_post( $page_id );
					if ( !$post ) {
						$page_id = 0; // Seite existiert nicht mehr, neu anlegen
					}
				}
				if ( empty( $page_id ) ) {
					// Doesn't exist so create the page
					$page_id = wp_insert_post( array(
						"post_author" => get_current_user_id(),
						"post_date" => current_time( 'mysql' ),
						"post_date_gmt" => current_time( 'mysql', 1 ),
						"post_content" => '',
						"post_title" => __( 'Tags', 'postindexer' ),
						"post_excerpt" => '',
						"post_status" => 'publish',
						"comment_status" => 'closed',
						"ping_status" => 'closed',
						"post_password" => '',
						"post_name" => $this->global_site_tags_base,
						"to_ping" => '',
						"pinged" => '',
						"post_modified" => current_time( 'mysql' ),
						"post_modified_gmt" => current_time( 'mysql', 1 ),
						"post_content_filtered" => '',
						"post_parent" => 0,
						"menu_order" => 0,
						"post_type" => 'page',
						"comment_count" => 0
					) );
				} else {
					// Seite existiert, Status ggf. auf publish setzen
					$post = get_post( $page_id );
					if ( $post && $post->post_status !== 'publish' ) {
						wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish' ) );
					}
				}
				update_option( 'global_site_tags_page', $page_id );
			}

			update_option( 'global_site_tags_page_setup', 'complete' );
		}
	}

	function global_site_tags_site_admin_options() {
		$global_site_tags_per_page = get_site_option( 'global_site_tags_per_page', '10' );
		$global_site_tags_shown = get_site_option( 'global_site_tags_shown', '50' );
		$global_site_tags_background_color = get_site_option( 'global_site_tags_background_color', '#F2F2EA' );
		$global_site_tags_alternate_background_color = get_site_option( 'global_site_tags_alternate_background_color', '#FFFFFF' );
		$global_site_tags_border_color = get_site_option( 'global_site_tags_border_color', '#CFD0CB' );
		$global_site_tags_banned_tags = get_site_option( 'global_site_tags_banned_tags', 'uncategorized' );
		$global_site_tags_post_type = get_site_option( 'global_site_tags_post_type', 'post' );
		$post_types = $this->global_site_tags_get_post_types();

		?><h3><?php _e( 'Seiten-Tags', 'postindexer' ) ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Angezeigte Tags', 'postindexer' ) ?></th>
				<td>
					<select name="global_site_tags_shown" id="global_site_tags_shown">
						<?php for ( $i = 5; $i <= 50; $i += 5 ) : ?>
						<option<?php selected( $i, $global_site_tags_shown ) ?>><?php echo $i ?></option>
						<?php endfor; ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Auflistung pro Seite', 'postindexer' ) ?></th>
				<td>
					<select name="global_site_tags_per_page" id="global_site_tags_per_page">
						<?php for ( $i = 5; $i <= 50; $i += 5 ) : ?>
						<option<?php selected( $i, $global_site_tags_per_page ) ?>><?php echo $i ?></option>
						<?php endfor; ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Hintergrundfarbe', 'postindexer' ) ?></th>
				<td>
					<input name="global_site_tags_background_color" type="text" id="global_site_tags_background_color" value="<?php echo esc_attr( $global_site_tags_background_color ) ?>" size="20">
					<br><?php _e( 'Standard', 'postindexer' ) ?>: #F2F2EA
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Alternative Hintergrundfarbe', 'postindexer' ) ?></th>
				<td>
					<input name="global_site_tags_alternate_background_color" type="text" id="global_site_tags_alternate_background_color" value="<?php echo esc_attr( $global_site_tags_alternate_background_color ) ?>" size="20">
					<br><?php _e( 'Standard', 'postindexer' ) ?>: #FFFFFF
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Rahmenfarbe', 'postindexer' ) ?></th>
				<td>
					<input name="global_site_tags_border_color" type="text" id="global_site_tags_border_color" value="<?php echo ( $global_site_tags_border_color ) ?>" size="20">
					<br><?php _e( 'Standard', 'postindexer' ) ?>: #CFD0CB
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Ausgeschlossene Tags', 'postindexer' ) ?></th>
				<td>
					<input name="global_site_tags_banned_tags" type="text" id="global_site_tags_banned_tags" value="<?php echo esc_attr( $global_site_tags_banned_tags ) ?>" style="width:95%">
					<br><?php _e( 'Ausgeschlossene Tags werden nicht in der Tag-Cloud angezeigt. Bitte mit Komma trennen. Z.B.: tag1, tag2, tag3', 'postindexer' ) ?>
				</td>
			</tr>
			<tr valign="top">
				<th width="33%" scope="row"><?php _e( 'Beitragstyp auflisten', 'postindexer' ) ?></th>
				<td>
					<select name="global_site_tags_post_type" id="global_site_tags_post_type">
						<option value="all"><?php _e( 'alle', 'postindexer' ) ?></option>
						<?php foreach ( $post_types as $r ) : ?>
						<option value="<?php echo esc_attr( $r ) ?>"<?php selected( $r, $global_site_tags_post_type ) ?>><?php esc_html_e( $r, 'postindexer' ) ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table><?php
	}

	function global_site_tags_get_post_types() {
		return (array)$this->db->get_col( "SELECT post_type FROM " . $this->db->base_prefix . "network_posts GROUP BY post_type" );
	}

	function global_site_tags_get_taxonomies() {
		return (array)$this->db->get_col( "SELECT taxonomy FROM {$this->db->base_prefix}network_term_taxonomy GROUP BY taxonomy" );
	}

	function global_site_tags_site_admin_options_process() {
		update_site_option( 'global_site_tags_shown', $_POST['global_site_tags_shown'] );
		update_site_option( 'global_site_tags_per_page', $_POST['global_site_tags_per_page'] );
		update_site_option( 'global_site_tags_background_color', trim( $_POST['global_site_tags_background_color'] ) );
		update_site_option( 'global_site_tags_alternate_background_color', trim( $_POST['global_site_tags_alternate_background_color'] ) );
		update_site_option( 'global_site_tags_border_color', trim( $_POST['global_site_tags_border_color'] ) );
		update_site_option( 'global_site_tags_banned_tags', trim( $_POST['global_site_tags_banned_tags'] ) );
		update_site_option( 'global_site_tags_post_type', $_POST['global_site_tags_post_type'] );
	}

	function global_site_tags_tag_cloud( $content, $number, $smallest, $largest, $cloud_banned_tags = false, $global_site_tags_post_type = 'post' ) {
		global $wpdb;

		$global_site_tags_banned_tags = get_site_option( 'global_site_tags_banned_tags', 'uncategorized' );

		$banned_tags = array_map( 'trim', explode( ',', $global_site_tags_banned_tags ) );
		if ( is_array( $cloud_banned_tags ) ) {
			$banned_tags = array_merge( $cloud_banned_tags, $banned_tags );
		}

		$base_url = trailingslashit( trailingslashit( home_url() ) . $this->global_site_tags_base );
		if ( GLOBAL_SITE_TAGS_BLOG != get_current_blog_id() ) {
			switch_to_blog( GLOBAL_SITE_TAGS_BLOG );
			$base_url = trailingslashit( trailingslashit( home_url() ) . $this->global_site_tags_base );
			restore_current_blog();
		}

		$query = "
			SELECT COUNT(*) as 'count',
			       t.term_id,
				   t.term_id as id,
				   t.name,
				   t.slug,
				   t.term_group,
				   tt.term_taxonomy_id,
				   tt.taxonomy,
				   tt.description,
				   tt.parent,
				   CONCAT('{$base_url}', t.slug) as 'link'
			  FROM {$this->db->base_prefix}network_terms as t
			 INNER JOIN {$this->db->base_prefix}network_term_taxonomy AS tt ON t.term_id = tt.term_id
			 INNER JOIN {$this->db->base_prefix}network_term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 INNER JOIN {$this->db->base_prefix}network_posts AS np ON np.ID = tr.object_id AND np.BLOG_ID = tr.blog_id
			 WHERE tt.taxonomy = 'post_tag'";

		if ( !empty( $banned_tags ) ) {
			$banned_tags = implode( "', '", array_map( 'esc_sql', array_unique( array_filter( array_map( 'trim', $banned_tags ) ) ) ) );
			$query .= " AND t.name NOT IN ('{$banned_tags}') ";
		}

		if ( $global_site_tags_post_type != 'all' ) {
			$query .= " AND np.post_type = '{$global_site_tags_post_type}'";
		}

		$query .= " GROUP BY t.term_id ORDER BY 'count' DESC LIMIT " . $number;
		$thetags = $wpdb->get_results( $query );
		$content .= !empty( $thetags )
			? wp_generate_tag_cloud( $thetags, array( 'smallest' => $smallest, 'largest' => $largest, 'unit' => 'px', 'number' => $number, 'orderby' => 'count', 'order' => 'DESC' ) )
			: '<p style="text-align:center">' . __( "Es gibt keine Tags zum Anzeigen.", "postindexer" ) . '</p>';

		return '<div class="tagcloud">' . $content . '</div>';
	}

	//------------------------------------------------------------------------//
	//---Output Functions-----------------------------------------------------//
	//------------------------------------------------------------------------//

	function global_site_tags_title_output( $title, $post_ID = '' ) {
		global $wpdb, $current_site, $post, $wp_query;

		if ( isset( $wp_query->query_vars['namespace'] ) && $wp_query->query_vars['namespace'] == 'gst' && $wp_query->query_vars['type'] == 'tag' && !empty( $wp_query->query_vars['tag'] ) ) {
			$page_id = get_option( 'global_site_tags_page' );
			if ( ( !empty( $page_id ) && $page_id == $post_ID ) || ( !empty( $post ) && $post->ID == $post_ID ) ) {
				$tag_name = esc_sql( urldecode( $wp_query->query_vars['tag'] ) );
				$tag_name = $wpdb->get_var( "SELECT name FROM {$wpdb->base_prefix}network_terms WHERE slug = '{$tag_name}'" );

				$base_url = $current_site->domain . $current_site->path . $this->global_site_tags_base;
				$title = '<a href="http://' . $base_url . '/">' . $title . '</a> &raquo; <a href="http://' . $base_url . '/' . $wp_query->query_vars['tag'] . '/">' . $tag_name . '</a>';
			}
		}

		return $title;
	}

	function global_site_tags_output( $content ) {
		global $wpdb, $current_site, $post, $global_site_tags_base, $members_directory_base, $network_query, $network_post, $wp_query;

		if ( !isset( $wp_query->query_vars['namespace'] ) || $wp_query->query_vars['namespace'] != 'gst' || $wp_query->query_vars['type'] != 'tag' ) {
			return $content;
		}

		$global_site_tags_shown = get_site_option( 'global_site_tags_shown', 50 );
		$global_site_tags_per_page = get_site_option( 'global_site_tags_per_page', 10 );
		$global_site_tags_background_color = get_site_option( 'global_site_tags_background_color', '#F2F2EA' );
		$global_site_tags_alternate_background_color = get_site_option( 'global_site_tags_alternate_background_color', '#FFFFFF' );
		$global_site_tags_border_color = get_site_option( 'global_site_tags_border_color', '#CFD0CB' );
		$global_site_tags_post_type = get_site_option( 'global_site_tags_post_type', 'post' );

		if ( empty( $wp_query->query_vars['tag'] ) ) {
			return $content . $this->global_site_tags_tag_cloud( $content, $global_site_tags_shown, 14, 52, '', $global_site_tags_post_type );
		}

		// Show the results list for the tag
		//=====================================//

		// Set the page number
		network_query_posts( array(
			'posts_per_page' => absint( $global_site_tags_per_page ),
			'paged'          => isset( $wp_query->query_vars['paged'] ) && $wp_query->query_vars['paged'] > 1 ? $wp_query->query_vars['paged'] : 1,
			'tag'            => urldecode( $wp_query->query_vars['tag'] ),
			'post_type'      => $global_site_tags_post_type != 'all'
				? $global_site_tags_post_type
				: $this->global_site_tags_get_post_types(),
		) );

		if ( !network_have_posts() ) {
			$content .= '<p style="text-align:center">';
			$content .= __( 'Nichts gefunden für Suchbegriff(e).', 'postindexer' );
			$content .= '</p>';

			return $content;
		}

		if ( isset( $GLOBALS['network_query']->found_posts ) && $GLOBALS['network_query']->found_posts > absint( $global_site_tags_per_page ) ) {
			$navigation_content = $this->new_pagination( $GLOBALS['network_query'], $current_site->path . $this->global_site_tags_base . '/' . urlencode( $wp_query->query_vars['tag'] ) );
		}

		if ( isset( $navigation_content ) ) {
			$content .= $navigation_content;
		}

		$content .= '<div style="float:left;width:100%">';
		$content .= '<table border="0" width="100%" bgcolor="">';
		$content .= '<tr>';
		$content .= '<td style="background-color:' . $global_site_tags_background_color . '; border-bottom-style:solid; border-bottom-color:' . $global_site_tags_border_color . '; border-bottom-width:1px; font-size:12px;" width="10%"> </td>';
		$content .= '<td style="background-color:' . $global_site_tags_background_color . '; border-bottom-style:solid; border-bottom-color:' . $global_site_tags_border_color . '; border-bottom-width:1px; font-size:12px;" width="90%"><center><strong>' . __( 'Beiträge', 'postindexer' ) . '</strong></center></td>';
		$content .= '</tr>';

		// Search results

		$members_directory_site_admin_options_exists = function_exists( 'members_directory_site_admin_options' );
		$avatar_default = get_option( 'avatar_default' );
		$tic_toc = 'toc';

		while ( network_have_posts() ) {
			network_the_post();

			//=============================//
			$author_id = network_get_the_author_id();
			$the_author = get_user_by( 'id', $author_id );
			$post_author_display_name = $the_author ? $the_author->display_name : __( 'Unbekannt', 'postindexer' );

			$tic_toc = ($tic_toc == 'toc') ? 'tic' : 'toc';
			$bg_color = ($tic_toc == 'tic') ? $global_site_tags_alternate_background_color : $global_site_tags_background_color;

			//=============================//
			$content .= '<tr>';
				$content .= '<td style="background-color:' . $bg_color . ';padding-top:10px;text-align:center;" valign="top" width="10%"><a style="text-decoration:none;" href="' . network_get_permalink() . '">' . get_avatar( $author_id, 32, $avatar_default ) . '</a></td>';
				$content .= '<td style="background-color:' . $bg_color . ';padding-top:10px;vertical-align:top;text-align:left;" width="90%" valign="top">';
					$content .= '<div>';
						$content .= $members_directory_site_admin_options_exists
							? '<strong><a style="text-decoration:none;" href="http://' . $current_site->domain . $current_site->path . $members_directory_base . '/' . $the_author->user_nicename . '/">' . $post_author_display_name . '</a> ' . __( ' schrieb', 'postindexer' ) . ': </strong> '
							: '<strong>' . sprintf( _x( '%s schrieb', '{author name} schrieb', 'postindexer' ), $post_author_display_name ) . ': </strong> ';
					$content .= '<strong><a style="text-decoration:none;" href="' . network_get_permalink() . '">' . network_get_the_title() . '</a></strong></div>';
					$content .= substr( strip_tags( network_get_the_content() ), 0, 250 ) . ' (<a href="' . network_get_permalink() . '">' . __( 'Mehr', 'postindexer' ) . '</a>)';
				$content .= '</td>';
			$content .= '</tr>';
		}

		$content .= '</table>';
		$content .= '</div>';

		if ( isset( $navigation_content ) ) {
			$content .= $navigation_content;
		}

		return $content;

	}

	function new_pagination( $wp_query, $mainlink = '' ) {
		if ( $wp_query->max_num_pages > 1 ) {
			// we can draw the pages
			return '<div class="gssnav">' . paginate_links( array(
				'base'      => trailingslashit( $mainlink ) . '%_%',
				'format'    => 'page/%#%',
				'total'     => $wp_query->max_num_pages,
				'current'   => !empty( $wp_query->query_vars['paged'] ) ? $wp_query->query_vars['paged'] : 1,
				'prev_next' => true
			) ) . '</div>';
		}
	}

	//------------------------------------------------------------------------//
	//---Page Output Functions------------------------------------------------//
	//------------------------------------------------------------------------//

	//------------------------------------------------------------------------//
	//---Support Functions----------------------------------------------------//
	//------------------------------------------------------------------------//

	function global_site_tags_roundup( $value, $dp ) {
		return ceil( $value * pow( 10, $dp ) ) / pow( 10, $dp );
	}

	// Setup erzwingen (z.B. nach Aktivierung im Netzwerk-Admin)
	public function force_setup() {
		delete_option('global_site_tags_page_setup');
		delete_option('global_site_tags_page');
		delete_option('gst_installed');
		$this->initialise_plugin();
	}
}

// Integration als Erweiterung für den Beitragsindexer
add_action('plugins_loaded', function() {
	if ( !class_exists('Postindexer_Extensions_Admin') ) return;
	global $postindexer_extensions_admin;
	if ( !isset($postindexer_extensions_admin) ) {
		if ( isset($GLOBALS['postindexeradmin']) && isset($GLOBALS['postindexeradmin']->extensions_admin) ) {
			$postindexer_extensions_admin = $GLOBALS['postindexeradmin']->extensions_admin;
		}
	}
	if ( isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('global_site_tags') ) {
		if (class_exists('globalsitetags')) {
			global $globalsitetags;
			$globalsitetags = new globalsitetags();
			// Widget-Registrierung in widgets_init verschieben!
			add_action('widgets_init', function() {
				require_once __DIR__ . '/widget-global-site-tags.php';
				if (!is_network_admin()) {
					register_widget('widget_global_site_tags');
				}
			});
		}
	}
});

// Settings-Renderer für Netzwerk-Admin (immer deklarieren)
if (!class_exists('Global_Site_Tags_Settings_Renderer')) {
    class Global_Site_Tags_Settings_Renderer extends globalsitetags {
        public function __construct() {
            if (!class_exists('globalsitetags')) {
                throw new \Exception('Basisklasse globalsitetags nicht gefunden!');
            }
            parent::__construct();
        }
        public function render_settings_form() {
            $shown = get_site_option( 'global_site_tags_shown', '50' );
            $per_page = get_site_option( 'global_site_tags_per_page', '10' );
            $background = get_site_option( 'global_site_tags_background_color', '#F2F2EA' );
            $alt_background = get_site_option( 'global_site_tags_alternate_background_color', '#FFFFFF' );
            $border = get_site_option( 'global_site_tags_border_color', '#CFD0CB' );
            $banned = get_site_option( 'global_site_tags_banned_tags', 'uncategorized' );
            $post_type = get_site_option( 'global_site_tags_post_type', 'post' );
            $post_types = $this->global_site_tags_get_post_types();
            ob_start();
            // KEIN <form> mehr, nur noch die Felder!
            wp_nonce_field('ps_extension_settings_save_global_site_tags', 'ps_extension_settings_nonce_global_site_tags');
            echo '<div style="background:#fff;border:1px solid #e5e5e5;padding:2em 2em 1em 2em;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:2em;">';
            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2em;">';
            // Tags Shown
            echo '<div><label for="gst_shown" style="font-weight:bold;">Anzahl angezeigter Tags</label><br>';
            echo '<select name="global_site_tags_shown" id="gst_shown" style="min-width:120px;">';
            for ( $i = 5; $i <= 50; $i += 5 ) {
                echo '<option value="'.$i.'"'.selected($shown,$i,false).'>'.$i.'</option>';
            }
            echo '</select></div>';
            // Auflistung pro Seite
            echo '<div><label for="gst_per_page" style="font-weight:bold;">Auflistung pro Seite</label><br>';
            echo '<select name="global_site_tags_per_page" id="gst_per_page" style="min-width:120px;">';
            for ( $i = 5; $i <= 50; $i += 5 ) {
                echo '<option value="'.$i.'"'.selected($per_page,$i,false).'>'.$i.'</option>';
            }
            echo '</select></div>';
            // Hintergrundfarbe
            echo '<div><label for="gst_bg" style="font-weight:bold;">Hintergrundfarbe</label><br>';
            echo '<input name="global_site_tags_background_color" type="text" id="gst_bg" value="'.esc_attr($background).'" size="20"> <span style="color:#888;font-size:0.95em;">Standard: #F2F2EA</span></div>';
            // Alternative Hintergrundfarbe
            echo '<div><label for="gst_alt_bg" style="font-weight:bold;">Alternative Hintergrundfarbe</label><br>';
            echo '<input name="global_site_tags_alternate_background_color" type="text" id="gst_alt_bg" value="'.esc_attr($alt_background).'" size="20"> <span style="color:#888;font-size:0.95em;">Standard: #FFFFFF</span></div>';
            // Rahmenfarbe
            echo '<div><label for="gst_border" style="font-weight:bold;">Rahmenfarbe</label><br>';
            echo '<input name="global_site_tags_border_color" type="text" id="gst_border" value="'.esc_attr($border).'" size="20"> <span style="color:#888;font-size:0.95em;">Standard: #CFD0CB</span></div>';
            // Banned Tags
            echo '<div style="grid-column:1/3;"><label for="gst_banned" style="font-weight:bold;">Ausgeschlossene Tags</label><br>';
            echo '<input name="global_site_tags_banned_tags" type="text" id="gst_banned" value="'.esc_attr($banned).'" style="width:95%"> <span style="color:#888;font-size:0.95em;">Mit Komma trennen, z.B. uncategorized,foo,bar</span></div>';
            // Beitragstyp
            echo '<div style="grid-column:1/3;"><label for="gst_post_type" style="font-weight:bold;">Beitragstyp auflisten</label><br>';
            echo '<select name="global_site_tags_post_type" id="gst_post_type" style="min-width:160px;">';
            echo '<option value="all"'.selected($post_type,'all',false).'>alle</option>';
            foreach ($post_types as $r) {
                echo '<option value="'.esc_attr($r).'"'.selected($post_type,$r,false).'>'.esc_html($r).'</option>';
            }
            echo '</select></div>';
            echo '</div>';
            echo '</div>';
            // KEIN Button, KEIN eigenes <form> mehr!
            return ob_get_clean();
        }
        /**
         * Speichert die Einstellungen aus dem Card-Formular (klassischer POST, kein AJAX)
         */
        public function process_settings_form() {
            if (
                isset($_POST['global_site_tags_shown']) &&
                isset($_POST['global_site_tags_per_page']) &&
                isset($_POST['global_site_tags_background_color']) &&
                isset($_POST['global_site_tags_alternate_background_color']) &&
                isset($_POST['global_site_tags_border_color']) &&
                isset($_POST['global_site_tags_banned_tags']) &&
                isset($_POST['global_site_tags_post_type']) &&
                isset($_POST['ps_extension_settings_nonce_global_site_tags']) &&
                wp_verify_nonce($_POST['ps_extension_settings_nonce_global_site_tags'], 'ps_extension_settings_save_global_site_tags')
            ) {
                update_site_option('global_site_tags_shown', intval($_POST['global_site_tags_shown']));
                update_site_option('global_site_tags_per_page', intval($_POST['global_site_tags_per_page']));
                update_site_option('global_site_tags_background_color', trim($_POST['global_site_tags_background_color']));
                update_site_option('global_site_tags_alternate_background_color', trim($_POST['global_site_tags_alternate_background_color']));
                update_site_option('global_site_tags_border_color', trim($_POST['global_site_tags_border_color']));
                update_site_option('global_site_tags_banned_tags', trim($_POST['global_site_tags_banned_tags']));
                update_site_option('global_site_tags_post_type', sanitize_text_field($_POST['global_site_tags_post_type']));
            }
        }
    }
}