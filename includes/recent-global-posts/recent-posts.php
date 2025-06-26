<?php
/*
Plugin Name: Aktuelle Netzwerkbeiträge
Plugin URI: https://cp-psource.github.io/recent-global-posts/
Description: Zeige eine anpassbare Liste der letzten Beiträge aus Deinem Multisite-Netzwerk auf Deiner Hauptseite an.
Author: PSOURCE
Version: 3.1.1
Author URI: https://github.com/cp-psource
*/

// +----------------------------------------------------------------------+
// | Copyright PSOURCE (https://github.com/cp-psource)                                |
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


if ( ! defined( 'ABSPATH' ) ) exit;

// Integration als Erweiterung für den Beitragsindexer
add_action('plugins_loaded', function() {
	if ( !class_exists('Postindexer_Extensions_Admin') ) return;
	global $postindexer_extensions_admin;
	if ( !isset($postindexer_extensions_admin) ) {
		// Fallback: Instanz suchen (z.B. aus Mainklasse)
		if ( isset($GLOBALS['postindexeradmin']) && isset($GLOBALS['postindexeradmin']->extensions_admin) ) {
			$postindexer_extensions_admin = $GLOBALS['postindexeradmin']->extensions_admin;
		}
	}
	if ( isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('recent_network_posts') ) {
		new Recent_Network_Posts();
	}
});

class Recent_Network_Posts {

