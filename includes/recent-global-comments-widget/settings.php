<?php
// Settings-Renderer für das Recent Global Comments Widget (Netzwerk-Admin)
if (!class_exists('Recent_Global_Comments_Widget_Settings_Renderer')) {
    class Recent_Global_Comments_Widget_Settings_Renderer {
        public function render_settings_form() {
            // Keine eigenen Settings mehr für das Widget
            return '';
        }
    }
}
// Verarbeitung der Einstellungen entfällt, da keine eigenen Settings mehr
