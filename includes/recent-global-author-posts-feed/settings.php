<?php

if (!class_exists('Recent_Global_Author_Posts_Feed_Settings_Renderer')) {
    class Recent_Global_Author_Posts_Feed_Settings_Renderer {
        public function render_settings_form() {
            // KEIN <form> mehr, nur noch die Nonce!
            return wp_nonce_field('ps_rgapf_settings_save','ps_rgapf_settings_nonce',true,false);
        }
    }
}
