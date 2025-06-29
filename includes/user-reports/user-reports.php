<?php

require_once( dirname( __FILE__ ) . '/lib/class-user-reports-posts-list-table.php' );
require_once( dirname( __FILE__ ) . '/lib/class-user-reports-comments-list-table.php' );

class UserReports {

	private $_pagehooks = array();    // A list of our various nav items. Used when hooking into the page load actions.
	private $_messages = array();    // Message set during the form processing steps for add, edit, udate, delete, restore actions
	private $_settings = array();    // These are global dynamic settings NOT stores as part of the config options

	private $_admin_header_error;    // Set during processing will contain processing errors to display back to the user

	private $_admin_panels;

	private $_filters = array();    // Set during processfilters().

	private $user_reports_table;

	/**
	 * The PHP5 Class constructor. Used when an instance of this class is needed.
	 * Sets up the initial object environment and hooks into the various WordPress
	 * actions and filters.
	 *
	 * @since 1.0.0
	 * @uses $this->_settings array of our settings
	 * @uses $this->_messages array of admin header message texts.
	 *
	 * @param none
	 */
	function __construct() {

		$this->_settings['VERSION'] = '1.0.3.2';
		$this->_settings['MENU_URL'] = 'users.php?page=';
		// Dynamische Pfad- und URL-Ermittlung für verschachtelte Plugins
        $plugin_dir = dirname(__FILE__);
        $plugin_url = plugins_url('', __FILE__);
        $this->_settings['PLUGIN_URL'] = $plugin_url;
        $this->_settings['PLUGIN_BASE_DIR'] = $plugin_dir;
		$this->_settings['admin_menu_label'] = __( "Benutzerberichte", 'postindexer' );

		$this->_settings['options_key'] = "user-report-" . $this->_settings['VERSION'];

		$this->_admin_header_error = "";

		add_action( 'admin_notices', array( &$this, 'user_reports_admin_notices_proc' ) );
		add_action( 'network_admin_notices', array( &$this, 'user_reports_admin_notices_proc' ) );

		/* Standard activation hook for all WordPress plugins see http://codex.wordpress.org/Function_Reference/register_activation_hook */
		register_activation_hook( __FILE__, array( &$this, 'user_reports_plugin_activation_proc' ) );

		/* Register stadnard admin actions */
		add_action( 'admin_menu', array( &$this, 'user_reports_admin_menu_proc' ) );
		add_action( 'user_admin_menu', array( &$this, 'user_reports_admin_menu_proc' ) );
		add_action( 'network_admin_menu', array( &$this, 'user_reports_admin_menu_proc' ) );
		//add_action( 'wp_login', 			array(&$this,'user_reports_wp_login_proc') );

		/* Add our 'Reports' to the User listing rows */
		add_filter( 'user_row_actions', array( &$this, 'user_reports_user_row_actions_proc' ), 10, 2 );
		add_filter( 'ms_user_row_actions', array( &$this, 'user_reports_user_row_actions_proc' ), 10, 2 );

		add_filter( 'the_comments', array( &$this, 'get_comments' ) );
	}

	/**
	 * Called when when our plugin is activated. Sets up the initial settings
	 * and creates the initial Snapshot instance.
	 *
	 * @since 1.0.0
	 * @uses none
	 * @see  $this->__construct() when the action is setup to reference this function
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_plugin_activation_proc() {}

	/**
	 * Hook to add the User row display and add out Reports hover nav element
	 *
	 * @since 1.0.0
	 * @uses $wp_admin_bar
	 * @uses $this->_settings
	 *
	 * @param array $actions
	 * @param object $user_object
	 *
	 * @return array
	 */
	function user_reports_user_row_actions_proc( $actions, $user_object ) {

		if ( current_user_can( 'list_users' ) ) {
			if ( ! isset( $actions['user-reports'] ) ) {
				$url = add_query_arg( 'page', 'user-reports', 'users.php' );

				if ( is_network_admin() ) {
					$url = add_query_arg( 'user_login', $user_object->user_login, $url );
				} else {
					$url = add_query_arg( 'user_id', $user_object->ID, $url );
				}

				$actions['user-reports'] = sprintf(
					'<a class="submitreports" href="%s">%s</a>',
					esc_url( $url ), __( 'Berichte', 'postindexer' )
				);
			}
		}

		return $actions;
	}

