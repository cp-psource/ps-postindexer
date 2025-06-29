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
            'desc' => 'Ermöglicht eine zentrale Suche über alle Seiten und Beiträge im gesamten Multisite-Netzwerk. Erstellt bei Aktivierung eine Suchseite (/site-search) und stellt Netzwerkweite Such Widgets bereit',
            'settings_page' => '', // ggf. später ergänzen
        ],
        'recent_global_posts_widget' => [
            'name' => 'Neueste Netzwerk Beiträge',
            'desc' => 'Stellt ein Widget bereit, das die neuesten Beiträge aus dem gesamten Netzwerk anzeigt. Alle Einstellungen legst du für jedes Widget separat an, wähle hier aus wo du das Widget erlauben willst',
            'settings_page' => '',
        ],
        'global_site_tags' => [
            'name' => 'Netzwerk Seiten-Tags',
            'desc' => 'Ermöglicht die Anzeige und Verwaltung globaler Schlagwörter (Tags) im gesamten Netzwerk. Es wird automatisch eine Seite mit dem Namen „Tags“ auf deinem Hauptblog erstellt, auf der jeder alle Blogs durchsuchen kann.',
            'settings_page' => '',
        ],
        'live_stream_widget' => [
            'name' => 'Live Stream Widget',
            'desc' => 'Zeigt die neuesten Beiträge und Kommentare in einem Live-Stream-Widget an.',
            'settings_page' => '',
        ],
        'recent_global_comments_widget' => [
            'name' => 'Global Comments Widget',
            'desc' => 'Stellt ein Widget bereit, das die neuesten Kommentare aus dem gesamten Netzwerk anzeigt.',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'recent_comments' => [
            'name' => 'Netzwerk Kommentare',
            'desc' => 'Zeigt die letzten Netzwerk-Kommentare per Shortcode [network_comments] an. Die zentrale Konfiguration erfolgt im Netzwerk-Admin unter den Erweiterungs-Einstellungen. (Comment Indexer erforderlich)',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'recent_global_author_comments_feed' => [
            'name' => 'Global Author Comments Feed',
            'desc' => 'Stellt einen globalen Feed aller Kommentare eines Autors im Netzwerk bereit.',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'comment_form_text' => [
            'name' => 'Comment Form Text',
            'desc' => 'Ermöglicht die Anpassung des Kommentarformular-Textes im gesamten Netzwerk.',
            'settings_page' => ''
        ],
        'comments_control' => [
            'name' => 'Comments Control',
            'desc' => 'Feinjustierung der Kommentar-Drosselung und IP-Whitelist/Blacklist für Kommentare im Netzwerk.',
            'settings_page' => ''
        ],
        'recent_global_author_posts_feed' => [
            'name' => 'Global Author Posts Feed',
            'desc' => 'Stellt einen globalen Feed aller Beiträge eines Autors im Netzwerk bereit.',
            'settings_page' => ''
        ],
        'recent_global_comments_feed' => [
            'name' => 'Recent Global Comments Feed',
            'desc' => 'Stellt einen globalen Feed der neuesten Kommentare im Netzwerk bereit.',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'recent_global_posts_feed' => [
            'name' => 'Recent Global Posts Feed',
            'desc' => 'Stellt einen globalen Feed der neuesten Beiträge im Netzwerk bereit.',
            'settings_page' => ''
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
        // Prüfen, ob Comment Indexer aktiv ist
        $comment_indexer_active = function_exists('get_site_option') && get_site_option('comment_indexer_active', 0);
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
                echo '<div class="updated notice is-dismissible"><p>Netzwerk Seiten-Tags: Einstellungen gespeichert.</p></div>';
            }
        }
        // Speicherlogik für Comments Control
        if (isset($_POST['comments_control_settings_nonce']) && check_admin_referer('comments_control_settings_save','comments_control_settings_nonce')) {
            if (isset($_POST['limit_comments_allowed_ips'])) {
                update_site_option('limit_comments_allowed_ips', $_POST['limit_comments_allowed_ips']);
            }
            if (isset($_POST['limit_comments_denied_ips'])) {
                update_site_option('limit_comments_denied_ips', $_POST['limit_comments_denied_ips']);
            }
            echo '<div class="updated notice is-dismissible"><p>Comments Control: Einstellungen gespeichert.</p></div>';
        }
        // Neue Speicherlogik: pro Erweiterung/Card
        foreach ($this->extensions as $key => $ext) {
            if (
                isset($_POST['ps_extension_settings_nonce_' . $key]) &&
                check_admin_referer('ps_extension_settings_save_' . $key, 'ps_extension_settings_nonce_' . $key)
            ) {
                $settings = $this->get_settings();
                // Wenn Erweiterung Comment Indexer benötigt und dieser deaktiviert ist: Status merken, aber nicht aktivieren
                if (!empty($ext['requires_comment_indexer']) && !$comment_indexer_active) {
                    $settings[$key]['active_backup'] = isset($settings[$key]['active']) ? $settings[$key]['active'] : 0;
                    $settings[$key]['active'] = 0;
                } else {
                    $settings[$key]['scope'] = sanitize_text_field($_POST['ps_extensions_scope'][$key] ?? 'main');
                    if ($settings[$key]['scope'] === 'sites') {
                        $settings[$key]['sites'] = array_map('intval', $_POST['ps_extensions_sites'][$key] ?? []);
                    } else {
                        $settings[$key]['sites'] = [];
                    }
                    $settings[$key]['active'] = isset($_POST['ps_extensions_active'][$key]) && $_POST['ps_extensions_active'][$key] === '1' ? 1 : 0;
                    if (!empty($settings[$key]['active_backup']) && $settings[$key]['active']) {
                        unset($settings[$key]['active_backup']);
                    }
                }
                update_site_option($this->option_name, $settings);
                // Erweiterungs-spezifische Settings speichern
                if ($key === 'recent_network_posts' && class_exists('Recent_Network_Posts')) {
                    $recent = new \Recent_Network_Posts();
                    if (method_exists($recent, 'process_settings_form')) {
                        $recent->process_settings_form();
                    }
                } elseif ($key === 'global_site_search' && class_exists('Global_Site_Search_Settings_Renderer')) {
                    $gss = new \Global_Site_Search_Settings_Renderer();
                    if (method_exists($gss, 'process_settings_form')) {
                        $gss->process_settings_form();
                    }
                } elseif ($key === 'global_site_tags') {
                    require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
                    if (class_exists('Global_Site_Tags_Settings_Renderer')) {
                        $gst = new \Global_Site_Tags_Settings_Renderer();
                        if (method_exists($gst, 'process_settings_form')) {
                            $gst->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_posts_widget') {
                    require_once dirname(__DIR__) . '/includes/recent-global-posts-widget/settings.php';
                    if (class_exists('Recent_Global_Posts_Widget_Settings_Renderer')) {
                        $rgpw = new \Recent_Global_Posts_Widget_Settings_Renderer();
                        if (method_exists($rgpw, 'process_settings_form')) {
                            $rgpw->process_settings_form();
                        }
                    }
                } elseif ($key === 'live_stream_widget') {
                    require_once dirname(__DIR__) . '/includes/live-stream-widget/settings.php';
                    if (class_exists('Live_Stream_Widget_Settings_Renderer')) {
                        $lsw = new \Live_Stream_Widget_Settings_Renderer();
                        if (method_exists($lsw, 'process_settings_form')) {
                            $lsw->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_comments_widget') {
                    require_once dirname(__DIR__) . '/includes/recent-global-comments-widget/settings.php';
                    if (class_exists('Recent_Global_Comments_Widget_Settings_Renderer')) {
                        $rgcw = new \Recent_Global_Comments_Widget_Settings_Renderer();
                        if (method_exists($rgcw, 'process_settings_form')) {
                            $rgcw->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_comments') {
                    require_once dirname(__DIR__) . '/includes/recent-comments/settings.php';
                    if (class_exists('Recent_Comments_Settings_Renderer')) {
                        $rcw = new \Recent_Comments_Settings_Renderer();
                        if (method_exists($rcw, 'process_settings_form')) {
                            $rcw->process_settings_form($comment_indexer_active);
                        }
                    }
                } elseif ($key === 'recent_global_author_comments_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-author-comments-feed/settings.php';
                    if (class_exists('Recent_Global_Author_Comments_Feed_Settings_Renderer')) {
                        $gacf = new \Recent_Global_Author_Comments_Feed_Settings_Renderer();
                        if (method_exists($gacf, 'process_settings_form')) {
                            $gacf->process_settings_form();
                        }
                    }
                } elseif ($key === 'comment_form_text') {
                    require_once dirname(__DIR__) . '/includes/comment-form-text/comment-form-text.php';
                    if (class_exists('Comment_Form_Text_Settings_Renderer')) {
                        $cft = new \Comment_Form_Text_Settings_Renderer();
                        if (method_exists($cft, 'process_settings_form')) {
                            $cft->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_author_posts_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-author-posts-feed/settings.php';
                    if (class_exists('Recent_Global_Author_Posts_Feed_Settings_Renderer')) {
                        $rgapf = new \Recent_Global_Author_Posts_Feed_Settings_Renderer();
                        if (method_exists($rgapf, 'process_settings_form')) {
                            $rgapf->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_comments_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-comments-feed/settings.php';
                    if (class_exists('Recent_Global_Comments_Feed_Settings_Renderer')) {
                        $rgcf = new \Recent_Global_Comments_Feed_Settings_Renderer();
                        if (method_exists($rgcf, 'process_settings_form')) {
                            $rgcf->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_posts_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-posts-feed/settings.php';
                    if (class_exists('Recent_Global_Posts_Feed_Settings_Renderer')) {
                        $rgpf = new \Recent_Global_Posts_Feed_Settings_Renderer();
                        if (method_exists($rgpf, 'process_settings_form')) {
                            $rgpf->process_settings_form();
                        }
                    }
                }
                echo '<div class="updated notice is-dismissible"><p>Einstellungen für <b>' . esc_html($ext['name']) . '</b> gespeichert.</p></div>';
            }
        }
        $settings = $this->get_settings();
        $sites = get_sites(['fields'=>'ids','number'=>0]);
        $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
        echo '<div class="wrap"><h1>' . esc_html__( 'Erweiterungen', 'postindexer' ) . '</h1>';
        if (!$comment_indexer_active) {
            echo '<div style="max-width:600px;margin:2em auto 1.5em auto;padding:1.5em 2em;background:#fffbe6;border:1.5px solid #ffe58f;border-radius:12px;box-shadow:0 2px 12px rgba(255,215,0,0.07);display:flex;align-items:center;gap:1.2em;">';
            echo '<span style="font-size:2.1em;color:#f1c40f;">&#9888;&#65039;</span>';
            echo '<div style="flex:1;">';
            echo '<div style="font-size:1.18em;font-weight:600;color:#b8860b;margin-bottom:0.2em;">' . esc_html__('Comment Indexer ist aktuell deaktiviert', 'postindexer') . '</div>';
            echo '<div style="font-size:1.04em;color:#444;margin-bottom:0.7em;">' . esc_html__('Erweiterungen, die darauf basieren, können nicht genutzt werden.', 'postindexer') . '</div>';
            echo '<div style="margin-top:0.7em;font-size:1.08em;">'
                . esc_html__('Du kannst den Comment Indexer ', 'postindexer')
                . '<a href="' . esc_url(network_admin_url('admin.php?page=comment-index')) . '" style="font-weight:bold;">' . esc_html__('HIER aktivieren', 'postindexer') . '</a>'
                . '.</div>';
            echo '</div>';
            echo '</div>';
        }
        // <form> wieder einfügen, damit die Aktivierungs-Checkboxen korrekt gespeichert werden
        // ENTFERNT: echo '<form method="post">';
        // ENTFERNT: wp_nonce_field('ps_extensions_scope_save','ps_extensions_scope_nonce');
        echo '<style>
        .ps-extensions-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 2.5em 2.5em;
            justify-content: flex-start;
            align-items: flex-start;
        }
        .ps-extension-card {
            background: #fff;
            border: 1.5px solid #e5e5e5;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 2em 1.5em 1.5em 1.5em;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            position:relative;
            cursor:pointer;
            transition:box-shadow .2s, border .2s, flex-basis .3s cubic-bezier(.4,1.4,.6,1), max-width .3s cubic-bezier(.4,1.4,.6,1), min-width .3s cubic-bezier(.4,1.4,.6,1);
            overflow: hidden;
            flex: 1 1 340px;
            max-width: 420px;
            min-width: 320px;
            z-index: 1;
        }
        .ps-extension-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .ps-extension-card *:is(input,select,label,button) { cursor:auto !important; }
        .ps-extension-card.active {
            flex: 1 1 90vw;
            width: 90vw;
            max-width: 900px;
            min-width: 340px;
            z-index: 10;
            border:2px solid #2ecc40;
            box-shadow:0 8px 32px rgba(46,204,64,0.13);
        }
        @media (max-width: 1200px) {
            .ps-extension-card.active {
                max-width: 98vw;
                min-width: 98vw;
                width: 98vw;
            }
        }
        @media (max-width: 800px) {
            .ps-extension-card, .ps-extension-card.active {
                min-width: 98vw;
                max-width: 98vw;
                flex-basis: 98vw;
                width: 98vw;
            }
        }
        .ps-status-label { font-weight:bold; min-width:50px; display:inline-block; }
        .ps-extension-actions { margin-top:auto; display:flex; gap:1em; }
        .ps-scope-row { margin-bottom:1.2em; background:#f8f9fa; border-radius:7px; padding:0.7em 1em; }
        .ps-scope-row label { margin-right:1.5em; font-size:0.98em; }
        .ps-scope-sites { margin-top:0.7em; }
        .ps-scope-sites select { min-width:180px; }
        .ps-extension-settings-form { display:none !important; }
        .ps-extension-card.active .ps-extension-settings-form { display:block !important; }
        .ps-extension-save-btn {
            background: linear-gradient(90deg, #00c3ff 0%, #005bea 100%);
            color: #fff !important;
            border: none;
            border-radius: 6px;
            padding: 0.7em 2.2em;
            font-size: 1.08em;
            font-weight: 600;
            letter-spacing: 0.03em;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
            cursor: pointer;
            margin-top: 1.2em;
            display: none;
        }
        .ps-extension-save-btn:hover, .ps-extension-save-btn:focus {
            background: linear-gradient(90deg, #005bea 0%, #00c3ff 100%);
            box-shadow: 0 4px 16px rgba(0,80,200,0.13);
            transform: translateY(-2px) scale(1.04);
            outline: none;
        }
        </style>';
        echo '<div class="ps-extensions-grid">';
        foreach ($this->extensions as $key => $ext) {
            $scope = $settings[$key]['scope'] ?? 'main';
            $selected_sites = $settings[$key]['sites'] ?? [];
            $active = isset($settings[$key]['active']) ? (int)$settings[$key]['active'] : 1;
            $disabled = (!empty($ext['requires_comment_indexer']) && !$comment_indexer_active) ? 'disabled' : '';
            // Card ist initial NICHT active!
            echo '<div class="ps-extension-card" tabindex="0" data-extkey="' . esc_attr($key) . '">';
            // Card-Infos außerhalb des Formulars
            echo '<h2>' . esc_html($ext['name']) . '</h2>';
            echo '<p>' . esc_html($ext['desc']) . '</p>';
            // Formular NUR für Felder
            echo '<form method="post" class="ps-extension-settings-form" style="display:none;">';
            wp_nonce_field('ps_extension_settings_save_' . $key, 'ps_extension_settings_nonce_' . $key);
            // Status/Toggle prominent oben im Formular
            $readonly = (!empty($ext['requires_comment_indexer']) && !$comment_indexer_active) ? 'readonly' : '';
            echo '<div class="ps-extension-status">
<span class="ps-status-label" style="color:'.($active ? '#2ecc40' : '#aaa').';">'.($active ? 'Aktiv' : 'Inaktiv').'</span>';
echo '<input type="hidden" name="ps_extensions_active['.$key.']" value="0">';
echo '<label class="ps-switch"><input type="checkbox" name="ps_extensions_active['.$key.']" value="1" '.($active ? 'checked' : '').' '.$readonly.'><span class="ps-slider"></span></label>';
echo '</div>';
            // Bereich-Auswahl optisch abgesetzt
            echo '<div class="ps-scope-row">Aktivierungsbereich:<br>';
            echo '<label><input type="radio" name="ps_extensions_scope['.$key.']" value="network" '.checked($scope,'network',false).' '.$readonly.'> Netzwerkweit</label>';
            echo '<label><input type="radio" name="ps_extensions_scope['.$key.']" value="main" '.checked($scope,'main',false).' '.$readonly.'> Nur Hauptseite</label>';
            echo '<label><input type="radio" name="ps_extensions_scope['.$key.']" value="sites" '.checked($scope,'sites',false).' '.$readonly.'> Bestimmte Seiten</label>';
            $display_sites = ($scope==='sites') ? 'block' : 'none';
            echo '<div class="ps-scope-sites" style="display:'.$display_sites.';">';
            echo '<select name="ps_extensions_sites['.$key.'][]" multiple size="4" '.$readonly.'>';
foreach ($sites as $site_id) {
    $blog_details = get_blog_details($site_id);
    $sel = in_array($site_id, $selected_sites) ? 'selected' : '';
    echo '<option value="'.$site_id.'" '.$sel.'>' . esc_html($blog_details->blogname) . ' (ID '.$site_id.')</option>';
}
echo '</select></div>';
echo '</div>';
            // Eigene Einstellungen der Erweiterung (falls vorhanden)
            $settings_html = '';
            $has_settings = false;
            if ($key === 'recent_network_posts' && class_exists('Recent_Network_Posts')) {
                $recent = new \Recent_Network_Posts();
                $settings_html = $recent->render_settings_form(); $has_settings = true;
            } elseif ($key === 'global_site_search' && class_exists('Global_Site_Search_Settings_Renderer')) {
                $gss = new \Global_Site_Search_Settings_Renderer();
                $settings_html = $gss->render_settings_form(); $has_settings = true;
            } elseif ($key === 'global_site_tags') {
                require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
                if (class_exists('Global_Site_Tags_Settings_Renderer')) {
                    $gst = new \Global_Site_Tags_Settings_Renderer();
                    $settings_html = $gst->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'recent_global_posts_widget' ) {
                require_once dirname(__DIR__) . '/includes/recent-global-posts-widget/settings.php';
                if (class_exists('Recent_Global_Posts_Widget_Settings_Renderer')) {
                    $rgpw = new \Recent_Global_Posts_Widget_Settings_Renderer();
                    $settings_html = $rgpw->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'live_stream_widget' ) {
                require_once dirname(__DIR__) . '/includes/live-stream-widget/settings.php';
                if (class_exists('Live_Stream_Widget_Settings_Renderer')) {
                    $lsw = new \Live_Stream_Widget_Settings_Renderer();
                    $settings_html = $lsw->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'recent_global_comments_widget' ) {
                require_once dirname(__DIR__) . '/includes/recent-global-comments-widget/settings.php';
                if (class_exists('Recent_Global_Comments_Widget_Settings_Renderer')) {
                    $rgcw = new \Recent_Global_Comments_Widget_Settings_Renderer();
                    $settings_html = $rgcw->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'recent_comments') {
                require_once dirname(__DIR__) . '/includes/recent-comments/settings.php';
                if (class_exists('Recent_Comments_Settings_Renderer')) {
                    $rcw = new \Recent_Comments_Settings_Renderer();
                    $settings_html = $rcw->render_settings_form($comment_indexer_active); $has_settings = true;
                }
            } elseif ($key === 'recent_global_author_comments_feed' ) {
                if (!$comment_indexer_active) {
                    $settings[$key]['active'] = 0;
                }
                require_once dirname(__DIR__) . '/includes/recent-global-author-comments-feed/settings.php';
                if (class_exists('Recent_Global_Author_Comments_Feed_Settings_Renderer')) {
                    $gacf = new \Recent_Global_Author_Comments_Feed_Settings_Renderer();
                    $settings_html = $gacf->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'comment_form_text') {
                require_once dirname(__DIR__) . '/includes/comment-form-text/comment-form-text.php';
                if (class_exists('Comment_Form_Text_Settings_Renderer')) {
                    $cft = new \Comment_Form_Text_Settings_Renderer();
                    $settings_html = $cft->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'recent_global_author_posts_feed' ) {
                require_once dirname(__DIR__) . '/includes/recent-global-author-posts-feed/settings.php';
                if (class_exists('Recent_Global_Author_Posts_Feed_Settings_Renderer')) {
                    $rgapf = new \Recent_Global_Author_Posts_Feed_Settings_Renderer();
                    $settings_html = $rgapf->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'recent_global_comments_feed' ) {
                require_once dirname(__DIR__) . '/includes/recent-global-comments-feed/settings.php';
                if (class_exists('Recent_Global_Comments_Feed_Settings_Renderer')) {
                    $rgcf = new \Recent_Global_Comments_Feed_Settings_Renderer();
                    $settings_html = $rgcf->render_settings_form(); $has_settings = true;
                }
            } elseif ($key === 'recent_global_posts_feed' ) {
                require_once dirname(__DIR__) . '/includes/recent-global-posts-feed/settings.php';
                if (class_exists('Recent_Global_Posts_Feed_Settings_Renderer')) {
                    $rgpf = new \Recent_Global_Posts_Feed_Settings_Renderer();
                    $settings_html = $rgpf->render_settings_form(); $has_settings = true;
                }
            }
            if (!empty($ext['requires_comment_indexer']) && !$comment_indexer_active) {
                echo '<div style="color:#c00;font-weight:bold;margin-top:1em;">Diese Erweiterung benötigt den Comment Indexer.</div>';
                $settings[$key]['active'] = 0;
            }
            if ($has_settings && !empty($settings_html)) {
                // Entferne das gesamte <form ...>...</form> inkl. Submit-Button aus $settings_html, sodass nur die Felder (inkl. Nonce) übrig bleiben
                $settings_html = preg_replace('#^\s*<form[^>]*>#is', '', $settings_html);
                $settings_html = preg_replace('#<input[^>]+type=["\']?submit["\']?[^>]*>#is', '', $settings_html); // Entfernt submit-Button
                $settings_html = preg_replace('#<button[^>]+type=["\']?submit["\']?[^>]*>.*?</button>#is', '', $settings_html); // Entfernt Button-Submit
                $settings_html = preg_replace('#</form>\s*$#is', '', $settings_html);
                echo '<div class="ps-extension-settings-fields">' . $settings_html . '</div>';
            }
            echo '<button type="submit" class="ps-extension-save-btn" style="margin-top:1.2em;display:none;">Einstellungen speichern</button>';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>'; // .ps-extensions-grid
        // JS für Card-Expand/Collapse und Form-Anzeige
        // Initial: alle Cards zu, kein Formular sichtbar
        // Beim Klick: nur die geklickte Card bekommt 'active', nur dort wird das Formular und der Button angezeigt
        // Klick auf Inputs/Buttons/etc. in der Card öffnet NICHT die Card erneut
        // Card bleibt nach Submit offen

        echo "<script>document.addEventListener('DOMContentLoaded',function(){\n    document.querySelectorAll('.ps-extension-card').forEach(function(card){\n        var form = card.querySelector('.ps-extension-settings-form');\n        if(form) {\n            form.style.display='none';\n            var btn = form.querySelector('.ps-extension-save-btn');\n            if(btn) btn.style.display='none';\n        }\n        card.classList.remove('active');\n        card.addEventListener('click',function(e){\n            if(e.target.closest('input,select,button,label,form')) return;\n            document.querySelectorAll('.ps-extension-card').forEach(function(c){\n                if(c!==card){\n                    c.classList.remove('active');\n                    var f = c.querySelector('.ps-extension-settings-form');\n                    if(f) {\n                        f.style.display='none';\n                        var b = f.querySelector('.ps-extension-save-btn');\n                        if(b) b.style.display='none';\n                    }\n                }\n            });\n            card.classList.add('active');\n            var form = card.querySelector('.ps-extension-settings-form');\n            if(form) {\n                form.style.display='block';\n                var btn = form.querySelector('.ps-extension-save-btn');\n                if(btn) btn.style.display='inline-block';\n                // Editor-Initialisierung NUR für sichtbare Felder im aktiven Formular\n                form.querySelectorAll('textarea.wp-editor-area').forEach(function(textarea){\n                    var id = textarea.id;\n                    if(id && typeof window.switchEditor==='object' && typeof window.switchEditor.go==='function') {\n                        if(window.tinymce && !window.tinymce.get(id)) {\n                            try { window.switchEditor.go(id, 'tmce'); } catch(e){}\n                        }\n                    }\n                });\n            }\n        });\n    });\n});</script>";
        // Nach dem Speichern: Setup für Netzwerk Seiten-Tags erzwingen, wenn aktiviert
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
        // Migration: Wenn recent_comments noch keine Settings hat, aber recent_global_comments_widget schon, dann verschiebe sie dauerhaft
        if (!isset($settings['recent_comments']) && isset($settings['recent_global_comments_widget'])) {
            $settings['recent_comments'] = $settings['recent_global_comments_widget'];
            unset($settings['recent_global_comments_widget']);
            update_site_option($this->option_name, $settings); // dauerhaft speichern
        }
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

    // Hilfsfunktion: Gibt den passenden Settings-Renderer-Key für Netzwerk-Kommentare zurück
    private function get_recent_comments_settings_key() {
        // Bevorzuge neuen Key, aber unterstütze Fallback auf alten
        if (isset($this->extensions['recent_comments'])) {
            return 'recent_comments';
        } elseif (isset($this->extensions['recent_global_comments_widget'])) {
            return 'recent_global_comments_widget';
        }
        return null;
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
