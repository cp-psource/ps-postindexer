<?php

/**
 * Plugin main class
 */
class User_Activity {

	/**
	 * Current version of the plugin
	 */
	private $current_version = '1.1';

	private $page_id;

	private $tables_checked = false;

	/**
	 * Constructor
	 */
	function __construct() {

		add_action( 'admin_init', array( $this, 'init' ) );

		if ( is_multisite() ) {
			// add_action( 'admin_menu', array( $this, 'pre_3_1_network_admin_page' ) ); // entfernt, da Methode nicht mehr benötigt
			// add_action( 'network_admin_menu', array( $this, 'network_admin_page' ) ); // entfernt, da Methode nicht mehr benötigt
		} else {
			add_action( 'admin_menu', array( $this, 'admin_page' ) );
		}

		add_action( 'admin_footer', array( $this, 'global_db_sync' ) );
		add_action( 'wp_footer', array( $this, 'global_db_sync' ) );

		add_action( 'ua_remove_old_activity', array( $this, 'remove_old_activity' ) );

		global $wpmudev_notices;
		$wpmudev_notices[] = array(
			'id' => 3,
			'name' => __( 'Nutzer-Aktivität', 'postindexer' ),
			'screens' => array( 'settings_page_user_activity_main-network' ),
		);
	}

	/**
	 * Initializer
	 */
	function init() {

		$current_version = get_site_option( 'user_activity_version' );
		if ( ! $current_version || version_compare( $current_version, $this->current_version ) == -1 ) {
			update_site_option( 'user_activity_version', $this->current_version );
			$this->install();
		}

		// Do we have to remove old user activity?
		$transient = get_site_transient( 'user_activity_remove_old_activity' );
		if ( ! $transient ) {
			$this->remove_old_activity();
			set_site_transient( 'user_activity_remove_old_activity', true, 86400 );
		}
	}


	private function remove_old_activity() {
		global $wpdb;

		$last_31_days = time() - 2678400;

		$pq = $wpdb->prepare("DELETE FROM {$wpdb->base_prefix}user_activity_log WHERE visit_date < %d", $last_31_days);
		$wpdb->query( $pq );
	}

	/**
	 * Create plugin tables
	 */
	function install() {
		global $wpdb;

		if ( @is_file( ABSPATH . '/wp-admin/includes/upgrade.php' ) ) {
			include_once ABSPATH . '/wp-admin/includes/upgrade.php';
		} else {
			die( __( "Unable to find 'wp-admin/upgrade-functions.php' and 'wp-admin/includes/upgrade.php'", 'user_activity' ) );
		}

		$db_charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$db_charset_collate .= "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$db_charset_collate .= " COLLATE $wpdb->collate";
		}

