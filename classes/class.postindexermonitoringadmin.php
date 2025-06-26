<?php

if ( ! class_exists( 'Postindexer_Monitoring_Admin' ) ) {

class Postindexer_Monitoring_Admin {

    private $tools = [];

    public function __construct() {
        // Monitoring-Tools registrieren (weitere können hier ergänzt werden)
        $this->tools = [
            [
                'key' => 'reports',
                'name' => 'Netzwerk Reports',
                'desc' => 'Statistiken und Aktivitäten über das gesamte Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/reports/reports.php',
                'class' => 'Activity_Reports',
                'method' => 'page_output',
            ],
            [
                'key' => 'user_reports',
                'name' => 'User Reports',
                'desc' => 'Berichte und Statistiken zur Nutzeraktivität im Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/user-reports/user-reports.php',
                'class' => 'UserReports',
                'method' => 'user_reports_admin_show_panel',
            ],
            [
                'key' => 'blog_activity',
                'name' => 'Blog Activity',
                'desc' => 'Aktivitätsstatistiken zu Blogs, Beiträgen und Kommentaren im Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/blog-activity/blog-activity.php',
                'class' => 'Blog_Activity',
                'method' => 'page_main_output',
            ],
            [
                'key' => 'content_monitor',
                'name' => 'Content Monitor',
                'desc' => 'Überwacht und meldet neue oder geänderte Inhalte im Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/content-monitor/content-monitor.php',
                'class' => 'Content_Monitor',
                'method' => 'page_main_output',
            ],
            [
                'key' => 'user_activity',
                'name' => 'User Activity',
                'desc' => 'Zeigt Nutzeraktivitäten und Netzwerk-Logins an.',
                'file' => dirname(__DIR__) . '/includes/user-activity/user-activity.php',
                'class' => 'User_Activity',
                'method' => 'page_main_output',
            ],
            // Weitere Tools können hier ergänzt werden
        ];
    }

    public function render_monitoring_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Monitoring', 'postindexer' ) . '</h1>';
        echo '<p>Hier findest du alle Tools und Statistiken rund um Monitoring, Netzwerk-Statistiken und Auswertungen.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:2em;margin-top:2em;">';
        foreach ($this->tools as $tool) {
            echo '<div style="background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:2em 2em 1em 2em;box-shadow:0 2px 8px rgba(0,0,0,0.04);">';
            echo '<h2 style="margin-top:0;">' . esc_html($tool['name']) . '</h2>';
            echo '<p style="color:#444;">' . esc_html($tool['desc']) . '</p>';
            if (file_exists($tool['file'])) {
                require_once $tool['file'];
                if (class_exists($tool['class'])) {
                    $instance = new $tool['class']();
                    // Sicherstellen, dass das globale Objekt für UserReports gesetzt ist
                    if ($tool['class'] === 'UserReports') {
                        global $user_reports;
                        $user_reports = $instance;
                    }
                    if (method_exists($instance, $tool['method'])) {
                        ob_start();
                        $instance->{$tool['method']}();
                        echo ob_get_clean();
                    } else {
                        echo '<div style="color:#888;">Keine Ausgabemethode gefunden.</div>';
                    }
                } else {
                    echo '<div style="color:#888;">Klasse nicht gefunden.</div>';
                }
            } else {
                echo '<div style="color:#888;">Datei nicht gefunden.</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}

}
