<?php
// Settings-Renderer für Comments Control (IP-Whitelist/Blacklist)
if (!class_exists('Comments_Control_Settings_Renderer')) {
class Comments_Control_Settings_Renderer {
    public function render_settings_form() {
        // KEIN <form> mehr, nur noch die Felder und Nonce!
        wp_nonce_field('comments_control_settings_save', 'comments_control_settings_nonce');
        echo '<h3>Kommentare</h3>';
        echo '<table class="form-table">';
        echo '<tr valign="top"><td colspan="2">Erlaubte Regeln gelten vor gesperrten Regeln</td></tr>';
        echo '<tr valign="top">';
        echo '<th scope="row">IP-Whitelist</th>';
        echo '<td>';
        $allowed = stripslashes(get_site_option('limit_comments_allowed_ips'));
        echo "<textarea name='limit_comments_allowed_ips' id='limit_comments_allowed_ips' style='width:95%;' rows='7' cols='40'>";
        echo esc_textarea($allowed);
        echo "</textarea>";
        echo "<br/>IPs, für die Kommentare nicht gedrosselt werden. Eine IP pro Zeile oder Komma-getrennt.<br/>";
        echo '</td></tr>';
        echo '<tr valign="top">';
        echo '<th scope="row">IP-Blacklist</th>';
        echo '<td>';
        $denied = stripslashes(get_site_option('limit_comments_denied_ips'));
        echo "<textarea name='limit_comments_denied_ips' id='limit_comments_denied_ips' style='width:95%;' rows='7' cols='40'>";
        echo esc_textarea($denied);
        echo "</textarea>";
        echo "<br/>IPs, für die Kommentare immer abgelehnt werden. Eine IP pro Zeile oder Komma-getrennt.<br/>";
        echo '</td></tr>';
        echo '</table>';
    }
}
}