	/**
	 * Add the new Menu to the Tools section in the WordPress main nav
	 *
	 * @since 1.0.0
	 * @uses $this->_pagehooks
	 * @see  $this->__construct where this function is referenced
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_admin_menu_proc() {

		$this->_pagehooks['user-reports'] = add_users_page(
			_x('Berichte', 'page label', 'postindexer' ),
			_x( 'Berichte', 'menu label', 'postindexer' ),
			'list_users', 'user-reports',
			array( $this, 'user_reports_admin_show_panel' ) );

		//site-users-network
		$this->_pagehooks['network-user-reports'] = add_submenu_page(
			'users-network',
			_x( 'Berichte', 'page label', 'postindexer' ),
			_x( 'Berichte', 'menu label', 'postindexer' ),
			'list_users', 'user-reports',
			array( $this, 'user_reports_admin_show_panel' ) );

		// Hook into the WordPress load page action for our new nav items. This is better then checking page query_str values.
		add_action( 'load-' . $this->_pagehooks['user-reports'], array( &$this, 'user_reports_on_load_page' ) );
	}

	/**
	 * Capture the login action to record to the usermeta
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_login - User login name
	 * @param array  $user - User object
	 *
	 * @return void
	 */
	function user_reports_wp_login_proc( $user_login, $user ) {
		global $wpdb;

		if ( ! $user ) {
			$user = get_user_by( 'login', $user_login );
		}

		if ( isset( $user->ID ) && intval( $user->ID ) > 0 && isset( $wpdb->blogid ) && intval( $wpdb->blogid ) > 0 ) {

			$user_login_data = get_user_meta( $user->ID, 'user-reports-login', true );
			if ( ( ! $user_login_data ) || ( ! is_array( $user_login_data ) ) ) {
				$user_login_data = array();
			}

			$user_login_data[ intval( $wpdb->blogid ) ] = time();
			update_user_meta( $user->ID, 'user-reports-login', (array) $user_login_data );
		}
	}

	/**
	 * Display our message on the Snapshot page(s) header for actions taken
	 *
	 * @since 1.0.0
	 * @uses $this->_messages Set in form processing functions
	 *
	 * @return void
	 */
	function user_reports_admin_notices_proc() {

		// IF set during the processing logic setsp for add, edit, restore
		if ( ( isset( $_REQUEST['message'] ) ) && ( isset( $this->_messages[ $_REQUEST['message'] ] ) ) ) {
			?>
			<div id='user-report-warning' class='updated fade'>
			<p><?php echo $this->_messages[ $_REQUEST['message'] ]; ?></p></div><?php
		}

		// IF we set an error display in red box
		if ( strlen( $this->_admin_header_error ) ) {
			?>
			<div id='user-report-error' class='error'><p><?php echo $this->_admin_header_error; ?></p></div><?php
		}
	}

	public function get_filters() {
		return $this->_filters;
	}

	/**
	 * On Load Reports page. Initializes Filters, loads needed scripts and stylesheets
	 *
	 * @since 1.0.0
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_on_load_page() {

		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( __( 'Cheatin&#8217; uh?' ) );
		}

		$this->user_reports_process_filters();

		if ( $this->_filters['type'] == "comments" ) {
			$this->user_reports_table = new User_Reports_Comments_List_Table();
		} /* else if ($this->_filters['type'] == "logins") {
			$this->user_reports_table = new User_Reports_Logins_List_Table();
		} */ else {
			$this->user_reports_table = new User_Reports_Posts_List_Table();
		}