	public function __construct() {
		add_shortcode( 'recent_network_posts', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ], 99 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'check_indexer_plugin' ] );
	}

	public function check_indexer_plugin() {
		if ( ! function_exists( 'network_query_posts' ) ) {
			echo '<div class="notice notice-error"><p><strong>Indexer-Plugin nicht aktiv!</strong> Das Plugin "Multisite Beitragsindex" wird benötigt, damit [recent_network_posts] funktioniert.<br>Weitere Informationen und Download: <a href="https://cp-psource.github.io/ps-postindexer/" target="_blank" rel="noopener noreferrer">https://cp-psource.github.io/ps-postindexer/</a></p></div>';
		}
	}

	public function enqueue_styles() {
		wp_register_style( 'recent-network-posts-style', false );
		wp_enqueue_style( 'recent-network-posts-style' );
		wp_add_inline_style( 'recent-network-posts-style', $this->get_inline_css() );
	}

	private function get_inline_css() {
		return <<<CSS
		.network-posts {
		display: flex;
		flex-direction: column;
		gap: 2rem;
		margin: 2rem 0;
	}

	.network-posts.layout-grid {
		all: initial;
		display: grid !important;
		grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
		gap: 2rem !important;
	}

	.network-post {
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 12px;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		box-shadow: 0 2px 8px rgba(0,0,0,0.05);
		transition: transform 0.2s ease;
	}

	.network-post:hover {
		transform: translateY(-4px);
	}

	.network-post .thumb img {
		width: 100%;
		height: auto;
		display: block;
	}

	.network-post .content {
		padding: 1rem;
	}

	.network-post h3 {
		margin-top: 0;
		font-size: 1.2rem;
	}

	.network-post p {
		margin: 0.5rem 0;
		font-size: 0.95rem;
		color: #444;
	}

	.network-post .blogname {
		font-size: 0.85rem;
		color: #888;
	}

	.network-post .read-more {
		display: inline-block;
		margin-top: 1rem;
		font-weight: bold;
		text-decoration: none;
		color: #005f99;
	}

	.network-post .read-more:hover {
		text-decoration: underline;
	}
	CSS;
	}

	public function render_shortcode( $atts ) {
		$options = get_option( 'network_posts_defaults', [] );

		$args = shortcode_atts( [
			'number'           => $options['number'] ?? 5,
			'posttype'         => 'post',
			'show_thumb'       => $options['show_thumb'] ?? 'yes',
			'thumb_size'       => $options['thumb_size'] ?? 'medium',
			'show_author'      => $options['show_author'] ?? 'yes',
			'show_blog'        => $options['show_blog'] ?? 'yes',
			'excerpt_length'   => $options['excerpt_length'] ?? 200,
			'read_more_text'   => $options['read_more_text'] ?? '',
			'layout'           => isset( $options['layout'] ) ? sanitize_key( $options['layout'] ) : 'card',
			'sort_order'       => $options['sort_order'] ?? 'date',
			'pagination'       => $options['pagination'] ?? 'no',
		], $atts );

		return $this->get_recent_posts( $args );
	}

	private function get_recent_posts( array $args ): string {
		if ( ! function_exists( 'network_query_posts' ) ) {
			return '<p>Indexer-Plugin nicht aktiv. Keine Beiträge verfügbar.</p>';
		}

		$html = '';
		$posts = [];

		// Paginierung
		$paged = 1;
		if ( $args['pagination'] === 'yes' ) {
			$paged = isset( $_GET['rnp_page'] ) ? max( 1, intval( $_GET['rnp_page'] ) ) : 1;
		}

		// Query-Args für Sortierung und Paginierung
		$query_args = [
			'post_type'      => $args['posttype'],
			'posts_per_page' => $args['number'],
			'post_status'    => 'publish',
			'paged'          => $paged,
		];

		switch ( $args['sort_order'] ) {
			case 'modified':
				$query_args['orderby'] = 'modified';
				$query_args['order'] = 'DESC';
				break;
			case 'title':
				$query_args['orderby'] = 'title';
				$query_args['order'] = 'ASC';
				break;
			case 'rand':
				$query_args['orderby'] = 'rand';
				break;
			case 'date':
			default:
				$query_args['orderby'] = 'date';
				$query_args['order'] = 'DESC';
		}

		network_query_posts( $query_args );

		global $network_post, $wp_query;
		$posts = [];

		while ( network_have_posts() ) {
			network_the_post();

			$blog_id = $network_post->BLOG_ID ?? get_current_blog_id();
			$post_id = $network_post->ID;

			switch_to_blog( $blog_id );

			$title   = network_get_the_title();
			$url     = network_get_permalink();
			$content = network_get_the_content();
			$author  = get_the_author_meta( 'display_name' );
			$blogname = get_bloginfo( 'name' );

			$excerpt_length = intval( $args['excerpt_length'] ?? 200 );
			$content_stripped = wp_strip_all_tags( $content );
			if ( mb_strlen( $content_stripped ) > $excerpt_length ) {
				$excerpt = mb_substr( $content_stripped, 0, $excerpt_length ) . '...';
			} else {
				$excerpt = $content_stripped;
			}

			$thumb_html = '';
			if ( $args['show_thumb'] === 'yes' ) {
				$thumb_id = get_post_thumbnail_id( $post_id );
				error_log( "[{$blog_id} - {$post_id}] Thumbnail-ID: " . $thumb_id );
				if ( $thumb_id ) {
					$thumb_html = wp_get_attachment_image( $thumb_id, $args['thumb_size'] ?? 'medium' );
				}
			}

			restore_current_blog();

			$posts[] = [
				'title'    => $title,
				'url'      => $url,
				'excerpt'  => $excerpt,
				'thumb'    => $thumb_html,
				'blogname' => $blogname,
				'author'   => $author,
				'ID'       => $post_id,
				'blog_id'  => $blog_id,
			];
		}

		// Nur für fallback: falls network_query_posts keine Sortierung kann
		if ( $args['sort_order'] === 'title' ) {
			usort( $posts, function( $a, $b ) {
				return strcmp( $a['title'], $b['title'] );
			});
		} elseif ( $args['sort_order'] === 'rand' ) {
			shuffle( $posts );
		}

		$layout_class = 'layout-' . sanitize_html_class( sanitize_key( $args['layout'] ) );
		$html = '<div class="network-posts ' . esc_attr( $layout_class ) . '">';

		foreach ( $posts as $post ) {
			$html .= '<div class="network-post">';

			if ( ! empty( $post['thumb'] ) ) {
				$html .= '<div class="thumb">' . $post['thumb'] . '</div>';
			}

			$html .= '<div class="content">
				<h3><a href="' . esc_url( $post['url'] ) . '">' . esc_html( $post['title'] ) . '</a></h3>
				<p>' . esc_html( $post['excerpt'] ) . '</p>';
			$html .= '</div>';
		}

		return $html;
	}

	public function register_settings() {
		register_setting( 'network_posts_options', 'network_posts_defaults' );

		add_settings_section(
			'network_posts_main',
			'',
			null,
			'network-posts-settings'
		);

		// Anzahl Beiträge
		add_settings_field(
			'number',
			'Anzahl Beiträge',
			function() {
				$options = get_option( 'network_posts_defaults' );
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Anzahl Beiträge</legend>'
					. '<input type="number" name="network_posts_defaults[number]" value="' . esc_attr( $options['number'] ?? 5 ) . '" min="1" max="20" style="width:80px;">'
					. '<span style="color:#666;font-size:0.95em;">Wie viele Beiträge sollen angezeigt werden?</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Layout
		add_settings_field(
			'layout',
			'Layout',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$layout = $options['layout'] ?? 'card';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Layout</legend>'
					. '<select name="network_posts_defaults[layout]" style="min-width:120px;">'
					. '<option value="card"' . selected( $layout, 'card', false ) . '>Card</option>'
					. '<option value="grid"' . selected( $layout, 'grid', false ) . '>Grid</option>'
					. '</select>'
					. '<span style="color:#666;font-size:0.95em;">Darstellung der Beitragsliste</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Beitragsbild anzeigen
		add_settings_field(
			'show_thumb',
			'Beitragsbild anzeigen',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$val = $options['show_thumb'] ?? 'yes';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Beitragsbild anzeigen</legend>'
					. '<label><input type="checkbox" name="network_posts_defaults[show_thumb]" value="yes" ' . checked( $val, 'yes', false ) . '> Ja</label>'
					. '<span style="color:#666;font-size:0.95em;">Beitragsbild anzeigen?</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Beitragsbild-Größe
		add_settings_field(
			'thumb_size',
			'Beitragsbild-Größe',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$size = $options['thumb_size'] ?? 'medium';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Beitragsbild-Größe</legend>'
					. '<select name="network_posts_defaults[thumb_size]" style="min-width:120px;">'
					. '<option value="thumbnail"' . selected( $size, 'thumbnail', false ) . '>Thumbnail</option>'
					. '<option value="medium"' . selected( $size, 'medium', false ) . '>Medium</option>'
					. '<option value="large"' . selected( $size, 'large', false ) . '>Large</option>'
					. '</select>'
					. '<span style="color:#666;font-size:0.95em;">Größe des Beitragsbilds</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Autor anzeigen
		add_settings_field(
			'show_author',
			'Autor anzeigen',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$val = $options['show_author'] ?? 'yes';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Autor anzeigen</legend>'
					. '<label><input type="checkbox" name="network_posts_defaults[show_author]" value="yes" ' . checked( $val, 'yes', false ) . '> Ja</label>'
					. '<span style="color:#666;font-size:0.95em;">Autor anzeigen?</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Blogname anzeigen
		add_settings_field(
			'show_blog',
			'Blogname anzeigen',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$val = $options['show_blog'] ?? 'yes';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Blogname anzeigen</legend>'
					. '<label><input type="checkbox" name="network_posts_defaults[show_blog]" value="yes" ' . checked( $val, 'yes', false ) . '> Ja</label>'
					. '<span style="color:#666;font-size:0.95em;">Blogname anzeigen?</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Länge Auszug (Zeichen)
		add_settings_field(
			'excerpt_length',
			'Länge Auszug (Zeichen)',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$val = $options['excerpt_length'] ?? 200;
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Länge Auszug (Zeichen)</legend>'
					. '<input type="number" name="network_posts_defaults[excerpt_length]" value="' . esc_attr( $val ) . '" min="10" max="500" style="width:80px;">'
					. '<span style="color:#666;font-size:0.95em;">Maximale Zeichenanzahl für den Auszug</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Weiterlesen-Text
		add_settings_field(
			'read_more_text',
			'"Weiterlesen"-Text',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$val = $options['read_more_text'] ?? 'Weiterlesen';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">"Weiterlesen"-Text</legend>'
					. '<input type="text" name="network_posts_defaults[read_more_text]" value="' . esc_attr( $val ) . '" style="min-width:180px;">'
					. '<span style="color:#666;font-size:0.95em;">Text für den Weiterlesen-Link</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Sortierung
		add_settings_field(
			'sort_order',
			'Sortierung',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$sort = $options['sort_order'] ?? 'date';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Sortierung</legend>'
					. '<select name="network_posts_defaults[sort_order]" style="min-width:160px;">'
					. '<option value="date"' . selected( $sort, 'date', false ) . '>Veröffentlichungsdatum (neueste zuerst)</option>'
					. '<option value="modified"' . selected( $sort, 'modified', false ) . '>Zuletzt bearbeitet</option>'
					. '<option value="title"' . selected( $sort, 'title', false ) . '>Alphabetisch (A-Z)</option>'
					. '<option value="rand"' . selected( $sort, 'rand', false ) . '>Zufällig</option>'
					. '</select>'
					. '<span style="color:#666;font-size:0.95em;">Bestimmt die Sortierung der Beiträge</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);

		// Paginierung
		add_settings_field(
			'pagination',
			'Paginierung',
			function() {
				$options = get_option( 'network_posts_defaults' );
				$val = $options['pagination'] ?? 'no';
				echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
					. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Paginierung</legend>'
					. '<label><input type="checkbox" name="network_posts_defaults[pagination]" value="yes" ' . checked( $val, 'yes', false ) . '> Ja</label>'
					. '<span style="color:#666;font-size:0.95em;">Beiträge werden nach der eingestellten Anzahl pro Seite paginiert</span>'
					. '</fieldset>';
			},
			'network-posts-settings',
			'network_posts_main'
		);
	}

	public function render_settings_form() {
		ob_start();
		// Info-Box entfernt, da sie jetzt in der Card steht
		echo '<form method="post" action="options.php" style="max-width:700px;">';
		settings_fields( 'network_posts_options' );
		echo '<div class="network-posts-settings-fields-wrapper" style="background:#fff;border:1px solid #e5e5e5;padding:2em 2em 1em 2em;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:2em;">';
		echo '<div class="network-posts-settings-fields" style="display:grid;grid-template-columns:1fr 1fr;gap:2em;">';
		do_settings_sections( 'network-posts-settings' );
		echo '</div>';
		echo '</div>';
		submit_button();
		echo '</form>';
		return ob_get_clean();
	}
}
//delete_option( 'network_posts_defaults' );
new Recent_Network_Posts();
