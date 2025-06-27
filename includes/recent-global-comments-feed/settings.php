<?php
// Settings-Renderer für Recent Global Comments Feed (nur Speicherbutton, keine Hinweise)
class Recent_Global_Comments_Feed_Settings_Renderer {
    public function render_settings_form() {
        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('ps_extensions_scope_save','ps_extensions_scope_nonce'); ?>
            <button type="submit" class="button button-primary">Einstellungen speichern</button>
        </form>
        <?php
        return ob_get_clean();
    }
}
