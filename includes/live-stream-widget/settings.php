<?php
// Settings-Renderer für Live Stream Widget (Netzwerk-Admin)
if ( !class_exists('Live_Stream_Widget_Settings_Renderer') ) {
class Live_Stream_Widget_Settings_Renderer {
    public function render_settings_form() {
        // KEIN <form> mehr, nur noch die Nonce!
        echo wp_nonce_field('ps_lsw_settings_save','ps_lsw_settings_nonce',true,false);
        // Hier können weitere Felder ergänzt werden
    }
}
}
// Verarbeitung der Einstellungen (Platzhalter, kann später erweitert werden)
if (is_admin() && isset($_POST['ps_lsw_settings_nonce']) && check_admin_referer('ps_lsw_settings_save','ps_lsw_settings_nonce')) {
    // Hier können Einstellungen gespeichert werden
    // update_site_option('live_stream_widget_settings', ...);
    add_action('admin_notices', function(){
        echo '<div class="updated notice is-dismissible"><p>' . __('Live Stream Widget: Einstellungen gespeichert.', 'postindexer') . '</p></div>';
    });
}
