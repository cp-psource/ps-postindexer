<?php
// Settings-Renderer für Recent Global Posts Widget (Netzwerk-Admin)
if ( !class_exists('Recent_Global_Posts_Widget_Settings_Renderer') ) {
class Recent_Global_Posts_Widget_Settings_Renderer {
    public function render_settings_form() {
        // Minimal: Hinweistext und Speichern-Button
        $nonce = wp_nonce_field('ps_rgpw_settings_save','ps_rgpw_settings_nonce',true,false);
        echo '<form method="post">';
        echo $nonce;
        echo '<button type="submit" class="button button-primary">Einstellungen speichern</button>';
        echo '</form>';
    }
}
}
// Verarbeitung der Einstellungen (Platzhalter, kann später erweitert werden)
if (is_admin() && isset($_POST['ps_rgpw_settings_nonce']) && check_admin_referer('ps_rgpw_settings_save','ps_rgpw_settings_nonce')) {
    // Hier können Einstellungen gespeichert werden
    // update_site_option('recent_global_posts_widget_settings', ...);
    add_action('admin_notices', function(){
        echo '<div class="updated notice is-dismissible"><p>Recent Global Posts Widget: Einstellungen gespeichert.</p></div>';
    });
}
