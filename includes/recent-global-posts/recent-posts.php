<?php

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
		add_action( 'admin_notices', [ $this, 'check_indexer_plugin' ] );
	}

	public function check_indexer_plugin() {
		if ( ! function_exists( 'network_query_posts' ) ) {
			echo '<div class="notice notice-error"><p><strong>Indexer-Plugin nicht aktiv!</strong> Das Plugin "Multisite Index" wird benötigt, damit [recent_network_posts] funktioniert.<br>Weitere Informationen und Download: <a href="https://cp-psource.github.io/ps-postindexer/" target="_blank" rel="noopener noreferrer">https://cp-psource.github.io/ps-postindexer/</a></p></div>';
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
		$options = get_site_option( 'network_posts_defaults', [] );

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
			return '<p>' . __('Indexer-Plugin nicht aktiv. Keine Beiträge verfügbar.', 'postindexer') . '</p>';
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

			$html .= '<div class="content">'
				. '<h3><a href="' . esc_url( $post['url'] ) . '">' . esc_html( $post['title'] ) . '</a></h3>'
				. '<p>' . esc_html( $post['excerpt'] ) . '</p>';

            // Blogname anzeigen
            if ( $args['show_blog'] === 'yes' && !empty($post['blogname']) ) {
                $html .= '<div class="blogname">' . esc_html( $post['blogname'] ) . '</div>';
            }
            // Autor anzeigen
            if ( $args['show_author'] === 'yes' && !empty($post['author']) ) {
                $html .= '<div class="author">' . esc_html( $post['author'] ) . '</div>';
            }
            // Weiterlesen-Link
            $read_more = $args['read_more_text'] !== '' ? $args['read_more_text'] : __('Weiterlesen', 'postindexer');
            $html .= '<a class="read-more" href="' . esc_url( $post['url'] ) . '">' . esc_html( $read_more ) . '</a>';

            $html .= '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	public function render_settings_form() {
		ob_start();
		$options = get_site_option('network_posts_defaults', []);
		// KEIN <form> mehr, nur noch die Felder!
		wp_nonce_field('ps_extension_settings_save_recent_network_posts', 'ps_extension_settings_nonce_recent_network_posts');
		echo '<div class="network-posts-settings-fields-wrapper" style="background:#fff;border:1px solid #e5e5e5;padding:2em 2em 1em 2em;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:2em;">';
		echo '<div class="network-posts-settings-fields" style="display:grid;grid-template-columns:1fr 1fr;gap:2em;">';
		// Anzahl Beiträge
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Anzahl Beiträge</legend>'
			. '<input type="number" name="network_posts_defaults[number]" value="' . esc_attr( $options['number'] ?? 5 ) . '" min="1" max="20" style="width:80px;">'
			. '<span style="color:#666;font-size:0.95em;">Wie viele Beiträge sollen angezeigt werden?</span>'
			. '</fieldset>';
		// Layout
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Layout</legend>'
			. '<select name="network_posts_defaults[layout]" style="min-width:120px;">'
			. '<option value="card"' . selected( $options['layout'] ?? 'card', 'card', false ) . '>Card</option>'
			. '<option value="grid"' . selected( $options['layout'] ?? 'card', 'grid', false ) . '>Grid</option>'
			. '</select>'
			. '<span style="color:#666;font-size:0.95em;">Darstellung der Beitragsliste</span>'
			. '</fieldset>';
		// Beitragsbild anzeigen
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Beitragsbild anzeigen</legend>'
			. '<label><input type="checkbox" name="network_posts_defaults[show_thumb]" value="yes" ' . checked( $options['show_thumb'] ?? 'yes', 'yes', false ) . '> Ja</label>'
			. '<span style="color:#666;font-size:0.95em;">Beitragsbild anzeigen?</span>'
			. '</fieldset>';
		// Beitragsbild-Größe
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Beitragsbild-Größe</legend>'
			. '<select name="network_posts_defaults[thumb_size]" style="min-width:120px;">'
			. '<option value="thumbnail"' . selected( $options['thumb_size'] ?? 'medium', 'thumbnail', false ) . '>Thumbnail</option>'
			. '<option value="medium"' . selected( $options['thumb_size'] ?? 'medium', 'medium', false ) . '>Medium</option>'
			. '<option value="large"' . selected( $options['thumb_size'] ?? 'medium', 'large', false ) . '>Large</option>'
			. '</select>'
			. '<span style="color:#666;font-size:0.95em;">Größe des Beitragsbilds</span>'
			. '</fieldset>';
		// Autor anzeigen
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Autor anzeigen</legend>'
			. '<label><input type="checkbox" name="network_posts_defaults[show_author]" value="yes" ' . checked( $options['show_author'] ?? 'yes', 'yes', false ) . '> Ja</label>'
			. '<span style="color:#666;font-size:0.95em;">Autor anzeigen?</span>'
			. '</fieldset>';
		// Blogname anzeigen
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Blogname anzeigen</legend>'
			. '<label><input type="checkbox" name="network_posts_defaults[show_blog]" value="yes" ' . checked( $options['show_blog'] ?? 'yes', 'yes', false ) . '> Ja</label>'
			. '<span style="color:#666;font-size:0.95em;">Blogname anzeigen?</span>'
			. '</fieldset>';
		// Länge Auszug
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Länge Auszug (Zeichen)</legend>'
			. '<input type="number" name="network_posts_defaults[excerpt_length]" value="' . esc_attr( $options['excerpt_length'] ?? 200 ) . '" min="10" max="500" style="width:80px;">'
			. '<span style="color:#666;font-size:0.95em;">Maximale Zeichenanzahl für den Auszug</span>'
			. '</fieldset>';
		// Weiterlesen-Text
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">"Weiterlesen"-Text</legend>'
			. '<input type="text" name="network_posts_defaults[read_more_text]" value="' . esc_attr( $options['read_more_text'] ?? 'Weiterlesen' ) . '" style="min-width:180px;">'
			. '<span style="color:#666;font-size:0.95em;">Text für den Weiterlesen-Link</span>'
			. '</fieldset>';
		// Sortierung
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Sortierung</legend>'
			. '<select name="network_posts_defaults[sort_order]" style="min-width:160px;">'
			. '<option value="date"' . selected( $options['sort_order'] ?? 'date', 'date', false ) . '>Veröffentlichungsdatum (neueste zuerst)</option>'
			. '<option value="modified"' . selected( $options['sort_order'] ?? 'date', 'modified', false ) . '>Zuletzt bearbeitet</option>'
			. '<option value="title"' . selected( $options['sort_order'] ?? 'date', 'title', false ) . '>Alphabetisch (A-Z)</option>'
			. '<option value="rand"' . selected( $options['sort_order'] ?? 'date', 'rand', false ) . '>Zufällig</option>'
			. '</select>'
			. '<span style="color:#666;font-size:0.95em;">Bestimmt die Sortierung der Beiträge</span>'
			. '</fieldset>';
		// Paginierung
		echo '<fieldset class="network-posts-setting-row" style="display:flex;align-items:center;gap:1em;border:1px solid #e5e5e5;padding:0.7em 1em 0.7em 1em;border-radius:6px;background:#fcfcfc;margin-bottom:0;">'
			. '<legend style="font-weight:bold;font-size:1em;margin-right:1em;">Paginierung</legend>'
			. '<label><input type="checkbox" name="network_posts_defaults[pagination]" value="yes" ' . checked( $options['pagination'] ?? 'no', 'yes', false ) . '> Ja</label>'
			. '<span style="color:#666;font-size:0.95em;">Beiträge werden nach der eingestellten Anzahl pro Seite paginiert</span>'
			. '</fieldset>';
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
            isset($_POST['network_posts_defaults']) &&
            is_array($_POST['network_posts_defaults']) &&
            isset($_POST['ps_extension_settings_nonce_recent_network_posts']) &&
            wp_verify_nonce($_POST['ps_extension_settings_nonce_recent_network_posts'], 'ps_extension_settings_save_recent_network_posts')
        ) {
            update_site_option('network_posts_defaults', $_POST['network_posts_defaults']);
        }
    }
}
//delete_option( 'network_posts_defaults' ); // ENTFERNT! Instanziierung nur noch über plugins_loaded bei aktiver Erweiterung

