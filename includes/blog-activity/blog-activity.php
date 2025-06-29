<?php

if( !is_multisite() )
	exit( __( 'Das Blog-Aktivitäts-Plugin funktioniert nur mit WordPress Multisite.', 'postindexer' ) );

/**
 * Plugin main class
 **/
class Blog_Activity {

	/**
	 * Current version of the plugin
	 **/
	var $current_version = '1.1.6';

	private $tables_checked = false;

	/**
	 * PHP 5 constructor
	 **/
	function __construct() {
		global $wp_version;
		add_action( 'admin_init', array( &$this, 'setup' ) );
		add_action( 'comment_post', array( &$this, 'blog_global_db_sync' ) );
		add_action( 'save_post', array( &$this, 'blog_global_db_sync' ) );
		add_action( 'comment_post', array( &$this, 'comment_global_db_sync' ) );
		add_action( 'save_post', array( &$this, 'post_global_db_sync' ) );
		add_action( 'blog_activity_cleanup_cron', array( &$this, 'cleanup' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	/**
	 * Plugin db setup
	 **/
	function setup() {
		global $plugin_page;

		// maybe upgrade db
		if( 'blog_activity_main' == $plugin_page ) {
			$this->install();
			$this->upgrade();
		}

		// maybe cleanup activity
		if( isset( $_GET['action'] ) && 'blog_activity_cleanup' == $_GET['action'] ) {
			$this->cleanup();
		}
	}

	/**
	 * Update plugin version in the db
	 **/
	function upgrade() {
		if( get_site_option( 'blog_activity_version' ) == '' )
			add_site_option( 'blog_activity_version', $this->current_version );

		if( get_site_option( 'blog_activity_version' ) !== $this->current_version )
			update_site_option( 'blog_activity_version', $this->current_version );
	}

	function activate() {
		$this->install();
	}

	/**
	 * Create plugin tables
	 **/
	function install() {
		global $wpdb;

		if( get_site_option( 'blog_activity_installed' ) == '' )
			add_site_option( 'blog_activity_installed', 'no' );

		if( get_site_option( 'blog_activity_installed' ) !== 'yes' ) {

			if( @is_file( ABSPATH . '/wp-admin/includes/upgrade.php' ) )
				include_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
			else
				die( __( 'Wir haben ein Problem beim Finden deiner \'/wp-admin/upgrade-functions.php\' und \'/wp-admin/includes/upgrade.php\'', 'postindexer' ) );

			// choose correct table charset and collation
			$charset_collate = '';
			if( $wpdb->has_cap( 'collation' ) ) {
				if( !empty( $wpdb->charset ) ) {
					$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
				}
				if( !empty( $wpdb->collate ) ) {
					$charset_collate .= " COLLATE $wpdb->collate";
				}
			}

			$blog_activity_table = "CREATE TABLE `{$wpdb->base_prefix}blog_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`last_active` bigint(35) NOT NULL default '0',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";

			$post_activity_table = "CREATE TABLE `{$wpdb->base_prefix}post_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`user_ID` bigint(35) NOT NULL default '0',
				`stamp` bigint(35) NOT NULL default '0',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";

			$comment_activity_table = "CREATE TABLE `{$wpdb->base_prefix}comment_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`user_ID` bigint(35) NOT NULL default '0',
				`stamp` bigint(35) NOT NULL default '0',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";


			maybe_create_table( "{$wpdb->base_prefix}blog_activity", $blog_activity_table );
			maybe_create_table( "{$wpdb->base_prefix}post_activity", $post_activity_table );
			maybe_create_table( "{$wpdb->base_prefix}comment_activity", $comment_activity_table );

			update_site_option( 'blog_activity_installed', 'yes' );
		}
	}

	/**
	 * Create post activity entry
	 **/
	function post_global_db_sync() {
		$this->ensure_tables_exist();
		global $wpdb, $current_user;

		if( !( '' == $wpdb->blogid || '' == $current_user->ID ) )
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}post_activity ( blog_ID, user_ID, stamp ) VALUES ( '%d', '%d', '%d' )", $wpdb->blogid, $current_user->ID, time() ) );
	}

	/**
	 * Create comment activity entry
	 **/
	function comment_global_db_sync() {
		$this->ensure_tables_exist();
		global $wpdb, $current_user;

		if( '' == $wpdb->blogid || '' == $current_user->ID ) {
			if( '' == $current_user->ID ) {
				if( '' !== $wpdb->blogid )
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}comment_activity ( blog_ID, user_ID, stamp ) VALUES ( '%d', '%d', '%d' )", $wpdb->blogid, 0, time() ) );
			}
		} else {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}comment_activity ( blog_ID, user_ID, stamp ) VALUES ( '%d', '%d', '%d' )", $wpdb->blogid, $current_user->ID, time() ) );
		}
	}