		$table = $wpdb->base_prefix . 'user_activity';
		$sql = "CREATE TABLE $table (
			active_ID bigint(20) unsigned NOT NULL auto_increment,
			user_ID bigint(35) NOT NULL default '0',
			last_active bigint(35) NOT NULL default '0',
			PRIMARY KEY  (active_ID)
		      ) ENGINE=MyISAM $db_charset_collate;";
		dbDelta( $sql );

		$table = $wpdb->base_prefix . 'user_activity_log';
		$sql = "CREATE TABLE $table (
			log_ID bigint(20) unsigned NOT NULL auto_increment,
			user_ID bigint(35) NOT NULL default '0',
			visit_date bigint(35) NOT NULL default '0',
			PRIMARY KEY  (log_ID),
			KEY visit_date (visit_date),
			KEY user_id (user_ID)
		      ) ENGINE=MyISAM $db_charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create or update current user activity entry
	 */
	function global_db_sync() {
		$this->ensure_tables_exist();
		global $wpdb, $current_user;

		if ( ! $current_user->ID ) {
			return;
		}

		$tmp_user_activity_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_activity WHERE user_ID = '%d'", $current_user->ID ) );

		if ( '0' == $tmp_user_activity_count ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}user_activity ( user_ID, last_active ) VALUES ( '%d', '%d' )", $current_user->ID, time() ) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->base_prefix}user_activity SET last_active = '%d' WHERE user_ID = '%d'", time(), $current_user->ID ) );
		}

		// We'll count a visit every 30 minutes, so a refreshing before 30min will not be considered a visit
		$visited = get_site_transient( 'user_activity_' . $current_user->ID );

		if ( ! $visited ) {
			$wpdb->insert(
				$wpdb->base_prefix . 'user_activity_log',
				array(
					'user_ID' => $current_user->ID,
					'visit_date' => time()
				),
				array( '%d', '%d' )
			);
			set_site_transient( 'user_activity_' . $current_user->ID, true, 1800 );
		}
	}

	/**
	 * Get activity from db for a set period of type
	 * @param string|int $tmp_period
	 */
	function get_activity( $tmp_period ) {
		$this->ensure_tables_exist();
		global $wpdb, $current_user;

		$tmp_period = ( $tmp_period == '' || $tmp_period == 0 ) ? 1 : $tmp_period;
		$tmp_period = $tmp_period * 60;
		$user_current_stamp = time();
		$tmp_stamp = $user_current_stamp - $tmp_period;
		$tmp_output = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_activity WHERE last_active > '%d'", $tmp_stamp ) );

		echo $tmp_output;
	}

	/**
	 * Add admin page for singlesite
	 */
	function admin_page() {
		$this->add_admin_menu( 'users.php', 'edit_users' );
	}

	public function setup_meta_boxes() {
	    wp_enqueue_script( 'postbox' );
	}

	/**
	 * Admin page output.
	 */
	function page_main_output() {
		$this->ensure_tables_exist();
		global $wpdb, $wp_roles, $current_user, $wp_meta_boxes;

		// Allow access for users with correct permissions only
		if ( is_multisite() && ! current_user_can( 'manage_network_options' ) ) {
			die( __( 'Netter Versuch...', 'postindexer' ) );
		} elseif ( ! is_multisite() && ! current_user_can( 'manage_options' ) ) {
			die( __( 'Netter Versuch...', 'postindexer' ) );
		}

		echo '<div class="wrap">';

		//echo '<div class="postbox">';
		//do_meta_boxes( 'user_activity_main', 'advanced', 'dfdff' );
		//echo '</div>';
		$current_stamp = time();

		$current_five_minutes = $current_stamp - 300;
		$current_hour = $current_stamp - 3600;
		$current_day = $current_stamp - 86400;
		$current_week = $current_stamp - 604800;
		$current_month = $current_stamp - 2592000;

		$five_minutes = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_activity WHERE last_active > '$current_five_minutes'" );
		$hour = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_activity WHERE last_active > '$current_hour'" );
		$day = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_activity WHERE last_active > '$current_day'" );
		$week = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_activity WHERE last_active > '$current_week'" );
		$month = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_activity WHERE last_active > '$current_month'" );

		$now = time();
		$last_day = $now - 86400;
		$today_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COUNT(log_ID) visits,user_ID FROM {$wpdb->base_prefix}user_activity_log WHERE visit_date > %d GROUP BY user_ID ORDER BY visits DESC LIMIT 10",
				$last_day
			)
		);

		$last_7_days = $now - 604800;
		$last_7_days_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COUNT(log_ID) visits,user_ID FROM {$wpdb->base_prefix}user_activity_log WHERE visit_date > %d GROUP BY user_ID ORDER BY visits DESC LIMIT 10",
				$last_7_days
			)
		);


		$last_month = $now - 2592000;
		$last_month_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COUNT(log_ID) visits,user_ID FROM {$wpdb->base_prefix}user_activity_log WHERE visit_date > %d GROUP BY user_ID ORDER BY visits DESC LIMIT 10",
				$last_month
			)
		);

		?>
		<h2><?php _e( 'Nutzer-Aktivität', 'postindexer' ); ?></h2>

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-1">
				<div id="postbox-container-1" class="postbox-container">
					<div id="advanced-sortables" class="meta-box-sortables ui-sortable">
						<div id="ua-totals-box" class="postbox" style="display: block;">
							<div class="handlediv" title="Haz clic para cambiar"><br></div>
							<h3 class="hndle"><span><?php _e( 'Gesamt', 'postindexer' ); ?></span></h3>
							<div class="inside">
								<table class="form-table">
									<thead>
									<th><h4><?php _e( 'Aktive Nutzer in den letzten', 'postindexer' ); ?></h4></th>
									<th><h4 class="ua-visits"><?php _e( 'Eindeutige Besuche', 'postindexer' ); ?></h4></th>
									</thead>
									<tbody>
									<tr>
										<td><?php _e( 'Fünf Minuten', 'postindexer' ); ?></td>
										<td class="ua-visits"><?php echo $five_minutes; ?></td>
									</tr>
									<tr>
										<td><?php _e( 'Stunde', 'postindexer' ); ?></td>
										<td class="ua-visits"><?php echo $hour; ?></td>
									</tr>
									<tr>
										<td><?php _e( 'Tag', 'postindexer' ); ?></td>
										<td class="ua-visits"><?php echo $day; ?></td>
									</tr>
									<tr>
										<td><?php _e( 'Woche', 'postindexer' ); ?></td>
										<td class="ua-visits"><?php echo $week; ?></td>
									</tr>
									<tr>
										<td><?php _e( '30 Tage', 'postindexer' ); ?></td>
										<td class="ua-visits"><?php echo $month; ?></td>
									</tr>
									</tbody>
								</table>
							</div>
						</div>

						<div id="ua-today-box" class="postbox" style="display: block;">
							<div class="handlediv" title="Haz clic para cambiar"><br></div>
							<h3 class="hndle"><span><?php _e( 'Heute', 'postindexer' ); ?></span></h3>
							<div class="inside">
								<table class="form-table">
									<thead>
									<th><h4><?php _e( 'Nutzer', 'postindexer' ); ?></h4></th>
									<th><h4 class="ua-visits"><?php _e( 'Besuche', 'postindexer' ); ?></h4></th>
									</thead>
									<tbody>
									<?php foreach ( $today_results as $row ): ?>
										<?php
										$user = get_userdata( $row->user_ID );
										$nicename = isset( $user->data->user_nicename ) ? $user->data->user_nicename : __( 'Unbekannt', 'postindexer' );
										$user_link = $user ? '<a href="' . network_admin_url( 'user-edit.php?user_id=' . $row->user_ID ) . '">' . $nicename . '</a>' : $nicename;
										?>
										<tr>
											<td><?php echo $user_link; ?></td>
											<td class="ua-visits"><?php echo $row->visits; ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>

								</table>
							</div>
						</div>

						<div id="ua-seven-days-box" class="postbox" style="display: block;">
							<div class="handlediv" title="Haz clic para cambiar"><br></div>
							<h3 class="hndle"><span><?php _e( 'Letzte 7 Tage', 'postindexer' ); ?></span></h3>
							<div class="inside">
								<table class="form-table">
									<thead>
									<th><h4><?php _e( 'Nutzer', 'postindexer' ); ?></h4></th>
									<th><h4 class="ua-visits"><?php _e( 'Besuche', 'postindexer' ); ?></h4></th>
									</thead>
									<tbody>
									<?php foreach ( $last_7_days_results as $row ): ?>
										<?php
										$user = get_userdata( $row->user_ID );
										$nicename = isset( $user->data->user_nicename ) ? $user->data->user_nicename : __( 'Unbekannt', 'postindexer' );
										$user_link = $user ? '<a href="' . network_admin_url( 'user-edit.php?user_id=' . $row->user_ID ) . '">' . $nicename . '</a>' : $nicename;
										?>
										<tr>
											<td><?php echo $user_link; ?></td>
											<td class="ua-visits"><?php echo $row->visits; ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>

								</table>
							</div>
						</div>

						<div id="ua-30-days-box" class="postbox" style="display: block;">
							<div class="handlediv" title="Haz clic para cambiar"><br></div>
							<h3 class="hndle"><span><?php _e( 'Letzte 30 Tage', 'postindexer' ); ?></span></h3>
							<div class="inside">
								<table class="form-table">
									<thead>
									<th><h4><?php _e( 'Nutzer', 'postindexer' ); ?></h4></th>
									<th><h4 class="ua-visits"><?php _e( 'Besuche', 'postindexer' ); ?></h4></th>
									</thead>
									<tbody>
									<?php foreach ( $last_month_results as $row ): ?>
										<?php
										$user = get_userdata( $row->user_ID );
										$nicename = isset( $user->data->user_nicename ) ? $user->data->user_nicename : __( 'Unbekannt', 'postindexer' );
										$user_link = $user ? '<a href="' . network_admin_url( 'user-edit.php?user_id=' . $row->user_ID ) . '">' . $nicename . '</a>' : $nicename;
										?>
										<tr>
											<td><?php echo $user_link; ?></td>
											<td class="ua-visits"><?php echo $row->visits; ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>

								</table>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>

		<style>

		.form-table tr {
			border-bottom: 1px solid #DEDEDE;
		}

		.form-table tr:last-child {
			border-bottom: none;
		}

		.postbox {
			width: 45%;
			margin-right: 4%;
			float: left;
			min-width: 225px;
		}

		.form-table h4 {
			line-height: 1.7em;
			font-weight: normal;
			font-size: 13px;
			margin: 0 0 .2em;
			padding: 0;
			font-family: Georgia, "Times New Roman", "Bitstream Charter", Times, serif;
		}

		.ua-visits {
			text-align: center;
		}

		</style>
		<?php
	}

	/**
	 * Prüft, ob die User-Activity-Tabellen existieren, und legt sie ggf. an
	 */
	private function ensure_tables_exist() {
		global $wpdb;
		if ($this->tables_checked) return;
		$this->tables_checked = true;
		$tables = [
			$wpdb->base_prefix . 'user_activity',
			$wpdb->base_prefix . 'user_activity_log',
		];
		$missing = false;
		foreach ($tables as $table) {
			if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) != $table) {
				$missing = true;
				break;
			}
		}
		if ($missing) {
			$this->install();
		}
	}

}

