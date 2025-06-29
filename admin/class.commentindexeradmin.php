<?php
// Modul: Comment Indexer für PS-Postindexer (kein eigenständiges Plugin mehr)

require_once dirname(__DIR__) . '/comment-indexer.php';

// Admin-Seite für den Comment Indexer (Netzwerk-Admin)
class Comment_Indexer_Admin {
    private $option_name = 'comment_indexer_active';

    public function __construct() {
        // Tabelle anlegen, falls sie fehlt (direkt beim Instanziieren)
        global $wpdb;
        $table = $wpdb->base_prefix.'site_comments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            if (function_exists('comment_indexer_global_install')) {
                comment_indexer_global_install();
            }
        }
        add_action('network_admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        // Comment Index als Submenü direkt nach Post Index einfügen
        // Dazu $position=1 setzen, damit es direkt nach dem Hauptmenü erscheint
        $parent_slug = 'ps-multisite-index';
        add_submenu_page(
            $parent_slug,
            __('Comment Index', 'postindexer'),
            __('Comment Index', 'postindexer'),
            'manage_network',
            'comment-index',
            [$this, 'render_page'],
            1 // Position direkt nach Post Index
        );
    }

    public function render_page() {
        if (isset($_POST['comment_indexer_toggle']) && check_admin_referer('comment_indexer_toggle_action','comment_indexer_toggle_nonce')) {
            $active_value = isset($_POST['comment_indexer_active']) ? $_POST['comment_indexer_active'] : '0';
            update_site_option($this->option_name, $active_value === '1' ? 1 : 0);
            echo '<div class="updated notice is-dismissible"><p>'.__('Einstellungen gespeichert.','postindexer').'</p></div>';
        }
        $active = (int)get_site_option($this->option_name, 0);
        echo '<div class="wrap"><h1>'.esc_html__('Comment Index','postindexer').'</h1>';
        echo '<form method="post" style="margin-bottom:2em;">';
        wp_nonce_field('comment_indexer_toggle_action','comment_indexer_toggle_nonce');
        echo '<label style="font-size:1.2em;font-weight:bold;display:flex;align-items:center;gap:1em;">';
        echo '<span>'.__('Comment Indexer aktivieren','postindexer').'</span>';
        echo '<input type="hidden" name="comment_indexer_toggle" value="1">';
        echo '<span class="ps-switch">';
        echo '<input type="checkbox" name="comment_indexer_active" value="1" '.($active?'checked':'').' style="opacity:0;width:0;height:0;">';
        echo '<span class="ps-slider"></span>';
        echo '</span>';
        echo '<span class="ps-status-label" style="font-weight:bold;color:'.($active?'#2ecc40':'#aaa').';">'.($active?__('Aktiviert','postindexer'):__('Deaktiviert','postindexer')).'</span>';
        echo '</label> ';
        echo '<button type="submit" class="button button-primary" style="margin-left:1em;">'.__('Speichern','postindexer').'</button>';
        echo '</form>';
        echo '<style>.ps-switch { position: relative; display: inline-block; width: 48px; height: 24px; } .ps-switch input { opacity: 0; width: 0; height: 0; } .ps-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px; } .ps-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; } .ps-switch input:checked + .ps-slider { background-color: #2ecc40; } .ps-switch input:checked + .ps-slider:before { transform: translateX(24px); } .ps-status-label { min-width:70px; display:inline-block; }</style>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var sw=document.querySelector(".ps-switch input[type=checkbox][name=\"comment_indexer_active\"]");var label=document.querySelector(".ps-status-label");if(sw&&label){sw.addEventListener("change",function(){if(this.checked){label.textContent="Aktiviert";label.style.color="#2ecc40";}else{label.textContent="Deaktiviert";label.style.color="#aaa";}});}});</script>';
        if ($active) {
            $this->render_comments_table();
        } else {
            echo '<div style="color:#888;font-size:1.1em;">'.__('Der Comment Indexer ist derzeit deaktiviert.','postindexer').'</div>';
        }
        echo '</div>';
    }

    public function render_comments_table() {
        global $wpdb;
        // Sicherstellen, dass die Tabelle existiert
        if (function_exists('comment_indexer_ensure_table_exists')) {
            comment_indexer_ensure_table_exists();
        }
        $table = $wpdb->base_prefix.'site_comments';
        $comments = $wpdb->get_results("SELECT * FROM $table ORDER BY comment_date_gmt DESC LIMIT 50");
        echo '<h2 style="margin-top:2em;">'.__('Letzte globale Kommentare','postindexer').'</h2>';
        echo '<table class="widefat striped" style="margin-top:1em;max-width:100%;">';
        echo '<thead><tr>';
        echo '<th>'.__('ID').'</th><th>'.__('Blog').'</th><th>'.__('Autor').'</th><th>'.__('Inhalt').'</th><th>'.__('Datum').'</th><th>'.__('Status').'</th>';
        echo '</tr></thead><tbody>';
        if ($comments) {
            foreach ($comments as $c) {
                echo '<tr>';
                echo '<td>'.(int)$c->comment_id.'</td>';
                echo '<td>'.(int)$c->blog_id.'</td>';
                echo '<td>'.esc_html($c->comment_author).'</td>';
                echo '<td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;">'.esc_html(wp_trim_words($c->comment_content,20)).'</td>';
                echo '<td>'.esc_html($c->comment_date_gmt).'</td>';
                echo '<td>'.esc_html($c->comment_approved).'</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6" style="text-align:center;color:#888;">'.__('Keine Kommentare gefunden.','postindexer').'</td></tr>';
        }
        echo '</tbody></table>';
    }
}
