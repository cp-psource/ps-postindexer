<?php
// Platzhalter für das Settings-Panel des Recent Global Comments Widget
if (!class_exists('Recent_Global_Comments_Widget_Settings_Renderer')) {
    class Recent_Global_Comments_Widget_Settings_Renderer {
        public function render_settings_form() {
            return '<div style="color:#888;">(Noch keine Einstellungen für das Recent Global Comments Widget vorhanden.)</div>';
        }
    }
}
