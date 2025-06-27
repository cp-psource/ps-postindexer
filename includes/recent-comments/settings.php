<?php
// Settings-Renderer für Recent Comments (nur Speicherbutton, keine Beschreibung)
if (!class_exists('Recent_Comments_Settings_Renderer')) {
class Recent_Comments_Settings_Renderer {
    public function render_settings_form($comment_indexer_active = true) {
        $disabled = !$comment_indexer_active ? 'disabled' : '';
        echo '<form method="post">';
        wp_nonce_field('recent_comments_settings_save', 'recent_comments_settings_nonce');
        echo '<button type="submit" class="button button-primary" '.$disabled.'>Einstellungen speichern</button>';
        if (!$comment_indexer_active) {
            echo '<div style="color:#c00;font-weight:bold;margin-top:1em;">Diese Erweiterung benötigt den Comment Indexer.</div>';
        }
        echo '</form>';
    }
}
}