		// Fallback für Monitoring-Dashboard: Tabelle initialisieren, falls nicht gesetzt
		if (!isset($this->user_reports_table) || !$this->user_reports_table) {
			if (!isset($this->_filters) || !is_array($this->_filters)) {
				$this->_filters = [];
			}
			if (!isset($this->_filters['type'])) {
				$this->_filters['type'] = 'posts'; // Default
			}
			if (!isset($this->user_reports_table) || !$this->user_reports_table) {
				if ($this->_filters['type'] == "comments") {
					if (class_exists('User_Reports_Comments_List_Table')) {
						$this->user_reports_table = new User_Reports_Comments_List_Table();
					}
				} else {
					if (class_exists('User_Reports_Posts_List_Table')) {
						$this->user_reports_table = new User_Reports_Posts_List_Table();
					}
				}
			}
		}

		if ( isset( $_GET['user-report-download'] ) ) {

			$download_type = esc_attr( $_GET['user-report-download'] );

			$report_filename = "user-report-";
			if ( $this->_filters['type'] == "comments" ) {
				$report_filename .= "comments-";
			} else {
				$report_filename .= "posts-";
			}
			$report_filename .= date( 'ymd' );

			if ( $download_type == "pdf" ) {
				require_once dirname( __FILE__ ) . '/lib/dompdf/dompdf_config.inc.php';

				$this->_filters['per_page'] = 0;
				$this->_filters['doing_reports'] = 'pdf';

				$this->user_reports_table->prepare_items( $this->_filters );
				$html_for_pdf = $this->user_reports_table->display_pdf();
				if ( strlen( $html_for_pdf ) ) {
					//create and output the PDF as a stream (download dialog)
					$dompdf = new DOMPDF();
					$dompdf->set_paper( "letter", "landscape" );

					$dompdf->load_html( $html_for_pdf );
					$dompdf->render();
					$dompdf->stream( $report_filename );
					die();
				}
			} else if ( $download_type == "csv" ) {

				$this->_filters['doing_reports'] = 'csv';
				$this->user_reports_table->prepare_items( $this->_filters );

				$html_for_csv = $this->user_reports_table->display_csv();
				if ( strlen( $html_for_csv ) ) {

					header( "Content-type: text/csv" );
					header( "Content-Disposition: attachment; filename=" . $report_filename . ".csv" );
					header( "Pragma: no-cache" );
					header( "Expires: 0" );

					echo $html_for_csv;
					die();
				}
			}
		} else {
			$this->admin_setup_page_display_options();
			$this->user_reports_admin_plugin_help();

			/* enqueue our plugin styles */
			wp_enqueue_style( 'jquery.ui.datepicker-css', $this->_settings['PLUGIN_URL'] . '/css/jquery.ui.smoothness/jquery-ui-1.8.18.custom.css',
				false, '1.8.18' );
			wp_enqueue_style( 'user-reports-admin-stylesheet', $this->_settings['PLUGIN_URL'] . '/css/user-reports-admin-styles.css',
				false, $this->_settings['VERSION'] );

			wp_enqueue_script( 'user-reports-admin', $this->_settings['PLUGIN_URL'] . '/js/user-reports-admin.js',
				array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker' ), $this->_settings['VERSION'] );
		}
	}


	/**
	 * Setup the page options. Processes the $_GET passed arguments for the filters.
	 *
	 * @since 1.0.0
	 *
	 * @param none
	 *
	 * @return void
	 */
	function admin_setup_page_display_options() {

//		if ($this->_filters['type'] == "comments") {
//			$this->user_reports_table = new User_Reports_Comments_List_Table();
//		} /* else if ($this->_filters['type'] == "logins") {
//			$this->user_reports_table = new User_Reports_Logins_List_Table();
//		} */ else {
//			$this->user_reports_table = new User_Reports_Posts_List_Table();
//		}		

		$current_user_id = get_current_user_id();
		if ( $current_user_id ) {
			if ( isset( $_POST['wp_screen_options'] ) ) {

				if ( $_POST['wp_screen_options']['value'] ) {
					$option_value = esc_attr( $_POST['wp_screen_options']['value'] );
				}

				if ( $_POST['wp_screen_options']['option'] ) {
					$option_key = esc_attr( $_POST['wp_screen_options']['option'] );
				}

				if ( $option_key == 'users_page_user_reports_per_page' ) {
					if ( ! isset( $option_value ) ) {
						$option_value = 20;
					}

					update_user_meta( $current_user_id, $option_key, $option_value );
				}
			}
		}

		if ( ! isset( $option_value ) ) {
			$default_post_per_page = get_option( 'posts_per_page' );
			if ( $default_post_per_page ) {
				$option_value = $default_post_per_page;
			} else {
				$option_value = 20;
			}

		}
		add_screen_option( 'per_page', array( 'label' => __( 'pro Seite', 'postindexer' ), 'default' => $option_value ) );
	}

