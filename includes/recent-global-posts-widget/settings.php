<?php
// Settings-Renderer für Neueste Netzwerk Beiträge (Netzwerk-Admin)
if ( !class_exists('Recent_Global_Posts_Widget_Settings_Renderer') ) {
class Recent_Global_Posts_Widget_Settings_Renderer {
    public function render_settings_form() {
        // KEIN <form> mehr, nur noch die Felder!
        $nonce = wp_nonce_field('ps_extension_settings_save_recent_global_posts_widget','ps_extension_settings_nonce_recent_global_posts_widget',true,false);
        echo $nonce;
        // Hier können weitere Felder ergänzt werden
    }
    /**
     * Speichert die Einstellungen aus dem Card-Formular (klassischer POST, kein AJAX)
     */
    public function process_settings_form() {
        if (
            isset($_POST['ps_extension_settings_nonce_recent_global_posts_widget']) &&
            wp_verify_nonce($_POST['ps_extension_settings_nonce_recent_global_posts_widget'], 'ps_extension_settings_save_recent_global_posts_widget')
        ) {
            // Hier können Einstellungen gespeichert werden
            // update_site_option('recent_global_posts_widget_settings', ...);
        }
    }
}
// Verarbeitung der Einstellungen (Platzhalter, kann später erweitert werden)
if (is_admin() && isset($_POST['ps_rgpw_settings_nonce']) && check_admin_referer('ps_rgpw_settings_save','ps_rgpw_settings_nonce')) {
    // Hier können Einstellungen gespeichert werden
    // update_site_option('recent_global_posts_widget_settings', ...);
    add_action('admin_notices', function(){
        echo '<div class="updated notice is-dismissible"><p>Neueste Netzwerk Beiträge: Einstellungen gespeichert.</p></div>';
    });
}
// Fehlende schließende Klammer für die Klasse ergänzt
}
