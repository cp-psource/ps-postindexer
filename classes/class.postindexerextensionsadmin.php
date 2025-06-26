<?php

if ( ! class_exists( 'Postindexer_Extensions_Admin' ) ) {

class Postindexer_Extensions_Admin {

    private $extensions = [
        'recent_network_posts' => [
            'name' => 'Aktuelle Netzwerkbeiträge',
            'desc' => 'Zeigt eine anpassbare Liste der letzten Beiträge aus dem gesamten Multisite-Netzwerk an. Die Ausgabe erfolgt per Shortcode: [recent_network_posts] – einfach auf einer beliebigen Seite oder im Block-Editor einfügen.',
            'settings_page' => 'network-posts-settings',
        ],
        'global_site_search' => [
            'name' => 'Globale Netzwerksuche',
            'desc' => 'Ermöglicht eine zentrale Suche über alle Seiten und Beiträge im gesamten Multisite-Netzwerk.',
            'settings_page' => '', // ggf. später ergänzen
        ],
        'recent_global_posts_widget' => [
            'name' => 'Recent Global Posts Widget',
            'desc' => 'Stellt ein Widget bereit, das die neuesten Beiträge aus dem gesamten Netzwerk anzeigt.',
            'settings_page' => '',
        ],
        'global_site_tags' => [
            'name' => 'Global Site Tags',
            'desc' => 'Ermöglicht die Anzeige und Verwaltung globaler Schlagwörter (Tags) im gesamten Netzwerk.',
            'settings_page' => '',
        ],
        'live_stream_widget' => [
            'name' => 'Live Stream Widget',
            'desc' => 'Zeigt die neuesten Beiträge und Kommentare in einem Live-Stream-Widget an.',
            'settings_page' => '',
        ],
        // Weitere Erweiterungen können hier ergänzt werden
    ];

    private $option_name = 'postindexer_extensions_settings';

    public function __construct() {}

    public function register_menu( $main_slug, $cap ) {
        add_submenu_page(
            $main_slug,
            __( 'Erweiterungen', 'postindexer' ),
            __( 'Erweiterungen', 'postindexer' ),
            $cap,
            $main_slug . '-extensions',
            array( $this, 'render_extensions_page' )
        );
    }

    public function render_extensions_page() {
        // Speicherlogik für Bereich/Status wie gehabt
        if ( isset($_POST['ps_extensions_scope']) && is_array($_POST['ps_extensions_scope']) && check_admin_referer('ps_extensions_scope_save','ps_extensions_scope_nonce') ) {
            $settings = $this->get_settings();
            foreach ($this->extensions as $key => $ext) {
                $settings[$key]['scope'] = sanitize_text_field($_POST['ps_extensions_scope'][$key] ?? 'main');
                if ($settings[$key]['scope'] === 'sites') {
                    $settings[$key]['sites'] = array_map('intval', $_POST['ps_extensions_sites'][$key] ?? []);
                } else {
                    $settings[$key]['sites'] = [];
                }
                // Aktivierungsstatus speichern
                $settings[$key]['active'] = isset($_POST['ps_extensions_active'][$key]) && $_POST['ps_extensions_active'][$key] === '1' ? 1 : 0;
            }
            update_site_option($this->option_name, $settings);
            echo '<div class="updated notice is-dismissible"><p>Einstellungen gespeichert.</p></div>';
        }
        // Speicherlogik für global-site-search
        if (isset($_POST['ps_gss_settings_nonce']) && check_admin_referer('ps_gss_settings_save','ps_gss_settings_nonce')) {
            if (function_exists('global_site_search_site_admin_options_process')) {
                global_site_search_site_admin_options_process();
                echo '<div class="updated notice is-dismissible"><p>Globale Netzwerksuche: Einstellungen gespeichert.</p></div>';
            }
        }
        // Speicherlogik für global-site-tags
        if (isset($_POST['ps_gst_settings_nonce']) && check_admin_referer('ps_gst_settings_save','ps_gst_settings_nonce')) {
            if (function_exists('global_site_tags_site_admin_options_process')) {
                global_site_tags_site_admin_options_process();
                echo '<div class="updated notice is-dismissible"><p>Global Site Tags: Einstellungen gespeichert.</p></div>';
            }
        }
        $settings = $this->get_settings();
        $sites = get_sites(['fields'=>'ids','number'=>0]);
        $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
        $settings_html = [];
        foreach ($this->extensions as $key => $ext) {
            ob_start();
            if ($key === 'recent_network_posts' && class_exists('Recent_Network_Posts')) {
                $recent = new \Recent_Network_Posts();
                echo $recent->render_settings_form();
            } elseif ($key === 'global_site_search' && class_exists('Global_Site_Search_Settings_Renderer')) {
                $gss = new \Global_Site_Search_Settings_Renderer();
                echo $gss->render_settings_form();
            } elseif ($key === 'global_site_tags') {
                require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
                if (class_exists('Global_Site_Tags_Settings_Renderer')) {
                    $gst = new \Global_Site_Tags_Settings_Renderer();
                    echo $gst->render_settings_form();
                } else {
                    echo '<div style="color:#888;">(Keine Einstellungen verfügbar)</div>';
                }
            } elseif ($key === 'recent_global_posts_widget') {
                require_once dirname(__DIR__) . '/includes/recent-global-posts-widget/settings.php';
                if (class_exists('Recent_Global_Posts_Widget_Settings_Renderer')) {
                    $rgpw = new \Recent_Global_Posts_Widget_Settings_Renderer();
                    echo $rgpw->render_settings_form();
                } else {
                    echo '<div style="color:#888;">(Keine Einstellungen verfügbar)</div>';
                }
            } elseif ($key === 'live_stream_widget') {
                require_once dirname(__DIR__) . '/includes/live-stream-widget/settings.php';
                if (class_exists('Live_Stream_Widget_Settings_Renderer')) {
                    $lsw = new \Live_Stream_Widget_Settings_Renderer();
                    echo $lsw->render_settings_form();
                } else {
                    echo '<div style="color:#888;">(Keine Einstellungen verfügbar)</div>';
                }
            } else {
                echo '<div style="color:#888;">(Keine Einstellungen verfügbar)</div>';
            }
            $settings_html[$key] = ob_get_clean();
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Erweiterungen', 'postindexer' ) . '</h1>';
        // <form> wieder einfügen, damit die Aktivierungs-Checkboxen korrekt gespeichert werden
        echo '<form method="post">';
        wp_nonce_field('ps_extensions_scope_save','ps_extensions_scope_nonce');
        echo '<style>
        .ps-extensions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 2em; margin-top:2em; }
        .ps-extension-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 2em 1.5em 1.5em 1.5em; display: flex; flex-direction: column; position:relative; min-height:320px; cursor:pointer; transition:box-shadow .2s; }
        .ps-extension-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .ps-extension-card *:is(input,select,label,button) { cursor:auto !important; }
        .ps-extension-card.active { border:2px solid #2ecc40; box-shadow:0 6px 20px rgba(46,204,64,0.08); }
        .ps-extension-card h2 { margin-top:0; font-size:1.3em; margin-bottom:0.5em; }
        .ps-extension-card p { color:#444; font-size:1em; margin-bottom:1.2em; }
        .ps-extension-status { position:absolute; top:1.2em; right:1.5em; display:flex; align-items:center; gap:0.7em; }
        .ps-switch { position: relative; display: inline-block; width: 48px; height: 24px; }
        .ps-switch input { opacity: 0; width: 0; height: 0; }
        .ps-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px; }
        .ps-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        .ps-switch input:checked + .ps-slider { background-color: #2ecc40; }
        .ps-switch input:checked + .ps-slider:before { transform: translateX(24px); }
        .ps-status-label { font-weight:bold; min-width:50px; display:inline-block; }
        .ps-extension-actions { margin-top:auto; display:flex; gap:1em; }
        .ps-scope-row { margin-bottom:1.2em; background:#f8f9fa; border-radius:7px; padding:0.7em 1em; }
        .ps-scope-row label { margin-right:1.5em; font-size:0.98em; }
        .ps-scope-sites { margin-top:0.7em; }
        .ps-scope-sites select { min-width:180px; }
        </style>';
        echo '<div class="ps-extensions-grid">';
        foreach ($this->extensions as $key => $ext) {
            $scope = $settings[$key]['scope'] ?? 'main';
            $selected_sites = $settings[$key]['sites'] ?? [];
            $active = isset($settings[$key]['active']) ? (int)$settings[$key]['active'] : 1;
            echo '<div class="ps-extension-card" tabindex="0" data-extkey="' . esc_attr($key) . '">';
            // Status/Toggle prominent oben rechts
            echo '<div class="ps-extension-status>
                <span class="ps-status-label" style="color:'.($active ? '#2ecc40' : '#aaa').';">'.($active ? 'Aktiv' : 'Inaktiv').'</span>';
            echo '<label class="ps-switch"><input type="checkbox" name="ps_extensions_active['.$key.']" value="1" '.($active ? 'checked' : '').'><span class="ps-slider"></span></label>';
            echo '</div>';
            echo '<h2>' . esc_html($ext['name']) . '</h2>';
            echo '<p>' . esc_html($ext['desc']) . '</p>';
            // Bereich-Auswahl optisch abgesetzt
            echo '<div class="ps-scope-row">Aktivierungsbereich:<br>';
            echo '<label><input type="radio" name="ps_extensions_scope['.$key.']" value="network" '.checked($scope,'network',false).'> Netzwerkweit</label>';
            echo '<label><input type="radio" name="ps_extensions_scope['.$key.']" value="main" '.checked($scope,'main',false).'> Nur Hauptseite</label>';
            echo '<label><input type="radio" name="ps_extensions_scope['.$key.']" value="sites" '.checked($scope,'sites',false).'> Bestimmte Seiten</label>';
            $display_sites = ($scope==='sites') ? 'block' : 'none';
            echo '<div class="ps-scope-sites" style="display:'.$display_sites.';">';
            echo '<select name="ps_extensions_sites['.$key.'][]" multiple size="4">';
            foreach ($sites as $site_id) {
                $blog_details = get_blog_details($site_id);
                $sel = in_array($site_id, $selected_sites) ? 'selected' : '';
                echo '<option value="'.$site_id.'" '.$sel.'>' . esc_html($blog_details->blogname) . ' (ID '.$site_id.')</option>';
            }
            echo '</select></div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>'; // .ps-extensions-grid
        echo '<div id="ps-extension-settings-panel" style="margin-top:2em;"></div>';
        echo '</form>';
        // JS: Card-Click füllt das Panel
        echo "<script>document.addEventListener(\"DOMContentLoaded\",function(){
            var settings = ".json_encode($settings_html).";
            var panel = document.getElementById(\"ps-extension-settings-panel\");
            document.querySelectorAll(\".ps-extension-card\").forEach(function(card){
                card.addEventListener(\"click\",function(e){
                    if(e.target.closest(\"input,select,button,label\")) return;
                    document.querySelectorAll(\".ps-extension-card\").forEach(function(c){c.classList.remove(\"active\");});
                    card.classList.add(\"active\");
                    var extkey = card.getAttribute(\"data-extkey\");
                    panel.innerHTML = '';
                    if(settings[extkey]){
                        panel.innerHTML = settings[extkey];
                        panel.style.display = \"block\";
                        panel.scrollIntoView({behavior:\"smooth\",block:\"start\"});
                    } else {
                        panel.innerHTML = '';
                        panel.style.display = \"none\";
                    }
                });
            });
            document.querySelectorAll(\"input[type=radio][value=sites]\").forEach(function(radio){
                radio.addEventListener(\"change\",function(){
                    var sel = this.closest(\".ps-scope-row\").querySelector(\".ps-scope-sites\");
                    if(sel) sel.style.display = \"block\";
                });
            });
            document.querySelectorAll(\"input[type=radio]:not([value=sites])\").forEach(function(radio){
                radio.addEventListener(\"change\",function(){
                    var sel = this.closest(\".ps-scope-row\").querySelector(\".ps-scope-sites\");
                    if(sel) sel.style.display = \"none\";
                });
            });
            document.querySelectorAll(\".ps-switch input[type=checkbox]\").forEach(function(toggle){
                toggle.addEventListener(\"change\",function(){
                    const label = this.closest(\".ps-extension-status\").querySelector(\".ps-status-label\");
                    if(this.checked){ label.textContent = \"Aktiv\"; label.style.color = \"#2ecc40\"; }
                    else { label.textContent = \"Inaktiv\"; label.style.color = \"#aaa\"; }
                });
            });
        });</script>";
        // Nach dem Speichern: Setup für Global Site Tags erzwingen, wenn aktiviert
        if (isset($settings['global_site_tags']['active']) && $settings['global_site_tags']['active']) {
            if (!class_exists('globalsitetags')) {
                require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
            }
            if (class_exists('globalsitetags')) {
                $gst = new \globalsitetags();
                if (method_exists($gst, 'force_setup')) {
                    $gst->force_setup();
                }
            }
        }
    }

    public function get_settings() {
        $settings = get_site_option($this->option_name, []);
        // Defaults für neue Erweiterungen
        foreach ($this->extensions as $key => $ext) {
            if (!isset($settings[$key]['scope'])) $settings[$key]['scope'] = 'main';
            if (!isset($settings[$key]['sites'])) $settings[$key]['sites'] = [];
            if (!isset($settings[$key]['active'])) $settings[$key]['active'] = 0; // Standard: inaktiv
        }
        return $settings;
    }

    // Erweiterte Aktivierungslogik: Ist die Erweiterung aktiviert UND für diese Seite freigegeben?
    public function is_extension_active_for_site($extension_key, $site_id = null) {
        if (!$site_id) $site_id = get_current_blog_id();
        $settings = $this->get_settings();
        $scope = $settings[$extension_key]['scope'] ?? 'main';
        $active = isset($settings[$extension_key]['active']) ? (int)$settings[$extension_key]['active'] : 1;
        $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
        if (!$active) return false;
        if ($scope === 'network') return true;
        if ($scope === 'main') return $site_id == $main_site;
        if ($scope === 'sites') return in_array($site_id, $settings[$extension_key]['sites'] ?? []);
        return false;
    }
}

}

if ( !class_exists('Recent_Network_Posts') ) {
    require_once dirname(__DIR__) . '/includes/recent-global-posts/recent-posts.php';
}
if ( !class_exists('Global_Site_Search_Settings_Renderer') ) {
    require_once dirname(__DIR__) . '/includes/global-site-search/global-site-search.php';
}
if ( !class_exists('Global_Site_Tags_Settings_Renderer') ) {
    require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
}
if ( !class_exists('Live_Stream_Widget_Settings_Renderer') ) {
    require_once dirname(__DIR__) . '/includes/live-stream-widget/live-stream.php';
}
