<?php

if (!class_exists('Recent_Global_Author_Posts_Feed_Settings_Renderer')) {
    class Recent_Global_Author_Posts_Feed_Settings_Renderer {
        public function render_settings_form() {
            ob_start();
            echo '<form method="post">';
            wp_nonce_field('ps_rgapf_settings_save','ps_rgapf_settings_nonce');
            echo '<div style="background:#fff;border:1px solid #e5e5e5;padding:2em 2em 1em 2em;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:2em;">';
            submit_button('Einstellungen speichern');
            echo '</div>';
            echo '</form>';
            return ob_get_clean();
        }
    }
}