	/**
	 * This function is the main page wrapper output.
	 *
	 * @since 1.0.0
	 * @see
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_admin_show_panel() {

		// Sicherstellen, dass user_reports_table initialisiert ist
		if (!isset($this->user_reports_table) || !$this->user_reports_table) {
			if (!isset($this->_filters) || !is_array($this->_filters)) {
				$this->_filters = [];
			}
			if (!isset($this->_filters['type'])) {
				$this->_filters['type'] = 'posts'; // Default
			}
			if ($this->_filters['type'] == "comments") {
				if (class_exists('User_Reports_Comments_List_Table')) {
					$this->user_reports_table = new User_Reports_Comments_List_Table();
				}
			} else {
				if (class_exists('User_Reports_Posts_List_Table')) {
					$this->user_reports_table = new User_Reports_Posts_List_Table();
				}
			}
		}
		?>
		<div id="user-reports-panel" class="wrap user-reports-wrap">
			<h2><?php _ex( "Benutzerberichte", "User Reports New Page Title", 'postindexer' ); ?></h2>

			<?php
			if ( ( is_multisite() ) && ( is_network_admin() ) ) {
				?>
				<p><?php _ex( "Um einen Bericht zu erstellen, wählen Sie unten den Berichtstyp, Blogs, Benutzer und den Datumsbereich aus. Setzen Sie 'Benutzer' auf leer, wenn Sie die Statistiken aller Benutzer anzeigen möchten.",
						'User Reports page description', 'postindexer' ); ?></p>
				<?php $this->user_reports_show_filter_form_bar(); ?>
				<?php
				$this->user_reports_table->prepare_items( $this->_filters );
				$this->user_reports_table->display();

				$siteurl = get_option( 'siteurl' );
				$href_str = $siteurl . "/wp-admin/users.php?page=user-reports";
				if ( isset( $_GET['type'] ) ) {
					$href_str .= "&type=" . esc_attr( $_GET['type'] );
				}
				if ( isset( $_GET['blog_id'] ) ) {
					$href_str .= "&blog_id=" . esc_attr( $_GET['blog_id'] );
				}
				if ( isset( $_GET['date_start'] ) ) {
					$href_str .= "&date_start=" . esc_attr( $_GET['date_start'] );
				}
				if ( isset( $_GET['date_end'] ) ) {
					$href_str .= "&date_end=" . esc_attr( $_GET['date_end'] );
				}
				if ( isset( $_GET['orderby'] ) ) {
					$href_str .= "&orderby=" . esc_attr( $_GET['orderby'] );
				}
				if ( isset( $_GET['order'] ) ) {
					$href_str .= "&order=" . esc_attr( $_GET['order'] );
				}
				if ( ! empty( $this->_filters['user_login'] ) ) {
					$href_str .= '&user_login=' . esc_attr( $this->_filters['user_login'] );
				}
				if ( ! empty( $this->_filters['user_id'] ) ) {
					$user = get_userdata( $this->_filters['user_id'] );
					if ( $user ) {
						$href_str .= '&user_login=' . esc_attr( $user->user_login );
					}
				}

				?>
				<a class="button-secondary" href="<?php echo $href_str; ?>&amp;user-report-download=pdf"><?php _e( "PDF herunterladen", 'postindexer' ); ?></a>
				<a class="button-secondary" href="<?php echo esc_url( $href_str ); ?>&amp;user-report-download=csv"><?php _e( "CSV herunterladen", 'postindexer' ); ?></a>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * This function checks the $_GET query arguments and sets the object $_filters options accordingly
	 *
	 * @since 1.0.0
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_process_filters() {
		global $wpdb;

		if ( isset( $_GET['type'] ) ) {
			$this->_filters['type'] = esc_attr( $_GET['type'] );
		} else {
			$this->_filters['type'] = 'post';
		}

		// Validate and Set the Blog selection	
		if ( is_multisite() ) {
			if ( is_network_admin() ) {
				$this->_filters['blog_id'] = 0;
			} else {
				if ( isset( $_GET['blog_id'] ) ) {
					$this->_filters['blog_id'] = intval( $_GET['blog_id'] );
					if ( ( $this->_filters['blog_id'] != 0 ) && ( $this->_filters['blog_id'] != $wpdb->blogid ) ) {
						$this->_filters['blog_id'] = $wpdb->blogid;
					}
				} else {
					$this->_filters['blog_id'] = $wpdb->blogid;
				}
			}
		} else {
			$this->_filters['blog_id'] = $wpdb->blogid;
		}

		// Validate and Set the User selection

		// First we need to get a list of all User Ids for the current blog. 
		$user_args = array(
			'number' => 0,
			'blog_id' => $wpdb->blogid,
			'fields' => array( 'ID', 'display_name' ),
		);

		if ( ! empty( $_GET['user_login'] ) ) {
			$user_args['search'] = $_GET['user_login'];
			$user_args['search_columns'] = array( 'user_login' );
		}

		$this->_filters['blog_users'] = array();
		$this->_filters['blog_users_ids'] = array();
		$wp_user_search = new WP_User_Query( $user_args );
		$users = $wp_user_search->get_results();
		if ( $users ) {
			$this->_filters['blog_users'] = $users;
			foreach ( $users as $user ) {
				$this->_filters['blog_users_ids'][ $user->ID ] = $user->ID;
			}
		}

		if ( is_multisite() ) {
			if ( is_network_admin() ) {
				if ( isset( $_GET['user_login'] ) ) {
					$userdata = get_user_by( 'login', esc_attr( $_GET['user_login'] ) );
					if ( $userdata && intval( $userdata->ID ) ) {
						$this->_filters['user_id'] = $userdata->ID;
						$this->_filters['user_login'] = $userdata->user_login;
					}
				} else {
					$userdata = wp_get_current_user();
					//echo "userdata<pre>"; print_r($userdata); echo "</pre>";
					if ( ( $userdata ) && ( intval( $userdata->ID ) ) ) {
						$this->_filters['user_id'] = $userdata->ID;
						$this->_filters['user_login'] = $userdata->user_login;
					}
				}

			} else {

				if ( isset( $_GET['user_id'] ) ) {
					$this->_filters['user_id'] = intval( $_GET['user_id'] );
					if ( ( $this->_filters['user_id'] != 0 ) && ( array_search( $this->_filters['user_id'], $this->_filters['blog_users_ids'] ) === false ) ) {
						$this->_filters['user_id'] = get_current_user_id();
					}
				} else {
					if ( ! is_super_admin() ) {
						$this->_filters['user_id'] = get_current_user_id();
					} else {
						$this->_filters['user_id'] = 0;
					}
				}
			}
		} else {
			if ( isset( $_GET['user_id'] ) ) {
				$this->_filters['user_id'] = intval( $_GET['user_id'] );
				if ( ( $this->_filters['user_id'] != 0 ) && ( array_search( $this->_filters['user_id'], $this->_filters['blog_users_ids'] ) === false ) ) {
					$this->_filters['user_id'] = get_current_user_id();
				}
			} else {
				$this->_filters['user_id'] = 0;
			}
		}

		if ( isset( $_GET['date_end'] ) ) {
			$date_end = strtotime( esc_attr( $_GET['date_end'] ) );
			if ( $date_end !== false ) { // We have a valid date
				$this->_filters['date_end'] = mktime( 23, 59, 59,
					date( "m", $date_end ),
					date( "d", $date_end ),
					date( "Y", $date_end ) );
			} else {
				$this->_filters['date_end'] = mktime( 23, 59, 59,
					date( "m" ),
					date( "d" ),
					date( "Y" ) );
			}
		} else {
			// Else, set date_end to taday's date
			$this->_filters['date_end'] = mktime( 23, 59, 59,
				date( "m" ),
				date( "d" ),
				date( "Y" ) );
		}

		if ( isset( $_GET['date_start'] ) ) {
			$date_start = strtotime( esc_attr( $_GET['date_start'] ) );
			if ( $date_start !== false ) {
				$this->_filters['date_start'] = mktime( 0, 0, 0,
					date( "m", $date_start ),
					date( "d", $date_start ),
					date( "Y", $date_start ) );
			} else {
				if ( isset( $this->_filters['date_end'] ) ) {
					$this->_filters['date_start'] = mktime( 0, 0, 0,
						date( "m", $this->_filters['date_end'] ),
						date( "d", $this->_filters['date_end'] ) - 90,
						date( "Y", $this->_filters['date_end'] ) );

				} else {
					//echo "invalid date_end<br />";
					$this->_filters['date_start'] = mktime( 0, 0, 0,
						date( "m" ),
						date( "d" ) - 90,
						date( "Y" ) );
				}
			}
		} else {
			// Else set start date to 90 days prior to today's date
			$this->_filters['date_start'] = mktime( 0, 0, 0,
				date( "m" ),
				date( "d" ) - 90,
				date( "Y" ) );
		}

		// IF the date_end is earlier than date_start. Swap them.
		if ( $this->_filters['date_end'] < $this->_filters['date_start'] ) {
			$date_tmp = $this->_filters['date_end'];
			$this->_filters['date_end'] = $this->_filters['date_start'];
			$this->_filters['date_start'] = $date_tmp;
		}
		$date_range = intval( $this->_filters['date_end'] ) - intval( $this->_filters['date_start'] );
		$date_range = intval( $date_range / 86400 );
		if ( intval( $date_range ) < 90 ) {
			$this->_filters['date_start'] = mktime( 0, 0, 0,
				date( "m", $this->_filters['date_end'] ),
				date( "d", $this->_filters['date_end'] ) - 90,
				date( "Y", $this->_filters['date_end'] ) );
		}
	}

	/**
	 * This function build the output display for the filters bar shown at the top of the page. This
	 * filter bar contains all form elements used to filter the main table. User, Blog, Dates, Post Types
	 *
	 * @since 1.0.0
	 * @see
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_show_filter_form_bar() {
    ?>
    <style>
    .user-report-filters-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 2em 1.5em;
        align-items: flex-end;
        margin-bottom: 2em;
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 1.5em 1em 1em 1em;
    }
    .user-report-filters-bar .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 180px;
        flex: 1 1 180px;
    }
    .user-report-filters-bar label {
        font-weight: 600;
        margin-bottom: 0.3em;
        color: #222;
    }
    .user-report-filters-bar select,
    .user-report-filters-bar input[type="text"] {
        padding: 0.4em 0.6em;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 1em;
        background: #fff;
    }
    .user-report-filters-bar .button-secondary {
        margin-top: 1.2em;
        padding: 0.6em 1.5em;
        font-size: 1em;
        border-radius: 4px;
        background: #0073aa;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: background 0.2s;
    }
    .user-report-filters-bar .button-secondary:hover {
        background: #005177;
    }
    @media (max-width: 700px) {
        .user-report-filters-bar { flex-direction: column; gap: 1em; }
    }
    </style>
    <form id="user-report-filters" class="user-report-filters-bar" method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <div class="filter-group">
            <?php $this->user_reports_show_filter_form_types(); ?>
        </div>
        <div class="filter-group">
            <?php $this->user_reports_show_filter_form_blogs(); ?>
        </div>
        <div class="filter-group">
            <?php $this->user_reports_show_filter_form_users(); ?>
        </div>
        <div class="filter-group">
            <?php $this->user_reports_show_filter_form_dates(); ?>
        </div>
        <div class="filter-group" style="min-width:120px;flex:0 0 120px;align-items:flex-end;display:flex;">
            <input class="button-secondary" id="user-reports-filters-submit" type="submit" value="<?php esc_attr_e( 'Erstellen', 'postindexer' ); ?>" />
        </div>
    </form>
    <?php
}

	/**
	 * Show the filter bar field set for the Report Type dropdown.
	 *
	 * @since 1.0.0
	 * @see
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_show_filter_form_types() {

		$content_types = array();

		if ( $this->has_post_indexer_plugin() ) {
			$content_types['post'] = __( 'Beitrag', 'postindexer' );
		} else {
			if ( ! is_multisite() || ! is_network_admin() ) {
				foreach ( (array) get_post_types( array( 'show_ui' => true ), 'name' ) as $post_type => $details ) {
					$content_types[ $post_type ] = $details->labels->name;
				}
			}
		}

		if ( $this->has_post_indexer_plugin() ) {
			$content_types['comments'] = __( 'Kommentare', 'postindexer' );
		} else {
			if ( ! is_multisite() || ! is_network_admin() ) {
				$content_types['comments'] = __( 'Kommentare', 'postindexer' );
			}
		}

		if ( $content_types && count( $content_types ) ) {
			?>
			<label for="user-reports-filter-types"><?php _e( 'Berichtstyp', 'postindexer' ); ?></label>:
			<select id="user-reports-filter-types" name="type">
				<?php

				foreach ( $content_types as $type => $label ) {

					$selected = '';
					if ( $type == $this->_filters['type'] ) {
						$selected = ' selected="selected" ';
					}

					?>
					<option <?php echo $selected; ?> value="<?php echo $type ?>"><?php echo $label ?></option><?php
				}
				?>
			</select>
			<?php
		}
	}

	/**
	 * Show the filter bar field set for the Blogs dropdown.
	 *
	 * @since 1.0.0
	 * @see
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_show_filter_form_blogs() {
		global $wpdb;

		if ( ( is_multisite() ) && ( $this->has_post_indexer_plugin() ) ) {
			if ( is_network_admin() ) {

				$blogs = array(
					'0' => __( 'Alle Blogs', 'postindexer' ),
				);

			} else {
				$current_blog = get_blog_details( $wpdb->blogid );

				$blogs = array(
					'0' => __( 'Alle Blogs', 'postindexer' ),
					$current_blog->blog_id => __( 'Nur dieser Blog', 'postindexer' ),
				);
			}

			?>
			<label for="user-reports-filter-blogs"><?php _e( 'Blogs', 'postindexer' ); ?></label>:
			<select id="user-reports-filter-blogs" name="blog_id">
				<?php
				foreach ( $blogs as $blog_id => $blog_name ) {
					$selected = '';
					if (isset($this->_filters['blog_id']) && intval( $blog_id ) == intval( $this->_filters['blog_id'] ) ) {
						$selected = ' selected="selected" ';
					}

					?>
					<option <?php echo $selected; ?> value="<?php echo $blog_id ?>"><?php echo $blog_name ?></option><?php
				}
				?>
			</select>
			<?php
		}
	}

	/**
	 * Show the filter bar field set for the Users dropdown.
	 *
	 * @since 1.0.0
	 * @see
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_show_filter_form_users() {
		global $wpdb;

		if ( ! is_network_admin() ) {
			$users = $this->user_reports_get_users( $wpdb->blogid );
			if ( $users ) {
				?>
				<label for="user-reports-filter-users"><?php _e( 'Benutzer', 'postindexer' ); ?>: </label>
				<select id="user-reports-filter-users" name="user_id">
					<option value="0"><?php _e( 'Alle Benutzer', 'postindexer' ); ?></option>
					<?php
					foreach ( $users as $user_group_name => $user_group ) {
						if ( ( is_array( $user_group ) ) && ( count( $user_group ) ) ) {

							?>
							<optgroup label="<?php echo $user_group_name; ?>"><?php
							foreach ( $user_group as $user_id => $display_name ) {
								$selected = '';
								if ( $user_id == $this->_filters['user_id'] ) {
									$selected = ' selected="selected" ';
								}

								?>
								<option <?php echo $selected ?> value="<?php echo $user_id; ?>"><?php echo $display_name; ?></option><?php
							}
							?></optgroup><?php
						}
					}
					?>
				</select>
				<?php
			}
		} else {
			$user_login = ! empty( $this->_filters['user_login'] ) ? $this->_filters['user_login'] : '';
			?>
			<label for="user-reports-filter-users"><?php _e( 'Benutzer', 'postindexer' ); ?>: </label>
			<input type="text" id="user-reports-filter-users" name="user_login" value="<?php echo esc_attr( $user_login ); ?>" />
			<?php
		}
	}

	/**
	 * Show the filter bar field set for the Date filters.
	 *
	 * @since 1.0.0
	 * @see
	 *
	 * @param none
	 *
	 * @return void
	 */
	function user_reports_show_filter_form_dates() {
		?>
		<label for="user-reports-filter-date-start">Von Datum</label>
		<input type="text" size="10" name="date_start" id="user-reports-filter-date-start"
		       value="<?php echo isset($this->_filters['date_start']) ? date( 'Y-m-d', $this->_filters['date_start'] ) : ''; ?>" />

		<label for="user-reports-filter-date-end">Bis Datum</label>
		<input type="text" size="10" name="date_end" id="user-reports-filter-date-end"
		       value="<?php echo isset($this->_filters['date_end']) ? date( 'Y-m-d', $this->_filters['date_end'] ) : ''; ?>" />
		<?php
	}

