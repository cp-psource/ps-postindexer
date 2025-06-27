<?php


//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

// Backend: Einstellungen speichern und Formular anzeigen
if (is_network_admin()) {
    // Editor-Skripte im Netzwerk-Admin gezielt laden
    add_action('admin_enqueue_scripts', function($hook) {
        if ($hook === 'settings_page_comment-form-text') {
            if (function_exists('wp_enqueue_editor')) {
                wp_enqueue_editor();
            }
            if (function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }
            // Quicktags für Texteditor laden
            wp_enqueue_script('quicktags');
        }
    });
    add_action('network_admin_menu', function() {
        add_submenu_page('settings.php', 'Comment Form Text', 'Comment Form Text', 'manage_network_options', 'comment-form-text', function() {
            if (isset($_POST['cft_text_logged_in'], $_POST['cft_text_guest'], $_POST['cft_css']) && check_admin_referer('comment_form_text_settings_save', 'comment_form_text_settings_nonce')) {
                update_site_option('cft_text_logged_in', wp_unslash($_POST['cft_text_logged_in']));
                update_site_option('cft_text_guest', wp_unslash($_POST['cft_text_guest']));
                update_site_option('cft_css', wp_unslash($_POST['cft_css']));
                echo '<div class="updated"><p>Einstellungen gespeichert!</p></div>';
            }
            $text_logged_in = get_site_option('cft_text_logged_in', '');
            $text_guest = get_site_option('cft_text_guest', '');
            $css = get_site_option('cft_css', '');
            echo '<div class="wrap"><h1>Comment Form Text Einstellungen</h1>';
            echo '<form method="post">';
            wp_nonce_field('comment_form_text_settings_save', 'comment_form_text_settings_nonce');
            echo '<h2>Text für eingeloggte Nutzer</h2>';
            if (function_exists('wp_editor')) {
                wp_editor($text_logged_in, 'cft_text_logged_in_editor_admin', array('textarea_name'=>'cft_text_logged_in', 'textarea_rows'=>8, 'media_buttons'=>false));
                // Quicktags initialisieren
                echo "<script>if(window.QTags){QTags.addQuickTags('cft_text_logged_in_editor_admin');}</script>";
            } else {
                echo '<textarea name="cft_text_logged_in" rows="5" style="width:100%">'.esc_textarea($text_logged_in).'</textarea>';
            }
            echo '<h2>Text für Gäste</h2>';
            if (function_exists('wp_editor')) {
                wp_editor($text_guest, 'cft_text_guest_editor_admin', array('textarea_name'=>'cft_text_guest', 'textarea_rows'=>8, 'media_buttons'=>false));
                // Quicktags initialisieren
                echo "<script>if(window.QTags){QTags.addQuickTags('cft_text_guest_editor_admin');}</script>";
            } else {
                echo '<textarea name="cft_text_guest" rows="5" style="width:100%">'.esc_textarea($text_guest).'</textarea>';
            }
            echo '<h2>Eigene CSS-Styles (optional)</h2>';
            echo '<textarea name="cft_css" rows="3" style="width:100%">'.esc_textarea($css).'</textarea>';
            echo '<br><button class="button button-primary">Einstellungen speichern</button>';
            echo '</form></div>';
        });
    });
}

function comment_form_text_output(){
    // Fallback: Aktivierungsprüfung direkt über die gespeicherten Optionen
    $settings = get_site_option('postindexer_extensions_settings', []);
    $site_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
    $ext = $settings['comment_form_text'] ?? null;
    $active = isset($ext['active']) ? (int)$ext['active'] : 0;
    $scope = $ext['scope'] ?? 'main';
    $sites = $ext['sites'] ?? [];
    $show = false;
    if ($active) {
        if ($scope === 'network') $show = true;
        elseif ($scope === 'main' && $site_id == $main_site) $show = true;
        elseif ($scope === 'sites' && in_array($site_id, $sites)) $show = true;
    }
    if (!$show) return;
    $css = get_site_option('cft_css', '');
    if ($css) {
        echo '<style type="text/css">'.esc_html($css).'</style>';
    }
    if ( is_user_logged_in() ) {
        echo wp_kses_post(get_site_option('cft_text_logged_in', ''));
    } else {
        echo wp_kses_post(get_site_option('cft_text_guest', ''));
    }
}
add_action('comment_form_after_fields', 'comment_form_text_output');

// Unterstützte Hooks für die Positionierung
$cft_hooks = [
    'comment_form_before' => 'Vor dem Formular',
    'comment_form_top' => 'Am Anfang des Formulars',
    'comment_form_before_fields' => 'Vor den Feldern',
    'comment_form_after_fields' => 'Nach den Feldern',
    'comment_form' => 'Am Ende des Formulars',
    'comment_form_after' => 'Nach dem Formular',
];

function cft_get_selected_hook() {
    $hook = get_site_option('cft_output_hook', 'comment_form_after_fields');
    global $cft_hooks;
    return isset($cft_hooks[$hook]) ? $hook : 'comment_form_after_fields';
}

// Frontend-Hook dynamisch registrieren
if (!is_admin()) {
    $selected_hook = cft_get_selected_hook();
    add_action($selected_hook, 'comment_form_text_output', 10);
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

// Settings-Renderer für Comment Form Text (nur Speicherbutton, keine Beschreibung)
if (!class_exists('Comment_Form_Text_Settings_Renderer')) {
class Comment_Form_Text_Settings_Renderer {
    public function render_settings_form() {
        $text_logged_in = get_site_option('cft_text_logged_in', '');
        $text_guest = get_site_option('cft_text_guest', '');
        $css = get_site_option('cft_css', '');
        ob_start();
        wp_nonce_field('comment_form_text_settings_save', 'comment_form_text_settings_nonce');
        echo '<h3>Text für eingeloggte Nutzer</h3>';
        echo '<textarea name="cft_text_logged_in" rows="4" style="width:100%">'.esc_textarea($text_logged_in).'</textarea>';
        echo '<h3>Text für Gäste</h3>';
        echo '<textarea name="cft_text_guest" rows="4" style="width:100%">'.esc_textarea($text_guest).'</textarea>';
        echo '<h3>Custom CSS</h3>';
        echo '<textarea name="cft_css" rows="4" style="width:100%">'.esc_textarea($css).'</textarea>';
        return ob_get_clean();
    }
}
}

// Sicherstellen, dass die Postindexer_Extensions_Admin-Klasse auch im Frontend verfügbar ist
if (!class_exists('Postindexer_Extensions_Admin')) {
    require_once dirname(dirname(__DIR__)) . '/classes/class.postindexerextensionsadmin.php';
}