<?php

class Global_Site_Search_Widget extends WP_Widget {

	public function __construct() {
		$widget_options = array(
			'classname'   => 'global-site-search',
			'description' => __( 'Netzwerksuche Widget', 'postindexer' ),
		);

		$control_options = array(
			'id_base' => 'global-site-search-widget',
		);

		parent::__construct( 'global-site-search-widget', __( 'Netzwerksuche Widget', 'postindexer' ), $widget_options, $control_options );
	}

	function widget( $args, $instance ) {
		global $global_site_search, $wp_query, $wpdb;

		extract( $args );

		echo $before_widget;

		$title = apply_filters( 'widget_title', $instance['title'] );
		if ( !empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}

		// Suchformular
		$phrase = isset($_GET['gss_widget_phrase']) ? trim(stripslashes($_GET['gss_widget_phrase'])) : '';
		// Widget-Suchformular per AJAX, damit kein Redirect erfolgt
		echo '<form id="gss-widget-form-' . esc_attr($this->id) . '" action="#" method="get">';
		echo '<input type="text" name="gss_widget_phrase" value="' . esc_attr($phrase) . '" placeholder="' . esc_attr__('Suchbegriff...', 'postindexer') . '" style="width:70%;margin-right:0.5em;">';
		echo '<input type="submit" value="' . esc_attr__('Suchen', 'postindexer') . '">';
		echo '</form>';
		echo '<div id="gss-widget-results-' . esc_attr($this->id) . '">';
		if ($phrase !== '') {
			$limit = 5;
			$post_type = get_site_option('global_site_search_post_type', 'post');
			$where = $wpdb->prepare("post_title LIKE %s AND post_type = %s AND post_status = 'publish'", '%' . $wpdb->esc_like($phrase) . '%', $post_type);
			$results = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}network_posts WHERE $where ORDER BY post_date DESC LIMIT $limit");
			if ($results) {
				echo '<ul class="gss-widget-results">';
				foreach ($results as $row) {
					echo '<li><a href="' . esc_url($row->guid) . '">' . esc_html($row->post_title) . '</a></li>';
				}
				echo '</ul>';
				$main_site_url = network_home_url( global_site_search_get_search_base() . '/' . urlencode($phrase) . '/' );
				echo '<div style="margin-top:0.7em;"><a href="' . esc_url($main_site_url) . '" style="font-weight:bold;">' . esc_html__('Weitere Treffer anzeigen', 'postindexer') . '</a></div>';
			} else {
				echo '<div style="margin-top:0.7em;color:#888;">' . esc_html__('Keine Treffer gefunden.', 'postindexer') . '</div>';
			}
		}
		echo '</div>';
		// AJAX-Handler für das Widget
		echo '<script>document.addEventListener("DOMContentLoaded",function(){
            var form=document.getElementById("gss-widget-form-' . esc_attr($this->id) . '");
            var results=document.getElementById("gss-widget-results-' . esc_attr($this->id) . '");
            if(form){
                form.addEventListener("submit",function(e){
                    e.preventDefault();
                    var phrase=form.querySelector("input[name=gss_widget_phrase]").value;
                    if(!phrase) return;
                    results.innerHTML=\'<div style="color:#888;">Suche läuft...</div>\';
                    fetch(window.location.pathname+"?gss_widget_ajax=1&phrase="+encodeURIComponent(phrase))
                        .then(r=>r.text())
                        .then(html=>{results.innerHTML=html;});
                });
            }
        });</script>';

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags for title and name to remove HTML (important for text inputs). */
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array)$instance, array(
			'title' => __( 'Netzwerksuche', 'postindexer' ),
		) );
		?><p>
			<label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Titel', 'postindexer' ) ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'title' ) ?>" name="<?php echo $this->get_field_name( 'title' ) ?>" value="<?php echo esc_attr( $instance['title'] ) ?>" class="widefat">
		</p><?php
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
	if ( isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('global_site_search') ) {
		add_action( 'widgets_init', 'global_site_search_load_widgets' );
	}
});

// Entfernt: add_action( 'widgets_init', 'global_site_search_load_widgets' );
function global_site_search_load_widgets() {
	if ( in_array( get_current_blog_id(), global_site_search_get_allowed_blogs() ) ) {
		register_widget( 'Global_Site_Search_Widget' );
	}
}