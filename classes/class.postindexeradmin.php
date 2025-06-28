<?php

if ( ! class_exists( 'postindexeradmin' ) ) {

	include_once( dirname( __FILE__ ) . '/class.processlocker.php' );
	include_once( dirname( __FILE__ ) . '/class.postindexerextensionsadmin.php' );
	include_once( dirname( __FILE__ ) . '/class.postindexermonitoringadmin.php' );

	class postindexeradmin {

		var $build = 1;

		// The class holder for the db class
		var $db;

		// The post indexer model
		var $model;

		var $global_post_types;

		var $base_url;

		var $extensions_admin;

		var $monitoring_admin;

		function __construct() {

			global $wpdb;

			$this->db       = $wpdb;
			$this->model    = new postindexermodel();
			$this->base_url = plugins_url( '/', dirname( __FILE__ ) );

			$this->extensions_admin = new Postindexer_Extensions_Admin();
			$this->monitoring_admin = new Postindexer_Monitoring_Admin();

			// Add settings menu action
			add_action( 'network_admin_menu', array( $this, 'add_admin_page' ) );
			add_action( 'network_admin_notices', array( &$this, 'admin_notices' ) );
			add_action( 'load-settings_page_postindexer', array( $this, 'add_header_postindexer_page' ) );
			add_action( 'load-toplevel_page_ps-multisite-index', array( $this, 'add_header_postindexer_page' ) );
			//settings_page_postindexer
			// Sites page integration
			add_filter( 'wpmu_blogs_columns', array( $this, 'add_sites_column_heading' ), 99 );
			add_action( 'manage_sites_custom_column', array( $this, 'add_sites_column_data' ), 99, 2 );
			add_action( 'wp_ajax_editsitepostindexer', array( $this, 'edit_site_postindexer' ) );
			add_action( 'wp_ajax_summarysitepostindexer', array( $this, 'summary_site_postindexer' ) );
			add_action( 'admin_head-sites.php', array( $this, 'add_header_sites_page' ) );
			add_action( 'wpmuadminedit', array( $this, 'process_sites_page' ) );
			// Sites update settings
			add_filter( 'network_sites_updated_message_disableindexing', array( $this, 'output_msg_sites_page' ) );
			add_filter( 'network_sites_updated_message_not_disableindexing', array( $this, 'output_msg_sites_page' ) );
			add_filter( 'network_sites_updated_message_enableindexing', array( $this, 'output_msg_sites_page' ) );
			add_filter( 'network_sites_updated_message_not_enableindexing', array( $this, 'output_msg_sites_page' ) );
			add_filter( 'network_sites_updated_message_rebuildindexing', array( $this, 'output_msg_sites_page' ) );
			add_filter( 'network_sites_updated_message_not_rebuildindexing', array( $this, 'output_msg_sites_page' ) );

			add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );

			// Index posts as we go along
			add_action( 'save_post', array( $this, 'index_post' ), 99, 2 );
			add_action( 'delete_post', array( $this, 'delete_post' ), 99 );

			//handle blog changes
			add_action( 'make_spam_blog', array( $this, 'remove_from_index' ) );
			add_action( 'archive_blog', array( $this, 'remove_from_index' ) );
			add_action( 'mature_blog', array( $this, 'remove_from_index' ) );
			add_action( 'deactivate_blog', array( $this, 'remove_from_index' ) );
			add_action( 'delete_blog', array( $this, 'remove_from_index' ) );

			add_action( 'blog_privacy_selector', array( $this, 'check_privacy' ) );

			// Set the global / default post types that we will be using
			$this->global_post_types = get_site_option( 'postindexer_globalposttypes', array( 'post' ) );

			// Add the jazzy statistics information
			add_action( 'postindexer_statistics', array( $this, 'handle_statistics_page' ) );

			add_action( 'network_admin_footer', array( $this, 'output_modals' ) );
		}

		//------------------------------------------------------------------------//
		//---Functions------------------------------------------------------------//
		//------------------------------------------------------------------------//

		function load_textdomain() {

			if ( preg_match( '/mu\-plugin/', PLUGINDIR ) > 0 ) {
				load_muplugin_textdomain( 'postindexer', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
			} else {
				load_plugin_textdomain( 'postindexer', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
			}
		}

		function add_header_sites_page() {
			wp_enqueue_style( 'postindexernetworksettings', $this->base_url . 'css/sites.postindexer.css' );

			wp_register_script(
				'psource-modal',
				$this->base_url . 'assets/psource-ui/modal/psource-modal.js',
				array('jquery'),
				'1.0.0',
				true
			);
			wp_enqueue_script('psource-modal');

			wp_register_script(
				'pi-sites-post-indexer',
				$this->base_url . 'js/sites.postindexer.js',
				array('jquery', 'psource-modal'),
				'1.0.0',
				true
			);
			wp_enqueue_script('pi-sites-post-indexer');

			wp_localize_script( 'pi-sites-post-indexer', 'postindexer', array(
				'siteedittitle'    => __( 'PS-Multisite Index Einstellungen', 'postindexer' ),
				'sitesummarytitle' => __( 'PS-Multisite Index Zusammenfassung', 'postindexer' )
			) );
		}

		function output_msg_sites_page( $msg ) {
			switch ( $_GET['action'] ) {
				case 'disableindexing':
					$msg = __( 'Indizierung für die angeforderte Seite deaktiviert.', 'postindexer' );
					break;
				case 'not_disableindexing':
					$msg = __( 'Die Indizierung für die angeforderte Seite konnte nicht deaktiviert werden.', 'postindexer' );
					break;
				case 'enableindexing':
					$msg = __( 'Indizierung für die angeforderte Seite aktiviert.', 'postindexer' );
					break;
				case 'not_enableindexing':
					$msg = __( 'Die Indizierung für die angeforderte Seite konnte nicht aktiviert werden.', 'postindexer' );
					break;
				case 'rebuildindexing':
					$msg = __( 'Index, der für die Neuerstellung der angeforderten Seite geplant ist.', 'postindexer' );
					break;
				case 'not_rebuildindexing':
					$msg = __( 'Der Index konnte nicht für die Neuerstellung der angeforderten Seite geplant werden.', 'postindexer' );
					break;
				case 'changed_siteindexing':
					$msg = __( 'Deine Einstellungen wurden für die angeforderte Seite aktualisiert.', 'postindexer' );
					break;
			}

			return $msg;
		}

		function admin_notices() {
			if ( ( is_network_admin() ) && ( isset( $_GET['page'] ) ) && ( $_GET['page'] == 'postindexer' ) ) {

				if ( ( defined( 'DISABLE_WP_CRON' ) ) && ( DISABLE_WP_CRON == true ) ) {
					?>
					<div id="post-indexer-error" class="error">
						<p><?php _e( 'Deine Seite wurde <strong>DISABLE_WP_CRON</strong> definiert als <strong>true</strong>. In den meisten Fällen bedeutet dies, dass Multisite Index Deine Seite(n) möglicherweise nicht richtig indiziert, da er auf dem ClassicPress-Scheduler (WP_Cron) basiert. Wenn Du einen alternativen Cron ausführst, kannst Du diese Meldung ignorieren.', 'postindexer' ); ?></p>
					</div>
					<?php
				}
			}
		}

		function process_sites_page() {

			if ( isset( $_GET['action'] ) ) {
				switch ( $_GET['action'] ) {
					case 'disablesitepostindexer':
						$blog_id = $_GET['blog_id'];
						check_admin_referer( 'disable_site_postindexer_' . $blog_id );
						if ( ! current_user_can( 'manage_sites' ) ) {
							wp_die( __( 'Du hast keine Berechtigung, auf diese Seite zuzugreifen.' ) );
						}

						if ( $blog_id != '0' ) {
							$this->model->disable_indexing_for_blog( $blog_id );
							wp_safe_redirect( esc_url_raw( add_query_arg( array(
								'updated' => 'true',
								'action'  => 'disableindexing'
							), wp_get_referer() ) ) );
						} else {
							wp_safe_redirect( esc_url_raw( add_query_arg( array(
								'updated' => 'true',
								'action'  => 'not_disableindexing'
							), wp_get_referer() ) ) );
						}
						break;

					case 'enablesitepostindexer':
						$blog_id = $_GET['blog_id'];
						check_admin_referer( 'enable_site_postindexer_' . $blog_id );
						if ( ! current_user_can( 'manage_sites' ) ) {
							wp_die( __( 'Du hast keine Berechtigung, auf diese Seite zuzugreifen.' ) );
						}

						if ( $blog_id != '0' ) {
							$this->model->enable_indexing_for_blog( $blog_id );
							wp_safe_redirect( esc_url_raw( add_query_arg( array(
								'updated' => 'true',
								'action'  => 'enableindexing'
							), wp_get_referer() ) ) );
						} else {
							wp_safe_redirect( esc_url_raw( add_query_arg( array(
								'updated' => 'true',
								'action'  => 'not_enableindexing'
							), wp_get_referer() ) ) );
						}
						break;
					case 'editsitepostindexer':
						break;

					case 'rebuildsitepostindexer':
						$blog_id = $_GET['blog_id'];
						check_admin_referer( 'rebuild_site_postindexer_' . $blog_id );
						if ( ! current_user_can( 'manage_sites' ) ) {
							wp_die( __( 'Du hast keine Berechtigung, auf diese Seite zuzugreifen.' ) );
						}

						if ( $blog_id != '0' ) {
							$this->model->rebuild_blog( $blog_id );
							wp_safe_redirect( esc_url_raw( add_query_arg( array(
								'updated' => 'true',
								'action'  => 'rebuildindexing'
							), wp_get_referer() ) ) );
						} else {
							wp_safe_redirect( esc_url_raw( add_query_arg( array(
								'updated' => 'true',
								'action'  => 'not_rebuildindexing'
							), wp_get_referer() ) ) );
						}
						break;

					case 'updatepostindexersitesettings':
						$blog_id = $_GET['blog_id'];
						check_admin_referer( 'postindexer_update_site_settings_' . $blog_id );
						if ( ! current_user_can( 'manage_sites' ) ) {
							wp_die( __( 'Du hast keine Berechtigung, auf diese Seite zuzugreifen.' ) );
						}

						$this->model->switch_to_blog( $blog_id );

						// Indexing
						if ( $_GET['postindexer_active'] == 'yes' ) {
							$indexing = get_option( 'postindexer_active', 'yes' );
							if ( $indexing != 'yes' ) {
								// Only set for indexing and queue if it's not already enabled
								$this->model->enable_indexing_for_blog( $blog_id );
							}
						} elseif ( $_GET['postindexer_active'] == 'no' ) {
							$this->model->disable_indexing_for_blog( $blog_id );
						}

						// Post types
						$post_types = $_GET['postindexer_posttypes'];
						$to_store   = array();
						if ( is_array( $post_types ) ) {
							array_map( 'trim', $post_types );

							//$live_post_types = get_post_types( '', 'objects' );
							$live_post_types = $this->model->get_active_post_types();
							// Run a check to make sure no erronious post types have been passed
							foreach ( $post_types as $key => $post_type ) {
								if ( in_array( $post_type, array_keys( $live_post_types ) ) ) {
									$to_store[] = $post_type;
								}
							}

							update_option( 'postindexer_posttypes', $to_store );
						}

						$this->model->restore_current_blog();
						//changed_siteindexing
						wp_safe_redirect( esc_url_raw( add_query_arg( array(
							'updated' => 'true',
							'action'  => 'changed_siteindexing'
						), $_GET['comefrom'] ) ) );
						break;

				}
			}

		}

		function add_sites_column_heading( $columns ) {

			$columns['postindexer'] = __( 'Indizierung', 'postindexer' );

			return $columns;
		}

		function add_sites_column_data( $column_name, $blog_id ) {

			if ( $column_name == 'postindexer' ) {
				$indexing = get_blog_option( $blog_id, 'postindexer_active', 'yes' );
				if ( $indexing == 'yes' ) {

					// Find out if this is in the queue
					if ( $this->model->is_in_rebuild_queue( $blog_id ) ) {
						?>
						<div class='smallrebuildclock'>&nbsp;</div>
						<?php
					}

					$posttypes = get_blog_option( $blog_id, 'postindexer_posttypes', $this->global_post_types );
					echo implode( ', ', $posttypes );
					?>
					<div class="row-actions">
						<span class="disable">
							<a class='postindexersitedisablelink'
							   href='<?php echo wp_nonce_url( network_admin_url( "sites.php?action=disablesitepostindexer&amp;blog_id=" . $blog_id . "" ), 'disable_site_postindexer_' . $blog_id ); ?>'><?php _e( 'Deaktivieren', 'postindexer' ); ?></a>
						</span> |
						<span class="edit">
							<a class='postindexersiteeditlink'
							href='<?php echo wp_nonce_url( admin_url( "admin-ajax.php?action=editsitepostindexer&amp;blog_id=" . $blog_id . "" ), 'edit_site_postindexer_' . $blog_id ); ?>'
							data-psource-modal-open="siteedit-modal">
							<?php _e( 'Bearbeiten', 'postindexer' ); ?>
							</a>
						</span> |
						<span class="rebuild">
							<a class='postindexersiterebuildlink'
							   href='<?php echo wp_nonce_url( network_admin_url( "sites.php?action=rebuildsitepostindexer&amp;blog_id=" . $blog_id . "" ), 'rebuild_site_postindexer_' . $blog_id ); ?>'><?php _e( 'Wiederaufbauen', 'postindexer' ); ?></a>
						</span> |
						<span class="summary">
							<a class='postindexersitesummarylink'
							   href='<?php echo wp_nonce_url( admin_url( "admin-ajax.php?action=summarysitepostindexer&amp;blog_id=" . $blog_id . "" ), 'summary_site_postindexer_' . $blog_id ); ?>'><?php _e( 'Statistiken', 'postindexer' ); ?></a>
						</span>
					</div>
					<?php
				} else {
					_e( 'Not Indexing', 'postindexer' );
					?>
					<div class="row-actions">
						<span class="enable">
							<a class='postindexersiteenablelink'
							   href='<?php echo wp_nonce_url( network_admin_url( "sites.php?action=enablesitepostindexer&amp;blog_id=" . $blog_id . "" ), 'enable_site_postindexer_' . $blog_id ); ?>'><?php _e( 'Aktivieren', 'postindexer' ); ?></a>
						</span> |
						<span class="edit">
							<a class='postindexersitesummarylink'
							href='<?php echo wp_nonce_url( admin_url( "admin-ajax.php?action=summarysitepostindexer&amp;blog_id=" . $blog_id . "" ), 'summary_site_postindexer_' . $blog_id ); ?>'
							data-psource-modal-open="sitesummary-modal">
							<?php _e( 'Statistiken', 'postindexer' ); ?>
							</a>
						</span>
					</div>
					<?php
				}
			}

		}

		function summary_site_postindexer() {

			// Enqueue the JS we need

			// Enqueue the graphing library
			wp_enqueue_script( 'flot_js', $this->base_url . 'js/jquery.flot.js', array( 'jquery' ) );
			wp_enqueue_script( 'flot_pie_js', $this->base_url . 'js/jquery.flot.pie.js', array( 'jquery', 'flot_js' ) );
			wp_enqueue_script( 'flot_stack_js', $this->base_url . 'js/jquery.flot.stack.js', array(
				'jquery',
				'flot_js'
			) );

			wp_enqueue_style( 'colors' );
			wp_enqueue_script( 'jquery' );

			// Add in header for IE users
			add_action( 'admin_head', array( $this, 'dashboard_iehead' ) );
			// Add in the chart data we need for the
			add_action( 'admin_head', array( $this, 'dashboard_singlesitechartdata' ) );

			//wp_enqueue_style('postindexernetworksettings', $this->base_url . 'css/options.postindexer.css');

			wp_enqueue_script( 'postindexerscript', $this->base_url . 'js/sitestats.postindexer.js', array( 'jquery' ) );

			_wp_admin_html_begin();
			?>
			<title><?php _e( 'Webseiten-Index Zusammenfassung', 'postindexer' ); ?></title>
			<?php

			do_action( 'admin_print_styles' );
			do_action( 'admin_print_scripts' );
			do_action( 'admin_head' );

			?>
			</head>
			<body<?php if ( isset( $GLOBALS['body_id'] ) ) {
				echo ' id="' . $GLOBALS['body_id'] . '"';
			} ?> class="no-js">
			<script type="text/javascript">
				document.body.className = document.body.className.replace('no-js', 'js');
			</script>
			<div id='singlesitestats' style='height: 380px; width: 600px; margin-top: 10px; margin-bottom: 10px;'>
			</div>
			<?php
			do_action( 'admin_print_footer_scripts' );
			?>
			<script type="text/javascript">if (typeof wpOnload == 'function')wpOnload();</script>
			</body>
			</html>
			<?php
			exit;
		}

		// Code from this function based on code from AJAX Media Upload function
		function edit_site_postindexer() {

			if ( isset( $_GET['action'] ) ) {
				switch ( $_GET['action'] ) {
					case 'updatepostindexersitesettings':
						$blog_id = $_GET['blog_id'];
						check_admin_referer( 'postindexer_update_site_settings_' . $blog_id );
						$this->model->switch_to_blog( $blog_id );
						update_option( 'postindexer_active', $_GET['postindexer_active'] );
						$this->model->restore_current_blog();
						break;
				}
			}

			_wp_admin_html_begin();
			?>
			<title><?php _e( 'PS-Multisite Index Einstellungen', 'postindexer' ); ?></title>
			<?php

			wp_enqueue_style( 'colors' );
			//wp_enqueue_style( 'media' );
			//wp_enqueue_style( 'ie' );
			wp_enqueue_script( 'jquery' );

			do_action( 'admin_print_styles' );
			do_action( 'admin_print_scripts' );
			do_action( 'admin_head' );

			?>
			</head>
			<body<?php if ( isset( $GLOBALS['body_id'] ) ) {
				echo ' id="' . $GLOBALS['body_id'] . '"';
			} ?> class="no-js">
			<script type="text/javascript">
				document.body.className = document.body.className.replace('no-js', 'js');
			</script>
			<?php
			$this->edit_site_content();

			do_action( 'admin_print_footer_scripts' );
			?>
			<script type="text/javascript">if (typeof wpOnload == 'function')wpOnload();</script>
			</body>
			</html>
			<?php
			exit;
		}

		function edit_site_content() {

			if ( ! isset( $_GET['blog_id'] ) ) {
				wp_die( __( 'Cheatin&#8217; uh?' ) );
			} else {

				$blog_id = $_GET['blog_id'];
				check_admin_referer( 'edit_site_postindexer_' . $blog_id );

				$this->model->switch_to_blog( $blog_id );
				?>
				<form action="" class="" id="postindexer-form" method='get'>

					<input type='hidden' name='action' value='updatepostindexersitesettings'/>
					<input type='hidden' name='blog_id' value='<?php echo $blog_id; ?>'/>
					<input type='hidden' name='comefrom' value='<?php echo esc_attr( wp_get_referer() ); ?>'/>
					<?php
					wp_nonce_field( 'postindexer_update_site_settings_' . $blog_id );
					?>


					<h3 class="media-title"><?php echo __( "PS-Multisite Indizierung", "postindexer" ); ?></h3>
					<p class='description'><?php _e( 'Du kannst die Post-Indizierung für diese Seite mithilfe der folgenden Einstellung aktivieren oder deaktivieren.', 'postindexer' ); ?></p>

					<table>
						<tbody>
						<tr>
							<th style='min-width: 150px;'><?php _e( 'Indizierung ist', 'postindexer' ); ?></th>
							<td>
								<?php
								$indexing = get_option( 'postindexer_active', 'yes' );
								?>
								<div style="display:flex;align-items:center;gap:1.2em;">
									<label class="ps-switch">
										<input type="checkbox" name="postindexer_active" value="yes" <?php checked( $indexing, 'yes' ); ?> />
										<span class="ps-slider"></span>
									</label>
									<span class="ps-status-label" style="font-weight:bold;color:<?php echo ($indexing==='yes') ? '#2ecc40' : '#aaa'; ?>;">
										<?php echo ($indexing==='yes') ? __('Aktiviert','postindexer') : __('Deaktiviert','postindexer'); ?>
									</span>
								</div>
								<input type="hidden" name="postindexer_active_hidden" value="no" />
								<div style="margin-top:1em;max-width:420px;padding:1em 1.3em;background:#fffbeige;border:1.5px solid #ffe58f;border-radius:10px;box-shadow:0 2px 12px rgba(255,215,0,0.07);font-size:1.04em;color:#444;">
									<?php _e('Wenn deaktiviert, werden keine neuen Beiträge oder Kommentare mehr für diese Seite indiziert.','postindexer'); ?>
								</div>
								<style>
								.ps-switch { position: relative; display: inline-block; width: 48px; height: 24px; }
								.ps-switch input { opacity: 0; width: 0; height: 0; }
								.ps-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px; }
								.ps-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
								.ps-switch input:checked + .ps-slider { background-color: #2ecc40; }
								.ps-switch input:checked + .ps-slider:before { transform: translateX(24px); }
								</style>
								<script>
								document.addEventListener('DOMContentLoaded',function(){
									var sw = document.querySelector('.ps-switch input[type=checkbox][name="postindexer_active"]');
									var label = document.querySelector('.ps-status-label');
									if(sw && label){
										sw.addEventListener('change',function(){
											if(this.checked){ label.textContent = 'Aktiviert'; label.style.color = '#2ecc40'; this.value = 'yes'; }
											else { label.textContent = 'Deaktiviert'; label.style.color = '#aaa'; this.value = 'no'; }
										});
									}
								});
								</script>
							</td>
						</tr>
						</tbody>
					</table>

					<h3 class="media-title"><?php echo __( "Beitrags-Typen für den Index", "postindexer" ); ?></h3>
					<p class='description'><?php _e( 'Wähle die Beitragstypen aus, die für diese Seite indiziert werden sollen.', 'postindexer' ); ?></p>

					<table>
						<tbody>
						<tr>
							<th style='min-width: 150px; vertical-align: top;'><?php _e( 'Derzeit indiziert', 'postindexer' ); ?></th>
							<td>
								<?php

								$indexingtypes = get_option( 'postindexer_posttypes', $this->global_post_types );
								//$post_types = get_post_types( '' , 'objects' );
								$post_types = $this->model->get_active_post_types();

								foreach ( $post_types as $post_type ) {
									?>
									<label><input type='checkbox' name='postindexer_posttypes[]'
									              value='<?php echo $post_type; ?>' <?php if ( in_array( $post_type, $indexingtypes ) ) {
											echo "checked='checked'";
										} ?> />&nbsp;<?php echo $post_type; ?></label><br/>
									<?php
								}

								?>
							</td>
						</tr>
						</tbody>
					</table>


					<p class="savebutton ml-submit">
						<input name="save" id="save" class="button-primary"
						       value="<?php _e( 'Alle Änderungen speichern', 'postindexer' ); ?>" type="submit">
					</p>
				</form>

				<?php
				$this->model->restore_current_blog();
			}

		}

		function add_admin_page() {
			global $wpmudev_notices;

			$main_slug = 'ps-multisite-index';
			$cap = 'manage_network_options';

			// Hauptmenü
			add_menu_page(
				__( 'PS-Multisite Index', 'postindexer' ),
				__( 'PS-Multisite Index', 'postindexer' ),
				$cap,
				$main_slug,
				array( $this, 'handle_postindexer_page' ),
				'dashicons-networking',
				3
			);

			// Dashboard/Übersicht als erste Subpage (bisherige Seite)
			add_submenu_page(
				$main_slug,
				__( 'Post Index', 'postindexer' ),
				__( 'Post Index', 'postindexer' ),
				$cap,
				$main_slug,
				array( $this, 'handle_postindexer_page' )
			);

			// Erweiterungen als Subpage über eigene Klasse
			$this->extensions_admin->register_menu( $main_slug, $cap );

			// Monitoring-Seite als neue Subpage
            add_submenu_page(
                $main_slug,
                __( 'Monitoring', 'postindexer' ),
                __( 'Monitoring', 'postindexer' ),
                $cap,
                $main_slug . '-monitoring',
                array( $this, 'render_monitoring_page' )
            );

			$wpmudev_notices[] = array(
				'id'      => 30,
				'name'    => 'PS-Multisite Index',
				'screens' => array( $main_slug . '-network' ),
			);
		}

		function render_extensions_page() {
			echo '<div class="wrap"><h1>' . esc_html__( 'Erweiterungen', 'postindexer' ) . '</h1>';
			echo '<p>Hier findest du alle verfügbaren Erweiterungen für den PS-Multisite Index. Aktiviere oder konfiguriere sie nach Bedarf.</p>';
			// Übersicht als Grid
			echo '<style>
			.ps-extensions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2em; margin-top:2em; }
			.ps-extension-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 2em 1.5em 1.5em 1.5em; display: flex; flex-direction: column; align-items: flex-start; }
			.ps-extension-card h2 { margin-top:0; font-size:1.3em; }
			.ps-extension-card p { color:#444; font-size:1em; }
			.ps-extension-status { margin: 0.5em 0 1em 0; font-size:0.95em; color:#888; display:flex; align-items:center; gap:1em; }
			.ps-switch { position: relative; display: inline-block; width: 48px; height: 24px; }
			.ps-switch input { opacity: 0; width: 0; height: 0; }
			.ps-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px; }
			.ps-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
			.ps-switch input:checked + .ps-slider { background-color: #2ecc40; }
			.ps-switch input:checked + .ps-slider:before { transform: translateX(24px); }
			.ps-status-label { font-weight:bold; min-width:50px; display:inline-block; }
			.ps-extension-actions { margin-top:auto; display:flex; gap:1em; }
			.ps-extension-actions .button { min-width:110px; }
			</style>';
			// JS für Status-Label
			echo '<script>
			document.addEventListener("DOMContentLoaded",function(){
				const toggles = document.querySelectorAll(".ps-switch input[type=checkbox]");
				toggles.forEach(function(toggle){
					toggle.addEventListener("change",function(){
						const label = this.closest(".ps-extension-status").querySelector(".ps-status-label");
						if(this.checked){ label.textContent = "Aktiv"; label.style.color = "#2ecc40"; }
						else { label.textContent = "Inaktiv"; label.style.color = "#aaa"; }
					});
				});
			});
			</script>';
			echo '<div class="ps-extensions-grid">';
			// Erweiterung 1: Aktuelle Netzwerkbeiträge
			echo '<div class="ps-extension-card">
				<h2>Aktuelle Netzwerkbeiträge</h2>
				<p>Zeigt eine anpassbare Liste der letzten Beiträge aus dem gesamten Multisite-Netzwerk an. Ermöglicht flexible Darstellung und Filterung.</p>
				<div class="ps-extension-status">
					<span class="ps-status-label" style="color:#2ecc40;">Aktiv</span>
					<label class="ps-switch"><input type="checkbox" checked><span class="ps-slider"></span></label>
				</div>
				<div class="ps-extension-actions">
					<a href="' . esc_url( network_admin_url( 'admin.php?page=network-posts-settings' ) ) . '" class="button button-primary">Einstellungen</a>
				</div>
			</div>';
			// Platzhalter für weitere Erweiterungen
			echo '<div class="ps-extension-card">
				<h2>Beispiel-Erweiterung</h2>
				<p>Hier könnte eine weitere Erweiterung stehen. Mehr demnächst!</p>
				<div class="ps-extension-status">
					<span class="ps-status-label" style="color:#aaa;">Inaktiv</span>
					<label class="ps-switch"><input type="checkbox"><span class="ps-slider"></span></label>
				</div>
				<div class="ps-extension-actions">
					<button class="button button-primary" disabled>Einstellungen</button>
				</div>
			</div>';
			echo '</div>';
			echo '</div>';
		}
		function add_header_postindexer_page() {

			// Enqueue the graphing library
			wp_enqueue_script( 'flot_js', $this->base_url . 'js/jquery.flot.js', array( 'jquery' ) );
			wp_enqueue_script( 'flot_pie_js', $this->base_url . 'js/jquery.flot.pie.js', array( 'jquery', 'flot_js' ) );
			wp_enqueue_script( 'flot_stack_js', $this->base_url . 'js/jquery.flot.stack.js', array(
				'jquery',
				'flot_js'
			) );
			// Add in header for IE users
			add_action( 'admin_head', array( $this, 'dashboard_iehead' ) );
			// Add in the chart data we need for the
			add_action( 'admin_head', array( $this, 'dashboard_chartdata' ) );

			wp_enqueue_style( 'postindexernetworksettings', $this->base_url . 'css/options.postindexer.css' );

			wp_enqueue_script( 'postindexerscript', $this->base_url . 'js/options.postindexer.js', array( 'jquery' ) );

			$this->process_postindexer_page();
		}

		function dashboard_iehead() {
			echo '<!--[if lt IE 8]><script type="text/javascript" src="' . $this->base_url . 'js/excanvas.min.js' . '"></script><![endif]-->';
		}

		function process_postindexer_page() {

			if ( ! isset( $_POST['action'] ) ) {
				return;
			}

			switch ( $_POST['action'] ) {

				case 'postindexerrebuildallsites':
					check_admin_referer( 'postindexer_rebuild_all_sites' );
					$this->model->rebuild_all_blogs();
					wp_safe_redirect( esc_url_raw( add_query_arg( array( 'msg' => 1 ), wp_get_referer() ) ) );
					exit;
					break;

				case 'postindexerupdateglobaloptions':
					check_admin_referer( 'postindexer_update_global_options' );
					$posttypes = array_map( 'trim', explode( "\n", $_POST['post_types'] ) );
					update_site_option( 'postindexer_globalposttypes', $posttypes );

					update_site_option( 'postindexer_agedposts', array(
						'agedunit'   => (int) $_POST['agedunit'],
						'agedperiod' => $_POST['agedperiod']
					) );

					wp_safe_redirect( esc_url_raw( add_query_arg( array( 'msg' => 2 ), wp_get_referer() ) ) );
					exit;
					break;

			}

		}

		function get_json( $results ) {
			if ( function_exists( 'json_encode' ) ) {
				return json_encode( $results );
			} else {
				// PHP4 version
				require_once( ABSPATH . "wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php" );
				$json_obj = new Moxiecode_JSON();

				return $json_obj->encode( $results );
			}

		}

		function return_json( $results ) {

			// Check for callback
			if ( isset( $_GET['callback'] ) ) {
				// Add the relevant header
				@header( 'Content-type: text/javascript' );
				echo addslashes( $_GET['callback'] ) . " (";
			} else {
				if ( isset( $_GET['pretty'] ) ) {
					// Will output pretty version
					@header( 'Content-type: text/html' );
				} else {
					@header( 'Content-type: application/json' );
				}
			}

			if ( function_exists( 'json_encode' ) ) {
				echo json_encode( $results );
			} else {
				// PHP4 version
				require_once( ABSPATH . "wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php" );
				$json_obj = new Moxiecode_JSON();
				echo $json_obj->encode( $results );
			}

			if ( isset( $_GET['callback'] ) ) {
				echo ")";
			}

		}

		function get_data( $results, $str = false ) {

			$data = array();

			foreach ( (array) $results as $key => $res ) {
				if ( $str ) {
					$data[] = "[ " . $key . ", '" . $res . "' ]";
				} else {
					$data[] = "[ " . $key . ", " . $res . " ]";
				}
			}

			return "[ " . implode( ", ", $data ) . " ]";

		}

		function dashboard_singlesitechartdata() {

			$blog_counts = $this->model->get_summary_single_site_blog_post_type_totals( (int) $_GET['blog_id'] );

			// Sort out the post types for the blog
			if ( ! empty( $blog_counts ) ) {
				$blog_counts_results = array();
				$blog_counts_data    = array();
				$blog_count_max      = 0;

				$blog_type_ticks = array();

				$n = 1;
				foreach ( $blog_counts as $bc ) {
					if ( ! array_key_exists( $bc->BLOG_ID, $blog_type_ticks ) ) {
						$blog_type_ticks[ $bc->BLOG_ID ] = array(
							(int) $n ++,
							get_blog_option( $bc->BLOG_ID, 'blogname' )
						);
					}
					if ( $bc->blog_type_count > $blog_count_max ) {
						$blog_count_max = $bc->blog_type_count;
					}
				}

				foreach ( $blog_counts as $bc ) {
					$val                                                 = $blog_type_ticks[ $bc->BLOG_ID ][0];
					$blog_counts_results[ $bc->post_type ][ (int) $val ] = (int) $bc->blog_type_count;
				}

				foreach ( $blog_counts_results as $key => $value ) {
					$blog_counts_data[ $key ] = array( "label" => $key, "data" => $value );
				}

				$blog_counts_data = array_values( $blog_counts_data );

			}


			echo "\n" . '<script type="text/javascript">';
			echo "\n" . '/* <![CDATA[ */ ' . "\n";

			echo "var blogcountdata = [\n";
			foreach ( $blog_counts_data as $bcd ) {
				echo "{label: '" . $bcd["label"] . "', data: " . $this->get_data( $bcd['data'] ) . "},\n";
			}
			echo "];\n";
			echo "var blogcountinfo = " . $this->get_json( array(
					"ticks"    => array_values( $blog_type_ticks ),
					"maxcount" => $blog_count_max
				) ) . ";\n";

			echo "\n" . '/* ]]> */ ';
			echo '</script>';

		}

		function dashboard_chartdata() {

			$post_type_results   = array();
			$post_type_max       = 0;
			$blog_counts_results = array();
			$blog_counts_data    = array();
			$blog_count_max      = 0;

			$blog_type_ticks = array();

			$post_type_counts = $this->model->get_summary_post_types();
			$blog_counts      = $this->model->get_summary_blog_post_type_totals();

			if ( ! empty( $post_type_counts ) ) {
				foreach ( $post_type_counts as $ptc ) {
					$post_type_results[] = array(
						'label' => __( $ptc->post_type, 'postindexer' ),
						'data'  => (int) $ptc->post_type_count
					);
					if ( $ptc->post_type_count > $post_type_max ) {
						$post_type_max = $ptc->post_type_count;
					}
				}

				$post_type_ticks = array();
			}

			// Sort out the post types for the blog
			if ( ! empty( $blog_counts ) ) {

				$n = 1;
				foreach ( $blog_counts as $bc ) {
					if ( ! array_key_exists( $bc->BLOG_ID, $blog_type_ticks ) ) {
						$blog_type_ticks[ $bc->BLOG_ID ] = array(
							(int) $n ++,
							str_replace( " ", "<br/>", get_blog_option( $bc->BLOG_ID, 'blogname' ) )
						);
					}
					if ( $bc->blog_type_count > $blog_count_max ) {
						$blog_count_max = $bc->blog_type_count;
					}
				}

				foreach ( $blog_counts as $bc ) {
					$val                                                 = $blog_type_ticks[ $bc->BLOG_ID ][0];
					$blog_counts_results[ $bc->post_type ][ (int) $val ] = (int) $bc->blog_type_count;
				}

				foreach ( $blog_counts_results as $key => $value ) {
					$blog_counts_data[ $key ] = array( "label" => $key, "data" => $value );
				}

				$blog_counts_data = array_values( $blog_counts_data );

			}


			echo "\n" . '<script type="text/javascript">';
			echo "\n" . '/* <![CDATA[ */ ' . "\n";

			echo "var posttypedata = " . $this->get_json( array( "chart" => $post_type_results ) ) . ";\n";
			echo "var blogcountdata = [\n";
			if ( ! empty( $blog_counts_data ) ) {
				foreach ( $blog_counts_data as $bcd ) {
					echo "{label: '" . $bcd["label"] . "', data: " . $this->get_data( $bcd['data'] ) . "},\n";
				}
			}
			echo "];\n";
			echo "var blogcountinfo = " . $this->get_json( array(
					"ticks"    => array_values( (array) $blog_type_ticks ),
					"maxcount" => $blog_count_max
				) ) . ";\n";

			echo "\n" . '/* ]]> */ ';
			echo '</script>';

		}

		function dashboard_news() {
			global $page, $action;

			$plugin = get_plugin_data( WP_PLUGIN_DIR . '/ps-postindexer/post-indexer.php' );

			?>
			<div id="post-indexer-summary" class="postbox ">
				<h3 class="hndle"><span><?php _e( 'Beitrags Index Zusammenfassung', 'postindexer' ); ?></span></h3>
				<div class="inside">

					<div class="table table_content">
						<p class="sub"><?php _e( 'Indizierte Beitragstypen', 'postindexer' ); ?></p>
						<?php
						// Get the counts for the post types
						$post_type_counts = $this->model->get_summary_post_types();
						?>
						<table id="post-indexer-indexed-post-types" class="widefat">
							<tbody>
							<?php
							$trclass = 'alt';
							foreach ( $post_type_counts as $ptc ) {
								?>
								<tr class="<?php echo $trclass; ?>">
									<td class="first b b-posts"><?php echo $ptc->post_type_count; ?></td>
									<td class="t posts"><?php echo __( $ptc->post_type, 'postindexer' ); ?></td>
								</tr>
								<?php
								if ( $trclass == '' ) {
									$trclass = 'alt';
								} else {
									$trclass = '';
								}
							}
							?>
							</tbody>
						</table>
					</div>

					<div class="table table_discussion">
						<p class="sub"><?php _e( 'Meist indizierten Webseiten', 'postindexer' ); ?></p>
						<?php
						// Get the counts for the blogs
						$blog_counts = $this->model->get_summary_blog_totals();
						?>
						<table id="post-indexer-most-indexed-sites" class="widefat">
							<tbody>
							<?php
							$trclass = 'alt';
							foreach ( $blog_counts as $bc ) {
								?>
								<tr class="<?php echo $trclass; ?>">
									<td class="first b b-posts"><?php echo $bc->blog_count; ?></td>
									<td class="t posts"><?php echo get_blog_option( $bc->BLOG_ID, 'blogname' ); ?></td>
								</tr>
								<?php
								if ( $trclass == '' ) {
									$trclass = 'alt';
								} else {
									$trclass = '';
								}
								//$trclass = '';
							}
							?>
							</tbody>
						</table>
					</div>

					<br class="clear">

				</div>
			</div>
			<?php
		}

		function dashboard_meta() {
			global $page, $action;

			$plugin = get_plugin_data( WP_PLUGIN_DIR . '/ps-postindexer/post-indexer.php' );

			?>
			<div id="post-indexer-plugin-info" class="postbox ">
				<h3 class="hndle"><span><?php _e( 'PS-Multisite Index Prozess Information', 'postindexer' ); ?></span></h3>
				<div class="inside">
					<p><?php echo __( '<strong>PS-Multisite Index-Version</strong>:', 'postindexer' ) . " " . $plugin['Version'] ?></p>
					<p><?php _e( '<strong>Debug-Protokollierung</strong>: ', 'postindexer' ); ?>
						<?php if ( defined( 'PI_CRON_DEBUG' ) && PI_CRON_DEBUG == true ) {
							_e( 'Aktiviert', 'postindexer' );
							if ( defined( 'PI_CRON_DEBUG_KEEP_LAST' ) ) {
								echo ' (' . PI_CRON_DEBUG_KEEP_LAST . ' ' . __( 'Einträge', 'postindexer' ) . ')';
							}

						} else {
							_e( 'Deaktiviert', 'postindexer' );
						}
						?></p>

					<?php if ( defined( 'WP_CRON_LOCK_TIMEOUT' ) ) {
						?>
						<p><?php echo __( '<strong>Zeitlimit für WP Cron-Sperre</strong>:', 'postindexer' ) . ' ' . WP_CRON_LOCK_TIMEOUT; ?></p><?php
					}
					?>

					<?php
					/*
					if ((defined('DISABLE_WP_CRON')) && (DISABLE_WP_CRON == true)) {
						?><p><?php _e('Your site has <strong>DISABLE_WP_CRON</strong> defined as <strong>true</strong>. In most cases this means Multisite Index may not properly index your site(s) as it relies on the ClassicPress scheduler (WP_Cron). If you are running an alternate cron you can ignore this message.', 'postindexer'); ?></p><?php
					}
					*/
					if ( ( isset( $_GET['post_indexer_clear_cron'] ) ) && ( ! empty( $_GET['post_indexer_clear_cron'] ) ) ) {
						$post_indexer_cron_clear = esc_attr( trim( $_GET['post_indexer_clear_cron'] ) );
						if ( ! empty( $post_indexer_cron_clear ) ) {
							// The value of the nonce is the value of the cron entry we want to clear.
							if ( ( isset( $_GET['post_indexer_clear_cron_nonce'] ) ) && ( wp_verify_nonce( $_GET['post_indexer_clear_cron_nonce'], $post_indexer_cron_clear ) ) ) {
								wp_clear_scheduled_hook( $post_indexer_cron_clear );
							}
						}
					}

					$date_format      = get_option( 'date_format' );
					$date_format      = str_replace( 'F', 'M', $date_format );
					$time_format      = get_option( 'time_format' );
					$date_time_format = $date_format . ' ' . $time_format;
					//echo "date_time_format[". $date_time_format ."]<br />";

					?>
					<p><?php _e( 'Beitragsindex WP Cron Einträge', 'postindexer' ) ?> <span
							style="float:right;"><?php _e( 'Aktuelle Uhrzeit:', 'postindexer' ) ?><?php echo date_i18n( $date_time_format, time() + get_option( 'gmt_offset' ) * 3600, false ); ?></span>
					</p>

					<?php

					//$crons = _get_cron_array();
					//echo "crons<pre>"; print_r($crons); echo "</pre>";

					//$doing_cron_transient = get_transient( 'doing_cron');
					//echo "doing_cron_transient[". $doing_cron_transient ."]<br />";
					?>


					<table id="postindexer-crons" class="widefat">
						<thead>
						<tr>
							<th class="col col-pi-action"><?php _e( 'Aktion', 'postindexer' ); ?></th>
							<th class="col col-pi-entry"><?php _e( 'Eintrag', 'postindexer' ); ?></th>
							<th class="col col-pi-next-run"><?php _e( 'Nächster Lauf', 'postindexer' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php
						$postindexer_crons = array(
							'postindexer_firstpass_cron'     => array(
								'label' => __( '1. Durchgang', 'postindexer' ),
								'limit' => '(' . PI_CRON_SITE_PROCESS_FIRSTPASS . ' ' . __( ' Seiten / Stapel', 'postindexer' ) . ')'
							),
							'postindexer_secondpass_cron'    => array(
								'label' => __( '2. Durchgang', 'postindexer' ),
								'limit' => '(' . PI_CRON_SITE_PROCESS_SECONDPASS . ' ' . __( ' Seiten', 'postindexer' ) . ', ' .
								           PI_CRON_POST_PROCESS_SECONDPASS . ' ' . __( 'Beiträge', 'postindexer' ) . ' ' . __( '/ Stapel', 'postindexer' ) . ')'
							),
							'postindexer_tagtidy_cron'       => array(
								'label' => __( 'Beitrags Tags ordentlich', 'postindexer' ),
								'limit' => '(' . PI_CRON_TIDY_DELETE_LIMIT . ' ' . __( ' Beiträge', 'postindexer' ) . ', ' .
								           PI_CRON_TIDY_COUNT_LIMIT . ' ' . __( 'Tags', 'postindexer' ) . ' ' . __( '/ Stapel', 'postindexer' ) . ')'
							),
							'postindexer_postmetatidy_cron'  => array(
								'label' => __( 'Beitrags Meta ordentlich', 'postindexer' ),
								'limit' => '(' . PI_CRON_TIDY_DELETE_LIMIT . ' ' . __( ' Löschen / Stapel', 'postindexer' ) . ')'
							),
							'postindexer_agedpoststidy_cron' => array(
								'label' => __( 'Gealterte Beiträge ordentlich', 'postindexer' ),
								'limit' => '(' . PI_CRON_TIDY_DELETE_LIMIT . ' ' . __( ' Löschen / Stapel', 'postindexer' ) . ')'
							),
						);

						$class = 'alt';

						foreach ( $postindexer_crons as $postindexer_cron_key => $postindexer_cron_info ) {
							?>
							<tr class='<?php echo $class; ?>'>
								<td style="text-align: center"><a
										title="<?php _e( 'Cron-Eintrag löschen', 'postindexer' ) ?>" href="<?php

									$clear_url = esc_url_raw( add_query_arg( 'post_indexer_clear_cron', $postindexer_cron_key ) );
									$clear_url = wp_nonce_url( $clear_url, $postindexer_cron_key, 'post_indexer_clear_cron_nonce' );
									echo $clear_url;

									?>"><?php _e( 'löschen', 'postindexer' ) ?></a></td>
								<td><?php
									if ( ( isset( $postindexer_cron_info['label'] ) ) && ( ! empty( $postindexer_cron_info['label'] ) ) ) {
										echo $postindexer_cron_info['label'];
									} else {
										echo $postindexer_cron;
									}

									if ( ( isset( $postindexer_cron_info['limit'] ) ) && ( ! empty( $postindexer_cron_info['limit'] ) ) ) {
										echo ' ' . $postindexer_cron_info['limit'];
									}

									?><br/><?php

									$_locker = new ProcessLocker( $postindexer_cron_key );
									// If we have the lock it means the real process is not running otherwise it would have the lock.
									$locker_out = '';


									$locker_info = $_locker->get_locker_info();
									//echo "locked: locker_info<pre>"; print_r($locker_info); echo "</pre>";
									if ( isset( $locker_info['pid'] ) ) {
										if ( strlen( $locker_out ) ) {
											$locker_out .= ', ';
										}
										$locker_out .= "pid: " . $locker_info['pid'];
									}
									if ( isset( $locker_info['time_start'] ) ) {
										if ( strlen( $locker_out ) ) {
											$locker_out .= ', ';
										}
										$locker_out .= "" . date_i18n( $date_time_format, $locker_info['time_start'] + get_option( 'gmt_offset' ) * 3600, true );
									}
									if ( isset( $locker_info['blog_id'] ) ) {
										if ( strlen( $locker_out ) ) {
											$locker_out .= ', ';
										}
										$locker_out .= "blog: " . $locker_info['blog_id'];
									}
									if ( isset( $locker_info['post_id'] ) ) {
										if ( strlen( $locker_out ) ) {
											$locker_out .= ', ';
										}
										$locker_out .= "post: " . $locker_info['post_id'];
									}
									if ( ! empty( $locker_out ) ) {
										if ( $_locker->is_locked() === false ) {
											echo __( 'aktiv:', 'postindexer' ) . ' ';
										} else {
											echo __( 'frühere:', 'postindexer' ) . ' ';
										}
										echo '<em>' . $locker_out . '</em>';
									}
									unset( $_locker );

									?></td>
								<td style="text-align: center"><?php

									$postindexer_cron_timestamp = wp_next_scheduled( $postindexer_cron_key );

									if ( $postindexer_cron_timestamp !== false ) {
										echo date_i18n( $date_time_format, $postindexer_cron_timestamp + get_option( 'gmt_offset' ) * 3600, true );
										echo '<br />(' . $postindexer_cron_timestamp . ')';

										$schedule_interval = wp_get_schedule( $postindexer_cron_key );
										if ( ! empty( $schedule_interval ) ) {
											echo ' ' . $schedule_interval;
										}
									} else {
										_e( 'Kein Plan', 'postindexer' );
									}
									?></td>
							</tr>
							<?php
							if ( $class == '' ) {
								$class = 'alt';
							} else {
								$class = '';
							}
						}
						?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}

		function dashboard_blog_stats() {

			?>
			<div id="blog-stats" class="postbox ">
				<h3 class="hndle"><span><?php _e( 'Die am häufigsten indizierten Webseiten im Netzwerk', 'postindexer' ); ?></span></h3>
				<div class="inside">
					<div id='blog-stats-chart' style='min-height: 400px;'>
					</div>
				</div>
			</div>
			<?php

		}

		function dashboard_post_type_stats() {

			?>
			<div id="post-type-stats" class="postbox ">
				<h3 class="hndle"><span><?php _e( 'Indizierte Beitragstypen', 'postindexer' ); ?></span></h3>
				<div class="inside">
					<div id='post-type-stats-chart' style='min-height: 150px;'>
					</div>
				</div>
			</div>
			<?php

		}

		function dashboard_rebuild_queue_stats() {

			?>
			<div id="rebuild-queue-stats" class="postbox ">
				<h3 class="hndle"><span><?php _e( 'Warteschlangenstatus für Indizierung', 'postindexer' ); ?></span></h3>
				<div class="inside">

					<div class="table table_content">
						<p class="sub"><?php _e( 'Aktuelle Warteschlangenübersicht', 'postindexer' ); ?></p>
						<?php
						// Get the queue counts

						?>
						<table>
							<tbody>
							<tr>
								<td class="first b b-posts"><?php echo $this->model->get_summary_sites_in_queue(); ?></td>
								<td class="t posts"><?php echo __( 'Webseiten in der Warteschlange', 'postindexer' ); ?></td>
							</tr>
							<tr>
								<td class="first b b-posts"><?php echo $this->model->get_summary_sites_in_queue_processing(); ?></td>
								<td class="t posts"><?php echo __( 'Webseiten, die gerade verarbeitet werden', 'postindexer' ); ?></td>
							</tr>
							<tr>
								<td class="first b b-posts"><?php echo $this->model->get_summary_sites_in_queue_not_processing(); ?></td>
								<td class="t posts"><?php echo __( 'Webseiten, die auf ihre Bearbeitung warten', 'postindexer' ); ?></td>
							</tr>
							<tr>
								<td class="first b b-posts"><?php echo $this->model->get_summary_sites_in_queue_finish_next_pass(); ?></td>
								<td class="t posts"><?php echo __( 'Die Verarbeitung der Webseiten wird beim nächsten Durchgang abgeschlossen', 'postindexer' ); ?></td>
							</tr>
							</tbody>
						</table>
					</div>
					<br class="clear">


				</div>
			</div>
			<?php

		}

		function dashboard_last_indexed_stats() {

			?>
			<div id="last-indexed-stats" class="postbox ">
				<h3 class="hndle"><span><?php _e( 'Kürzlich indizierte Beiträge', 'postindexer' ); ?></span></h3>
				<div class="inside">
					<table class='widefat'>
						<thead>
						<tr>
							<th scope="col"><?php _e( 'Beitragstitel', 'postindexer' ); ?></th>
							<th scope="col"><?php _e( 'Seite', 'postindexer' ); ?></th>
						</tr>
						</thead>
						<tfoot>
						<tr>
							<th scope="col"><?php _e( 'Beitragstitel', 'postindexer' ); ?></th>
							<th scope="col"><?php _e( 'Seite', 'postindexer' ); ?></th>
						</tr>
						</tfoot>
						<tbody>
						<?php
						$recent = $this->model->get_summary_recently_indexed();
						if ( ! empty( $recent ) ) {
							?>
							<?php
							$class = 'alt';
							foreach ( $recent as $r ) {
								switch_to_blog( $r->BLOG_ID );
								?>
								<tr class='<?php echo $class; ?>'>
									<td style='width: 75%;' valign=top><a href='<?php echo get_permalink( $r->ID ); ?>'>
											<?php
											echo get_the_title( $r->ID );
											?>
										</a>
									</td>
									<td style='width: 25%;' valign=top><a href='<?php echo get_option( 'home' ); ?>'>
											<?php
											echo get_option( 'blogname' );
											?>
										</a>
									</td>
								</tr>
								<?php
								restore_current_blog();
								if ( $class == '' ) {
									$class = 'alt';
								} else {
									$class = '';
								}
							}
							?>
							<?php
						}
						?>
						</tbody>
					</table>
				</div>
			</div>
			<?php

		}

		function handle_statistics_page() {

			add_action( 'postindexer_dashboard_left', array( $this, 'dashboard_news' ) );
			add_action( 'postindexer_dashboard_left', array( $this, 'dashboard_meta' ) );
			add_action( 'postindexer_dashboard_left', array( $this, 'dashboard_blog_stats' ) );

			if ( $this->model->blogs_for_rebuilding() ) {
				$rebuild_queue = true;
				add_action( 'postindexer_dashboard_right', array( $this, 'dashboard_rebuild_queue_stats' ) );
			} else {
				$rebuild_queue = false;
			}

			add_action( 'postindexer_dashboard_right', array( $this, 'dashboard_post_type_stats' ) );
			add_action( 'postindexer_dashboard_right', array( $this, 'dashboard_last_indexed_stats' ) );

			?>
			<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
			<h2><?php _e( 'Netzwerk-Beiträge-Index-Statistiken', 'postindexer' ); ?></h2>

			<?php
			if ( $rebuild_queue ) {
				// Show a rebuilding message and timer
				?>
				<div id='rebuildingmessage'>
					<?php _e( 'Es befinden sich derzeit Elemente in Deiner Indexierungswarteschlange.', 'postindexer' ); ?>
				</div>
				<?php
			}
			?>

			<div id="dashboard-widgets-wrap">

				<div class="metabox-holder" id="dashboard-widgets">
					<div style="width: 49%;" class="postbox-container">
						<div class="meta-box-sortables ui-sortable" id="normal-sortables">
							<?php
							do_action( 'postindexer_dashboard_left' );
							?>
						</div>
					</div>

					<div style="width: 49%;" class="postbox-container">
						<div class="meta-box-sortables ui-sortable" id="side-sortables">
							<?php
							do_action( 'postindexer_dashboard_right' );
							?>
						</div>
					</div>

					<div style="display: none; width: 49%;" class="postbox-container">
						<div class="meta-box-sortables ui-sortable" id="column3-sortables" style="">
						</div>
					</div>

					<div style="display: none; width: 49%;" class="postbox-container">
						<div class="meta-box-sortables ui-sortable" id="column4-sortables" style="">
						</div>
					</div>
				</div>

			</div>
			<?php
		}

		function handle_log_page() {

			?>
			<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
			<h2><?php _e( 'PS-Multisite Index Cron Log', 'postindexer' ); ?></h2>

			<?php
			if ( $this->model->blogs_for_rebuilding() ) {
				// Show a rebuilding message and timer
				?>
				<div id='rebuildingmessage'>
					<?php _e( 'Es befinden sich derzeit Elemente in Deiner Indexierungswarteschlange.', 'postindexer' ); ?>
				</div>
				<?php
			}
			?>

			<?php
			if ( isset( $_GET['msg'] ) ) {
				echo '<div id="message" class="updated fade"><p>' . $messages[ (int) $_GET['msg'] ] . '</p></div>';
				$_SERVER['REQUEST_URI'] = esc_url_raw( remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] ) );
			}
			?>

			<form action='' method='post'>

				<br/>
				<p class='description'><?php
					echo sprintf( __( "Anzeigen der neuesten <strong>%s</strong> Cron-Protokolleinträge.", 'postindexer' ), PI_CRON_DEBUG_KEEP_LAST ); ?>
				</p>
				<table class="form-table">
					<tbody>
					<?php
					$logs = $this->model->get_log_messages( PI_CRON_DEBUG_KEEP_LAST );
					if ( ! empty( $logs ) ) {
						$class = 'alt';
						foreach ( $logs as $log ) {
							?>
							<tr class='logentry <?php echo $class; ?>'>
								<td valign=top><strong><?php echo $log->log_title; ?></strong><br/>
									<?php
									echo $log->log_details;
									?>
								</td>
								<td valign=top align=right><?php echo $log->log_datetime; ?></td>
							</tr>
							<?php
							if ( $class == '' ) {
								$class = 'alt';
							} else {
								$class = '';
							}
						}
					}
					?>
					</tbody>
				</table>
			</form>
			<?php

		}

		function handle_postindexer_page() {

			$messages    = array();
			$messages[1] = __( 'Der Wiederaufbau des Post-Index ist geplant.', 'postindexer' );
			$messages[2] = __( 'Einstellungen wurden aktualisiert.', 'postindexer' );

			?>
			<div class="wrap nosubsub">
				<h3 class="nav-tab-wrapper">
					<?php if ( has_action( 'postindexer_statistics' ) ) {
						?>
						<a href="admin.php?page=ps-multisite-index&amp;tab=statistics"
						   class="nav-tab <?php if ( ! isset( $_GET['tab'] ) || $_GET['tab'] == 'statistics' ) {
							   echo 'nav-tab-active';
						   } ?>"><?php _e( 'Statistiken', 'postindexer' ); ?></a>
						<?php
					}
					?>
					<a href="admin.php?page=ps-multisite-index&amp;tab=globaloptions"
					   class="nav-tab <?php if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'globaloptions' ) {
						   echo 'nav-tab-active';
					   } ?>"><?php _e( 'Globale Einstellungen', 'postindexer' ); ?></a>
					<a href="admin.php?page=ps-multisite-index&amp;tab=rebuildindex"
					   class="nav-tab <?php if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'rebuildindex' ) {
						   echo 'nav-tab-active';
					   } ?>"><?php _e( 'Index neu erstellen', 'postindexer' ); ?></a>
					<?php
					if ( defined( 'PI_CRON_DEBUG' ) && PI_CRON_DEBUG == true ) {
						?>
						<a href="admin.php?page=ps-multisite-index&amp;tab=log"
						   class="nav-tab <?php if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'log' ) {
							   echo 'nav-tab-active';
						   } ?>"><?php _e( 'Cron Log', 'postindexer' ); ?></a>
						<?php
					}
					?>
				</h3>

				<?php
				if ( isset( $_GET['tab'] ) ) {
					switch ( $_GET['tab'] ) {


						case 'rebuildindex': ?>
							<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
							<h2><?php _e( 'Neurstellen des Netzwerkbeitragsindex', 'postindexer' ); ?></h2>

							<?php
							if ( $this->model->blogs_for_rebuilding() ) {
								// Show a rebuilding message and timer
								?>
								<div id='rebuildingmessage'>
									<?php _e( 'Es befinden sich derzeit Elemente in Deiner Indexierungswarteschlange.', 'postindexer' ); ?>
								</div>
								<?php
							}
							?>

							<?php
							if ( isset( $_GET['msg'] ) ) {
								echo '<div id="message" class="updated fade"><p>' . $messages[ (int) $_GET['msg'] ] . '</p></div>';
								$_SERVER['REQUEST_URI'] = esc_url_raw( remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] ) );
							}
							?>
							<form action='' method='post'>

								<input type='hidden' name='action' value='postindexerrebuildallsites'/>
								<?php
								wp_nonce_field( 'postindexer_rebuild_all_sites' );
								?>

								<p class='description'><?php _e( 'Du kannst den Post-Index neu erstellen, indem Du unten auf die Schaltfläche <strong>Index neu erstellen</strong> klickst.', 'postindexer' ); ?></p>
								<p class='description'><?php _e( "Hinweis: Dies kann viel Zeit in Anspruch nehmen und die Leistung Deines Servers beeinträchtigen.", 'postindexer' ); ?></p>

								<p class="submit">
									<input type="submit" name="Submit" class="button-primary"
									       value="<?php esc_attr_e( 'Index neu erstellen', 'postindexer' ); ?>"/>
								</p>
							</form>
							<?php
							break;

						case 'log':
							if ( defined( 'PI_CRON_DEBUG' ) && PI_CRON_DEBUG == true ) {
																$this->handle_log_page();
							}
							break;

						case 'globaloptions':
							?>
							<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
							<h2><?php _e( 'PS-Multisite Index - Globale Einstellungen', 'postindexer' ); ?></h2>

							<?php
							if ( $this->model->blogs_for_rebuilding() ) {
								// Show a rebuilding message and timer
								?>
								<div id='rebuildingmessage'>
									<?php _e( 'Es befinden sich derzeit Elemente in Deiner Indexierungswarteschlange.', 'postindexer' ); ?>
								</div>
								<?php
							}
							?>

							<?php
							if ( isset( $_GET['msg'] ) ) {
								echo '<div id="message" class="updated fade"><p>' . $messages[ (int) $_GET['msg'] ] . '</p></div>';
								$_SERVER['REQUEST_URI'] = esc_url_raw( remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] ) );
							}
							?>

							<form action='' method='post'>

								<input type='hidden' name='action' value='postindexerupdateglobaloptions'/>
								<?php
								wp_nonce_field( 'postindexer_update_global_options' );
								?>

								<p class='description'><?php
									_e( 'Mit den folgenden Einstellungen kannst Du die Standardeinstellungen für alle Seiten in Deinem Netzwerk und die Verarbeitung festlegen, die für den gesamten Index stattfinden. ', 'postindexer' );
									echo sprintf( __( "Du kannst einige dieser Einstellungen Seite für Seite über die <a href=%s>Webseiten Admin-Seite</a> überschreiben.", 'postindexer' ), network_admin_url( 'sites.php' ) ); ?>
								</p>

								<table class="form-table">
									<tbody>
									<tr valign="top">
										<th scope="row"><label
												for="post_types"><?php _e( 'Standardposttypen', 'postindexer' ); ?></label>
										</th>
										<td>
											<textarea id="post_types" name="post_types" cols="80"
											          rows="5"><?php echo implode( "\n", $this->global_post_types ); ?></textarea>
											<br/>
											<?php _e( 'Dies sind die Standardposttypen, die vom Plugin indiziert werden. Platziere jeden Beitragstyp in einer separaten Zeile.', 'postindexer' ); ?>
										</td>
									</tr>

									<tr valign="top">
										<th scope="row"><label



												for="agedperiod"><?php _e( 'Indizierte Beiträge entfernen, die älter als sind', 'postindexer' ); ?></label>
										</th>
										<td>
											<?php
											$agedposts = get_site_option( 'postindexer_agedposts', array(
												'agedunit'   => 1,
												'agedperiod' => 'year'
											) );
											?>
											<select name='agedunit'>
												<?php
												for ( $n = 1; $n <= 365; $n ++ ) {
													?>
													<option
														value='<?php echo $n; ?>' <?php selected( $n, $agedposts['agedunit'] ); ?>><?php echo $n; ?></option>
													<?php
												}
												?>
											</select>&nbsp;
											<select name='agedperiod'>
												<option
													value='hour' <?php selected( 'hour', $agedposts['agedperiod'] ); ?>><?php _e( 'Stunde(n)', 'postindexer' ); ?></option>
												<option
													value='day' <?php selected( 'day', $agedposts['agedperiod'] ); ?>><?php _e( 'Tag(e)', 'postindexer' ); ?></option>
												<option
													value='week' <?php selected( 'week', $agedposts['agedperiod'] ); ?>><?php _e( 'Woche(n)', 'postindexer' ); ?></option>
												<option
													value='month' <?php selected( 'month', $agedposts['agedperiod'] ); ?>><?php _e( 'Monat(e)', 'postindexer' ); ?></option>
												<option
													value='year' <?php selected( 'year', $agedposts['agedperiod'] ); ?>><?php _e( 'Jahr(e)', 'postindexer' ); ?></option>
											</select>&nbsp;

											<br/>
											<?php _e( 'Beiträge, die älter als diese Zeitspanne sind, werden aus dem globalen Index entfernt.', 'postindexer' ); ?>
										</td>
									</tr>
									</tbody>
								</table>
							
								<?php
								do_action( 'postindexer_options_page' );
								?>

								<p class="submit">
									<input type="submit" name="Submit" class="button-primary"
									       value="<?php esc_attr_e( 'Einstellungen aktualisieren', 'postindexer' ); ?>"/>
								</p>

							</form>
							<?php
							break;

						case 'statistics':
						default:
							do_action( 'postindexer_statistics' );
							break;


					}
				} else {
					do_action( 'postindexer_statistics' );
				}

				?>

			</div>
			<?php
		}

		// This function attempts to index / update / remove a recently saved post
		function index_post( $post_id, $post ) {

			if ( $this->model->is_blog_indexable( $this->db->blogid ) ) {

				// For this we will grab the post regardless if it is one we want to index so we can remove non-indexable ones
				$post = $this->model->get_post_for_indexing( $post_id, false, false );
				if ( ! empty( $post ) ) {
					// Check if we are a revision, and if so then grab the proper post
					if ( $post['post_type'] == 'revision' && ( (int) $post['post_parent'] > 0 ) ) {
						// Grab the parent_id and then grab that post
						$post_id = (int) $post['post_parent'];
						$post    = $this->model->get_post_for_indexing( $post_id, false, false );
					}

					// Check if the post should be indexed or not
					if ( $this->model->is_post_indexable( $post ) ) {

						// Get the local Beitrag ID
						$local_id = $post['ID'];
						// Add in the blog id to the post record
						$post['BLOG_ID'] = $this->db->blogid;

						// Add the post record to the network tables
						$this->model->index_post( $post );

						// Get the post meta for this local post
						$meta = $this->model->get_postmeta_for_indexing( $local_id );
						// Remove any existing ones that we are going to overwrite
						$this->model->remove_postmeta_for_post( $local_id );
						if ( ! empty( $meta ) ) {
							foreach ( $meta as $metakey => $postmeta ) {
								// Add in the blog_id to the table
							
								$postmeta['blog_id'] = $this->db->blogid;
								// Add it to the network tables
								$this->model->index_postmeta( $postmeta );
							}
						}

						// Get the taxonomy for this local post
						$taxonomy = $this->model->get_taxonomy_for_indexing( $local_id );
						// Remove any existing ones that we are going to overwrite
						$this->model->remove_term_relationships_for_post( $local_id, $this->db->blogid );
						if ( ! empty( $taxonomy ) ) {
							foreach ( $taxonomy as $taxkey => $tax ) {
								$tax['blog_id']   = $this->db->blogid;
								$tax['object_id'] = $local_id;
								$this->model->index_tax( $tax );
							}
						}

					} else {
						// The post isn't indexable so we should try to remove it in case it already exists and we're just updating it
						$this->delete_post( $post_id );
					}

				}

			} else {
				// Remove any existing posts in case we've already indexed them
				$this->model->remove_indexed_entries_for_blog( $item->blog_id );
			}

		}

		// This function attempts to delete a recently deleted post
		function delete_post( $post_id ) {
			$this->model->remove_indexed_entry_for_blog( $post_id, $this->db->blogid );
		}

		// This function removes the blog from the index
		function remove_from_index( $blog_id ) {

			// Remove the blog from the queue in case it is still in there
			$this->model->remove_blog_from_queue( $blog_id );
			// Remove any existing entries in the network index
			$this->model->remove_indexed_entries_for_blog( $blog_id );

		}

		function check_privacy() {

			$settings_updated = isset( $_GET['settings-updated'] ) ? $_GET['settings-updated'] : false;

			if ( $settings_updated === true ) {
				$blog_public = get_blog_status( $this->db->blogid, 'public' );
				if ( $blog_public != '1' ) {
					$this->remove_from_index( $this->db->blogid );
				}
			}

		}

		function post_indexer_global_install() {
			global $wpdb, $post_indexer_current_version;
			if ( get_site_option( "post_indexer_installed" ) == '' ) {
				add_site_option( 'post_indexer_installed', 'no' );
			}

			if ( get_site_option( "post_indexer_installed" ) == "yes" ) {
				// do nothing
				$sql  = "SHOW COLUMNS FROM " . $wpdb->base_prefix . "site_posts;";
				$cols = $wpdb->get_col( $sql );
				if ( ! in_array( 'post_type', $cols ) ) {
					$sql  = "ALTER TABLE " . $wpdb->base_prefix . "site_posts ADD post_type varchar(20) NULL DEFAULT 'post'  AFTER post_modified_stamp";
					$sql2 = "ALTER TABLE " . $wpdb->base_prefix . "site_posts ADD INDEX  (post_type);";

					$wpdb->query( $sql );
					$wpdb->query( $sql2 );
				}
			} else {

				$post_indexer_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "site_posts` (
				  `site_post_id` bigint(20) unsigned NOT NULL auto_increment,
				  `blog_id` bigint(20) default NULL,
				  `site_id` bigint(20) default NULL,
				  `sort_terms` text,
				  `blog_public` int(2) default NULL,
				  `post_id` bigint(20) default NULL,
				  `post_author` bigint(20) default NULL,
				  `post_title` text,
				  `post_content` text,
				  `post_content_stripped` text,
				  `post_terms` text,
				  `post_permalink` text,
				  `post_published_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
				  `post_published_stamp` varchar(255) default NULL,
				  `post_modified_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
				  `post_modified_stamp` varchar(255) default NULL,
				  `post_type` varchar(20) default 'post',
				  PRIMARY KEY  (`site_post_id`),
				  KEY `post_type` (`post_type`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

				$post_indexer_table2 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "term_counts` (
		  `term_count_id` bigint(20) unsigned NOT NULL auto_increment,
		  `term_id` bigint(20),
		  `term_count_type` TEXT,
		  `term_count_updated` datetime NOT NULL default '0000-00-00 00:00:00',
		  `term_count` bigint(20),
		  PRIMARY KEY  (`term_count_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

				$post_indexer_table3 = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}site_terms` (
			                `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			                `name` varchar(200) NOT NULL DEFAULT '',
			                `slug` varchar(200) NOT NULL DEFAULT '',
			                `type` varchar(20) NOT NULL DEFAULT 'product_category',
			                `count` bigint(10) NOT NULL DEFAULT '0',
			                PRIMARY KEY (`term_id`),
			                UNIQUE KEY `slug` (`slug`),
			                KEY `name` (`name`)
			              ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

				$post_indexer_table4 = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}site_term_relationships` (
				                `site_post_id` bigint(20) unsigned NOT NULL,
				                `term_id` bigint(20) unsigned NOT NULL,
				                KEY (`site_post_id`),
				                KEY (`term_id`)
				              ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
				$post_indexer_table5 = "";

				$wpdb->query( $post_indexer_table1 );
				$wpdb->query( $post_indexer_table2 );
				$wpdb->query( $post_indexer_table3 );
				$wpdb->query( $post_indexer_table4 );
				//$wpdb->query( $post_indexer_table5 );
				update_site_option( "post_indexer_installed", "yes" );
			}
		}

		public function output_modals() {
			?>
			<dialog id="siteedit-modal" class="psource-modal">
				<button class="psource-modal-close" type="button" aria-label="Schließen">×</button>
				<iframe style="width:100%;height:80vh;border:0;"></iframe>
			</dialog>
			<dialog id="sitesummary-modal" class="psource-modal">
				<button class="psource-modal-close" type="button" aria-label="Schließen">×</button>
				<iframe style="width:100%;height:80vh;border:0;"></iframe>
			</dialog>
			<?php
		}

		function render_monitoring_page() {
            if (isset($this->monitoring_admin)) {
            $this->monitoring_admin->render_monitoring_page();
        } else {
            echo '<div class="wrap"><h1>Monitoring</h1><p>Monitoring-Tools konnten nicht geladen werden.</p></div>';
        }
        }
	}
}

$postindexeradmin = new postindexeradmin();