	/**
	 * Utility function to determine all blogs under a Multisite install
	 *
	 * @since 1.0.0
	 * @see
	 *
	 * @param none
	 *
	 * @return array of blog information
	 */
	/*
	function user_report_get_blogs() {

		global $wpdb;

		$blogs_tmp = $wpdb->get_results( $wpdb->prepare("SELECT blog_id, site_id, domain FROM $wpdb->blogs") );
		if ($blogs_tmp) {
			$blogs = array();
			foreach($blogs_tmp as $blog) {
				$blogs[$blog->blog_id] = get_blog_details($blog->blog_id);;
			}
			return $blogs;		
		}
	}
	*/

	/**
	 * This function build an array of all users for the site and adds the super admins to the returned array
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $blog_id The blog_id we want user from. For network admin this is zero to grab all users.
	 *
	 * @return array
	 */
	function user_reports_get_users( $blog_id = '' ) {
		$users_all = array();

		$user_args = array(
			'number' => 0,
			'blog_id' => $blog_id,
			'fields' => array( 'ID', 'display_name' ),
		);

		$wp_user_search = new WP_User_Query( $user_args );
		$users_tmp = $wp_user_search->get_results();
		if ( $users_tmp ) {

			$users_all['Blog'] = array();

			foreach ( $users_tmp as $user ) {
				if ( ! is_super_admin( $user->ID ) ) {
					$users_all['Blog'][ $user->ID ] = $user->display_name;
				}
			}
			asort( $users_all['Blog'] );
		}

		return $users_all;
	}

