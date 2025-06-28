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
        // Custom CSS für Grid-Layout
        echo '<style>
        .pi-monitoring-grid { display: grid; grid-template-columns: repeat(2, minmax(320px, 1fr)); gap: 2em; margin-top: 2em; }
        .pi-monitoring-grid > div { background: #fff; border: 1px solid #e5e5e5; border-radius: 10px; padding: 2em 2em 1em 2em; box-shadow: 0 2px 8px rgba(0,0,0,0.04); min-width: 0; }
        @media (max-width: 900px) { .pi-monitoring-grid { grid-template-columns: 1fr; } }
        /* Letztes Grid-Element auf volle Breite, wenn ungerade Anzahl */
        .pi-monitoring-grid > div:last-child:nth-child(odd) {
            grid-column: 1 / -1;
        }
        </style>';

        // Blog Activity separat und prominent anzeigen
        $blog_activity_tool = null;
        $grid_tools = [];
        foreach ($this->tools as $tool) {
            if ($tool['key'] === 'blog_activity') {
                $blog_activity_tool = $tool;
            } else {
                $grid_tools[] = $tool;
            }
        }
        if ($blog_activity_tool) {
            echo '<div style="background:#fff;border:2px solid #0073aa;border-radius:12px;padding:2.5em 2em 1.5em 2em;box-shadow:0 4px 16px rgba(0,0,0,0.07);margin-bottom:2.5em;">';
            echo '<h2 style="margin-top:0;color:#0073aa;font-size:2em;">' . esc_html($blog_activity_tool['name']) . '</h2>';
            echo '<p style="color:#444;font-size:1.1em;">' . esc_html($blog_activity_tool['desc']) . '</p>';
            if (file_exists($blog_activity_tool['file'])) {
                require_once $blog_activity_tool['file'];
                if (class_exists($blog_activity_tool['class'])) {
                    $instance = new $blog_activity_tool['class']();
                    if (method_exists($instance, $blog_activity_tool['method'])) {
                        ob_start();
                        $instance->{$blog_activity_tool['method']}();
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

        // Grid mit maximal 2 Spalten, Tools in Originalreihenfolge (außer Blog Activity)
        echo '<div class="pi-monitoring-grid">';
        foreach ($grid_tools as $tool) {
            $tool_content = '';
            $has_real_content = false;
            if (file_exists($tool['file'])) {
                require_once $tool['file'];
                if (class_exists($tool['class'])) {
                    if ($tool['class'] === 'Activity_Reports') {
                        $instance = Activity_Reports::instance();
                    } else {
                        $instance = new $tool['class']();
                    }
                    if ($tool['class'] === 'UserReports') {
                        global $user_reports;
                        $user_reports = $instance;
                    }
                    if (method_exists($instance, $tool['method'])) {
                        ob_start();
                        $instance->{$tool['method']}();
                        $tool_content = ob_get_clean();
                        // Sichtbarer Output? (HTML, Whitespace, Zeilenumbrüche entfernen)
                        $plain = trim(preg_replace('/\s+/', '', strip_tags($tool_content)));
                        if ($plain !== '') {
                            $has_real_content = true;
                        }
                    }
                }
            }
            if ($has_real_content) {
                echo '<div>';
                echo '<h2 style="margin-top:0;">' . esc_html($tool['name']) . '</h2>';
                echo '<p style="color:#444;">' . esc_html($tool['desc']) . '</p>';
                echo $tool_content;
                echo '</div>';
            }
        }
        echo '</div>';
    }
}

}
