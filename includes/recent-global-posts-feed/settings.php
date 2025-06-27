<?php
// Settings-Renderer für Recent Global Posts Feed (nur Speicherbutton, keine Hinweise)
class Recent_Global_Posts_Feed_Settings_Renderer {
    public function render_settings_form() {
        // KEIN <form> mehr, nur noch die Nonce!
        return wp_nonce_field('ps_extensions_scope_save','ps_extensions_scope_nonce',true,false);
    }
}