// Nach dem Speichern: update_site_option statt update_option
add_action('updated_option', function($option, $old, $new) {
    if ($option === 'network_posts_defaults' && is_multisite()) {
        update_site_option('network_posts_defaults', $new);
    }
}, 10, 3);

// Netzwerkweite Einstellungen speichern
add_action('network_admin_edit_network_posts_options', function() {
    if (isset($_POST['network_posts_defaults'])) {
        check_admin_referer('network_posts_options-options');
        update_site_option('network_posts_defaults', $_POST['network_posts_defaults']);
    }
});

// AJAX-Handler für das Speichern der Netzwerk-Optionen
add_action('wp_ajax_save_network_posts_settings', function() {
	if (!current_user_can('manage_network_options')) wp_send_json_error('Fehlende Berechtigung: manage_network_options');
	if (!isset($_POST['network_posts_options_ajax_nonce'])) wp_send_json_error('Nonce fehlt');
	if (!wp_verify_nonce($_POST['network_posts_options_ajax_nonce'], 'network_posts_options-ajax')) wp_send_json_error('Nonce ungültig');
	if (!isset($_POST['network_posts_defaults']) || !is_array($_POST['network_posts_defaults'])) wp_send_json_error('Keine Daten erhalten');
	update_site_option('network_posts_defaults', $_POST['network_posts_defaults']);
	wp_send_json_success();
});

// jQuery im Netzwerk-Admin laden, damit AJAX funktioniert
add_action('admin_enqueue_scripts', function($hook) {
    if (is_network_admin() && $hook === 'ps-multisite-index_page_ps-multisite-index-extensions') {
        wp_enqueue_script('jquery');
        $ajax_url = admin_url('admin-ajax.php');
        wp_add_inline_script('jquery', "
            jQuery(document).on('submit', '#network-posts-settings-form', function(e) {
                console.log('AJAX submit!');
                e.preventDefault();
                var form = jQuery(this);
                var data = form.serialize();
                data += '&action=save_network_posts_settings';
                jQuery.post('" . esc_js($ajax_url) . "', data, function(response){
                    if(response.success){
                        jQuery('#network-posts-settings-success').show().delay(2000).fadeOut();
                    }else{
                        alert('Fehler beim Speichern: '+(response.data||'Unbekannter Fehler'));
                    }
                });
            });
        ");
    }
});