	function get_comments( $comments ) {

		if ( isset( $_GET['comment_reply'] ) ) {
			$comment = get_comment( absint( $_GET['comment_reply'] ) );
			//echo "comment<pre>"; print_r($comment); echo "</pre>";
			if ( $comment === null ) {
				$comments = array();
			} else {
				$comments = array( $comment );
				//add_action( 'admin_footer', 'wp_ozh_cqr_popup_reply' );
			}
		}

		return $comments;
	}

	/**
	 * This utility function checks if the Post Indexer plugin is installed.
	 *
	 * @since 1.0.2
	 *
	 * @param none
	 *
	 * @return bool|int true if Post Indexer plugin is installed. false is not
	 */

	function has_post_indexer_plugin() {
		global $post_indexer_current_version;

		if ( ! empty( $post_indexer_current_version ) ) {
			return 2;
		} else if ( class_exists( 'postindexermodel' ) ) {
			return 3;
		}

		return false;
	}

	/**
	 * This utility function checks if the Comment Indexer plugin is installed.
	 *
	 * @since 1.0.2
	 * @see
	 *
	 * @param none
	 *
	 * @return true if Comment Indexer plugin is installed. false is not
	 */
	function has_comment_indexer_plugin() {
		if ( function_exists( 'comment_indexer_comment_insert_update' ) ) {
			return true;
		}

		return false;
	}

}

$user_reports = new UserReports();
