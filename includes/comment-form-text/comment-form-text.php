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
    // Das Submenü und die Einstellungsseite werden nicht mehr benötigt und entfernt.
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
    'comment_form_before' => __('Vor dem Formular', 'postindexer'),
    'comment_form_top' => __('Am Anfang des Formulars', 'postindexer'),
    'comment_form_before_fields' => __('Vor den Feldern', 'postindexer'),
    'comment_form_after_fields' => __('Nach den Feldern', 'postindexer'),
    'comment_form' => __('Am Ende des Formulars', 'postindexer'),
    'comment_form_after' => __('Nach dem Formular', 'postindexer'),
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
        echo '<h3>' . esc_html__('Text für eingeloggte Nutzer', 'postindexer') . '</h3>';
        echo '<textarea name="cft_text_logged_in" rows="4" style="width:100%">'.esc_textarea($text_logged_in).'</textarea>';
        echo '<h3>' . esc_html__('Text für Gäste', 'postindexer') . '</h3>';
        echo '<textarea name="cft_text_guest" rows="4" style="width:100%">'.esc_textarea($text_guest).'</textarea>';
        echo '<h3>' . esc_html__('Eigenes CSS', 'postindexer') . '</h3>';
        echo '<textarea name="cft_css" rows="4" style="width:100%">'.esc_textarea($css).'</textarea>';
        return ob_get_clean();
    }
}
}

// Sicherstellen, dass die Postindexer_Extensions_Admin-Klasse auch im Frontend verfügbar ist
if (!class_exists('Postindexer_Extensions_Admin')) {
    require_once dirname(dirname(__DIR__)) . '/classes/class.postindexerextensionsadmin.php';
}