	/**
	 * Create or update blog activity entry
	 **/
	function blog_global_db_sync() {
		$this->ensure_tables_exist();
		global $wpdb, $current_user;

		if( '' !== $wpdb->blogid ) {
			$tmp_blog_activity_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}blog_activity WHERE blog_ID = '%d'", $wpdb->blogid ) );

			if( '0' == $tmp_blog_activity_count )
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}blog_activity ( blog_ID, last_active ) VALUES ( '%d', '%d' )", $wpdb->blogid, time() ) );
			else
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->base_prefix}blog_activity SET last_active = '%d' WHERE blog_ID = '%d'", time(), $wpdb->blogid ) );
		}
	}

	/**
	 * Cleanup activity older than 1 month from activity tables
	 **/
	function cleanup() {
		$this->ensure_tables_exist();
		global $wpdb;
		$current_stamp = time();
		$month_ago = $current_stamp - 2678400;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}blog_activity WHERE last_active < '%d'", $month_ago ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}post_activity WHERE stamp < '%d'", $month_ago ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}comment_activity WHERE stamp < '%d'", $month_ago ) );
	}

	/**
	 * Schedule cron to cleanup the db
	 **/
	function schedule_cron() {
		if( get_option( 'blog_activity_cron_scheduled' ) != '1' ) {
			$current_stamp = time();
			$current_hour = date( 'G', $current_stamp );
			if ( $current_hour == '23' ) {
				$schedule_time = $current_stamp;
			} else {
				$add_hours = 23 - $current_hour;
				$add_seconds = $add_hours * 3600;
				$schedule_time = $current_stamp + $add_seconds;
			}
			wp_schedule_event( $schedule_time, 'daily', $this->cleanup() );

			add_option( 'blog_activity_cron_scheduled', '1' );
		}
	}

	/**
	 * Get activity from db for a set period of type
	 **/
	function get_activity( $tmp_period, $type ) {
		$this->ensure_tables_exist();
		global $wpdb;

		$tmp_period = ( $tmp_period == '' || $tmp_period == 0 ) ? 1 : $tmp_period;
		$tmp_period = $tmp_period * 60;
		$tmp_current_stamp = time();
		$tmp_stamp = $tmp_current_stamp - $tmp_period;
		$tmp_output = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}{$type}_activity WHERE stamp < '%d'", $tmp_stamp ) );

		return $tmp_output;
	}

	/**
	 * Admin page output.
	 **/
	function page_main_output() {
		$this->ensure_tables_exist();
		global $wpdb;

		// Allow access for users with correct permissions only
		if( !current_user_can( 'manage_network_options' ) ) {
			_e( '<p>Netter Versuch...</p>', 'postindexer' );
			return;
		}

		// Schedule cron if necessary
		$this->schedule_cron();

		echo '<div class="wrap">';

		$current_stamp = time();

		$current_five_minutes = $current_stamp - 300;
		$current_hour = $current_stamp - 3600;
		$current_day = $current_stamp - 86400;
		$current_week = $current_stamp - 604800;
		$current_month = $current_stamp - 2592000;

		$objects = array('blog', 'post', 'comment');
		$labels = array(
			'blog' => __('Aktualisierte Blogs in den letzten:', 'postindexer'),
			'post' => __('Aktualisierte Beiträge in den letzten:', 'postindexer'),
			'comment' => __('Aktualisierte Kommentare in den letzten:', 'postindexer'),
		);
		$time_field = array('blog' => 'last_active', 'post' => 'stamp', 'comment' => 'stamp');
		$stats = [];
		foreach ($objects as $object) {
			$field = $time_field[$object];
			$stats[$object] = array(
				'five_minutes' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $field > '$current_five_minutes'"),
				'hour' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $field > '$current_hour'"),
				'day' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $field > '$current_day'"),
				'week' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $field > '$current_week'"),
				'month' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $field > '$current_month'"),
			);
		}

		echo '<h2>' . __( 'Blog-Aktivität', 'postindexer' ) . '</h2>';
		echo '<div style="display:flex;gap:2em;justify-content:space-between;margin-bottom:2em;flex-wrap:wrap;">';
		foreach ($objects as $object) {
			echo '<div style="flex:1 1 0;min-width:260px;max-width:33%;background:#f8fafc;border:1.5px solid #e5e5e5;border-radius:10px;padding:1.5em 1.2em 1em 1.2em;box-shadow:0 2px 8px rgba(0,0,0,0.03);">';
			echo '<h3 style="margin-top:0;font-size:1.15em;color:#0073aa;">' . esc_html($labels[$object]) . '</h3>';
			echo '<ul style="list-style:none;padding:0;margin:0 0 0.5em 0;font-size:1.05em;">';
			echo '<li>' . __('Fünf Minuten', 'postindexer') . ': <strong>' . intval($stats[$object]['five_minutes']) . '</strong></li>';
			echo '<li>' . __('Stunde', 'postindexer') . ': <strong>' . intval($stats[$object]['hour']) . '</strong></li>';
			echo '<li>' . __('Tag', 'postindexer') . ': <strong>' . intval($stats[$object]['day']) . '</strong></li>';
			echo '<li>' . __('Woche', 'postindexer') . ': <strong>' . intval($stats[$object]['week']) . '</strong></li>';
			echo '<li>' . __('Monat', 'postindexer') . ': <strong>' . intval($stats[$object]['month']) . '</strong></li>';
			echo '</ul>';
			echo '</div>';
		}
		echo '</div>';
		echo '<p style="color:#888;font-size:0.98em;">' . __( '* Monat = 30 Tage<br />Hinweis: Es dauert volle dreißig Tage, bis alle diese Daten korrekt sind. Wenn das Plugin zum Beispiel erst seit einem Tag installiert ist, sind nur "Tag", "Stunde" und "fünf Minuten" wirklich aussagekräftig.', 'postindexer' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Prüft, ob die Monitoring-Tabellen existieren, und legt sie ggf. an
	 */
	private function ensure_tables_exist() {
		global $wpdb;
		if ($this->tables_checked) return;
		$this->tables_checked = true;
		$tables = [
			$wpdb->base_prefix . 'blog_activity',
			$wpdb->base_prefix . 'post_activity',
			$wpdb->base_prefix . 'comment_activity',
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

$blog_activity = new Blog_Activity();

/**
 * Display updated posts for a specific period of time
 **/
function display_blog_activity_posts( $tmp_period ) {
	global $blog_activity;

	echo $blog_activity->get_activity( $tmp_period, 'post' );
}

/**
 * Display updated comments for a specific period of time
 **/
function display_blog_activity_comments( $tmp_period ) {
	global $blog_activity;

	echo $blog_activity->get_activity( $tmp_period, 'comment' );
}

/**
 * Display updated blogs for a specific period of time
 **/
function display_blog_activity_updated( $tmp_period ) {
	global $blog_activity;

	echo $blog_activity->get_activity( $tmp_period, 'blog' );
}
