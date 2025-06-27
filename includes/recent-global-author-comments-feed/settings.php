<?php
// Settings-Renderer für Global Author Comments Feed (nur Speicherbutton, keine Beschreibung)
if (!class_exists('Recent_Global_Author_Comments_Feed_Settings_Renderer')) {
class Recent_Global_Author_Comments_Feed_Settings_Renderer {
    public function render_settings_form() {
        // KEIN <form> mehr, nur noch die Nonce!
        return wp_nonce_field('recent_global_author_comments_feed_settings_save', 'recent_global_author_comments_feed_settings_nonce', true, false);
    }
}
}
