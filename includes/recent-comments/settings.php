<?php
// Settings-Renderer für Netzwerk Kommentare (Shortcode, zentrale Settings)
if (!class_exists('Recent_Comments_Settings_Renderer')) {
class Recent_Comments_Settings_Renderer {
            public function render_settings_form() {
            $settings = get_site_option('recent_global_comments_settings', []);
            $defaults = [
                'title' => '',
                'number' => 10,
                'content_characters' => 50,
                'avatars' => 'show',
                'avatar_size' => 32
            ];
            $settings = is_array($settings) ? array_merge($defaults, $settings) : $defaults;
            echo wp_nonce_field('ps_rgcw_settings_save','ps_rgcw_settings_nonce',true,false);
            echo '<div style="background:#fff;border:1px solid #e5e5e5;padding:2em 2em 1em 2em;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:2em;">';
            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2em;">';
            // Titel
            echo '<div><label for="rgcw_title" style="font-weight:bold;">Titel</label><br>';
            echo '<input type="text" name="recent_global_comments_settings[title]" id="rgcw_title" value="' . esc_attr($settings['title']) . '" style="min-width:220px;">';
            echo '</div>';
            // Anzahl
            echo '<div><label for="rgcw_number" style="font-weight:bold;">Anzahl Kommentare</label><br>';
            echo '<input type="number" name="recent_global_comments_settings[number]" id="rgcw_number" value="' . esc_attr($settings['number']) . '" min="1" max="50" style="width:80px;">';
            echo '</div>';
            // Zeichen
            echo '<div><label for="rgcw_content_characters" style="font-weight:bold;">Zeichen pro Kommentar</label><br>';
            echo '<input type="number" name="recent_global_comments_settings[content_characters]" id="rgcw_content_characters" value="' . esc_attr($settings['content_characters']) . '" min="10" max="500" style="width:80px;">';
            echo '</div>';
            // Avatare anzeigen
            echo '<div><label for="rgcw_avatars" style="font-weight:bold;">Avatare anzeigen</label><br>';
            echo '<select name="recent_global_comments_settings[avatars]" id="rgcw_avatars" style="min-width:120px;">';
            echo '<option value="show"' . selected($settings['avatars'], 'show', false) . '>Ja</option>';
            echo '<option value="hide"' . selected($settings['avatars'], 'hide', false) . '>Nein</option>';
            echo '</select></div>';
            // Avatar-Größe
            echo '<div><label for="rgcw_avatar_size" style="font-weight:bold;">Avatar-Größe</label><br>';
            echo '<select name="recent_global_comments_settings[avatar_size]" id="rgcw_avatar_size" style="min-width:120px;">';
            foreach ([16,32,48,96,128] as $size) {
                echo '<option value="'.$size.'"' . selected($settings['avatar_size'], $size, false) . '>'.$size.'px</option>';
            }
            echo '</select></div>';
            // Wrapper: global_before
            echo '<div><label for="rgcw_global_before" style="font-weight:bold;">HTML vor der gesamten Liste (global_before)</label><br>';
            echo '<input type="text" name="recent_global_comments_settings[global_before]" id="rgcw_global_before" value="' . esc_attr($settings['global_before'] ?? '') . '" style="min-width:220px;">';
            echo '</div>';
            // Wrapper: global_after
            echo '<div><label for="rgcw_global_after" style="font-weight:bold;">HTML nach der gesamten Liste (global_after)</label><br>';
            echo '<input type="text" name="recent_global_comments_settings[global_after]" id="rgcw_global_after" value="' . esc_attr($settings['global_after'] ?? '') . '" style="min-width:220px;">';
            echo '</div>';
            // Wrapper: before
            echo '<div><label for="rgcw_before" style="font-weight:bold;">HTML vor jedem Kommentar (before)</label><br>';
            echo '<input type="text" name="recent_global_comments_settings[before]" id="rgcw_before" value="' . esc_attr($settings['before'] ?? '') . '" style="min-width:220px;">';
            echo '</div>';
            // Wrapper: after
            echo '<div><label for="rgcw_after" style="font-weight:bold;">HTML nach jedem Kommentar (after)</label><br>';
            echo '<input type="text" name="recent_global_comments_settings[after]" id="rgcw_after" value="' . esc_attr($settings['after'] ?? '') . '" style="min-width:220px;">';
            echo '</div>';
            // Link-Text
            echo '<div><label for="rgcw_link" style="font-weight:bold;">Link-Text (link)</label><br>';
            echo '<input type="text" name="recent_global_comments_settings[link]" id="rgcw_link" value="' . esc_attr($settings['link'] ?? '') . '" style="min-width:220px;">';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
}
// Verarbeitung der Einstellungen
if (is_admin() && isset($_POST['recent_comments_settings_nonce']) && check_admin_referer('recent_comments_settings_save','recent_comments_settings_nonce')) {
    if (isset($_POST['recent_comments_settings']) && is_array($_POST['recent_comments_settings'])) {
        update_site_option('recent_comments_settings', $_POST['recent_comments_settings']);
        add_action('admin_notices', function(){
            echo '<div class="updated notice is-dismissible"><p>Netzwerk Kommentare: Einstellungen gespeichert.</p></div>';
        });
    }
}