$user_activity = new User_Activity();

/**
 * Display number of active users for a specific period of time
 *
 * @param $tmp_period
 */
function display_user_activity( $tmp_period ) {
	global $user_activity;
	$user_activity->get_activity( $tmp_period );
}

/**
 * Display last active users
 *
 * @param int    $minutes
 * @param int    $limit
 * @param string $global_before
 * @param string $before
 * @param string $global_after
 * @param string $after
 * @param string $avatars
 * @param int    $avatar_size
 */
function user_activity_output( $minutes = 5, $limit = 10, $global_before = '', $before = '', $global_after = '', $after = '', $avatars = 'yes', $avatar_size = 32 ) {
	global $wpdb;

	$user_activity_current_stamp = time();
	$user_activity_seconds = $minutes * 60;
	$user_activity_stamp = $user_activity_current_stamp - $user_activity_seconds;
	$query = $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}user_activity WHERE last_active > '%d' LIMIT %d", $user_activity_stamp, (int) $limit );
	$active_users = $wpdb->get_results( $query, ARRAY_A );

	if ( count( $active_users ) > 0 ) {
		echo $global_before;

		foreach ( $active_users as $active_user ) {
			echo $before;

			$user = get_user_by( 'id', $active_user['user_ID'] );
			$display_name = empty( $user->display_name ) ? $user->user_login : $user->display_name;

			$primary_blog = get_active_blog_for_user( $active_user['user_ID'] );

			if ( 'yes' == $avatars ) {
				echo get_avatar( $active_user['user_ID'], $avatar_size, get_option( 'avatar_default' ) );
				printf(
					' <a href="%s" style="text-decoration: none; border: none;">%s</a>',
					esc_url( 'http://' . $primary_blog->domain . $primary_blog->path ), esc_html( $display_name )
				);

			} else {
				printf( '<a href="%s">%s</a>', get_site_url( $primary_blog->blog_id, '/' ), $display_name );
			}

			echo $after;
		}

		echo $global_after;
	}
}


