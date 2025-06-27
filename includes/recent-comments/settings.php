<?php
// Settings-Renderer für Recent Comments (nur Speicherbutton, keine Beschreibung)
if (!class_exists('Recent_Comments_Settings_Renderer')) {
class Recent_Comments_Settings_Renderer {
    public function render_settings_form($comment_indexer_active = true) {
        // KEIN <form> mehr, nur noch die Nonce!
        return wp_nonce_field('recent_comments_settings_save', 'recent_comments_settings_nonce', true, false);
    }
}
}
