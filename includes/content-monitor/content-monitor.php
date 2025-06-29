<?php

//force multisite
if ( ! is_multisite() ) {
	die( __( 'Content Monitor is only compatible with Multisite installs.', 'contentmon' ) );
}

class Content_Monitor {

	public function __construct() {
		add_action( 'plugins_loaded', array( &$this, 'localization' ) );
		add_action( 'network_admin_menu', array( &$this, 'plug_pages' ) );

		if ( get_site_option( 'content_monitor_post_monitoring' ) ) {
			add_action( 'save_post', array( &$this, 'post_monitor' ), 10, 2 );
		}
		if ( get_site_option( 'content_monitor_comment_monitoring' ) ) {
			add_filter( 'preprocess_comment', array( &$this, 'comment_monitor' ), 10, 1 );
		}
	}

	public function localization() {
	}

	public function send_email( $post_permalink, $post_type ) {
		global $current_site;

		$subject_content = __( "SITE_NAME: Content Notification", 'contentmon' );

		$message_content = __( "Dear EMAIL,

The following TYPE on SITE_NAME has been flagged as possibly containing a non-allowed word:
PERMALINK", 'contentmon' );

		$send_to_email = get_site_option( 'content_monitor_email' );
		if ( $send_to_email == '' ) {
			$send_to_email = get_site_option( "admin_email" );
		}

		$message_content = str_replace( "SITE_NAME", $current_site->site_name, $message_content );
		$message_content = str_replace( "SITE_URL", 'http://' . $current_site->domain . '', $message_content );
		$message_content = str_replace( "PERMALINK", $post_permalink, $message_content );
		$message_content = str_replace( "TYPE", $post_type, $message_content );
		$message_content = str_replace( "EMAIL", $send_to_email, $message_content );

		$subject_content = str_replace( "SITE_NAME", $current_site->site_name, $subject_content );

		wp_mail( $send_to_email, $subject_content, $message_content );
	}

	/**
	 * Ersetzt Badwords im Content durch ein Custom-Word
	 * @param string $content
	 * @return string
	 */
	public function replace_badwords( $content ) {
		static $all_bad_words = null;
		if ($all_bad_words === null) {
			$bad_words = get_site_option( 'content_monitor_bad_words' );
			$bad_words_array = array_map( 'trim', explode( ',', $bad_words ) );
			// Integrierte Liste ergänzen, falls Option aktiv
			if ( get_site_option('content_monitor_use_default_list') ) {
				$default_file = dirname(__FILE__) . '/badwords-default.txt';
				if (file_exists($default_file)) {
					$default_words = file($default_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					foreach ($default_words as $w) {
						$w = trim($w);
						if ($w && $w[0] !== '#') $bad_words_array[] = $w;
					}
				}
			}
			$all_bad_words = array_unique(array_filter($bad_words_array));
		}
		$replace_word = get_site_option( 'content_monitor_replace_word', 'PIEPS' );
		if ( empty( $all_bad_words ) || empty( $replace_word ) ) return $content;
		foreach ( $all_bad_words as $bad_word ) {
			if ( $bad_word === '' ) continue;
			// Ersetze unabhängig von Groß-/Kleinschreibung, nur ganzes Wort
			$content = preg_replace( '/\b' . preg_quote( $bad_word, '/' ) . '\b/ui', $replace_word, $content );
		}
		return $content;
	}

	public function post_monitor( $post_id, $post ) {

		//Don't record this if it's not a post
		if ( ! ( 'post' == $post->post_type || 'page' == $post->post_type ) ) {
			return false;
		}

		if ( 'publish' != $post->post_status || ! empty( $post->post_password ) ) {
			return false;
		}

		//get bad words array
		$bad_words       = get_site_option( 'content_monitor_bad_words' );
		$bad_words_array = explode( ",", $bad_words );
		$bad_words_array = array_map( 'trim', $bad_words_array );

		//get post content words array
		$post_content = $post->post_title . ' ' . $post->post_content;
		$post_content = wp_filter_nohtml_kses( $post_content );

		$bad_word_count = 0;
		foreach ( $bad_words_array as $bad_word ) {
			if ( false !== mb_stripos( $post_content, $bad_word, 0, 'UTF-8' ) ) {
				$bad_word_count ++;
			}

			if ( $bad_word_count > 0 ) {
				break;
			}
		}

		if ( $bad_word_count > 0 ) {
			$post_permalink = get_permalink( $post_id );
			$this->send_email( $post_permalink, $post->post_type );
			// Fund speichern
			$badword_log = get_site_option( 'content_monitor_log', array() );
			$badword_log[] = array(
				'post_id' => $post_id,
				'permalink' => $post_permalink,
				'user_id' => $post->post_author,
				'user_login' => get_the_author_meta('user_login', $post->post_author),
				'user_email' => get_the_author_meta('user_email', $post->post_author),
				'found_at' => current_time('mysql'),
				'post_title' => $post->post_title,
			);
			update_site_option( 'content_monitor_log', $badword_log );
		}

		// Badwords ersetzen
		$replace_word = get_site_option( 'content_monitor_replace_word', 'PIEPS' );
		if ( $replace_word ) {
			$original_content = $post->post_content;
			$filtered_content = $this->replace_badwords( $original_content );
			if ( $filtered_content !== $original_content ) {
				remove_action( 'save_post', array( &$this, 'post_monitor' ), 10 );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $filtered_content ) );
				add_action( 'save_post', array( &$this, 'post_monitor' ), 10, 2 );
			}
		}
	}

	public function comment_monitor( $commentdata ) {
		$bad_words = get_site_option( 'content_monitor_bad_words' );
		$bad_words_array = array_map( 'trim', explode( ',', $bad_words ) );
		$replace_word = get_site_option( 'content_monitor_replace_word', 'PIEPS' );
		$bad_word_found = false;
		$original_content = $commentdata['comment_content'];
		$filtered_content = $this->replace_badwords( $original_content );
		foreach ( $bad_words_array as $bad_word ) {
			if ( $bad_word !== '' && false !== mb_stripos( $original_content, $bad_word, 0, 'UTF-8' ) ) {
				$bad_word_found = true;
				break;
			}
		}
		if ( $bad_word_found ) {
			// Debug: Log-Ausgabe
			if ( function_exists('error_log') ) {
				error_log('[ContentMonitor] Badword gefunden im Kommentar von UserID ' . (isset($commentdata['user_ID']) ? $commentdata['user_ID'] : '0') . ' | Autor: ' . (isset($commentdata['comment_author']) ? $commentdata['comment_author'] : '') . ' | Inhalt: ' . $original_content);
			}
			// Logging
			$badword_log = get_site_option( 'content_monitor_log', array() );
			$user_id = isset($commentdata['user_ID']) ? $commentdata['user_ID'] : 0;
			if ($user_id) {
				$user_login = get_the_author_meta('user_login', $user_id);
				$user_email = get_the_author_meta('user_email', $user_id);
			} else {
				$user_login = $commentdata['comment_author'];
				$user_email = $commentdata['comment_author_email'];
			}
			$permalink = '';
			if (!empty($commentdata['comment_post_ID'])) {
				$permalink = get_permalink($commentdata['comment_post_ID']);
			}
			$badword_log[] = array(
				'post_id' => isset($commentdata['comment_post_ID']) ? $commentdata['comment_post_ID'] : 0,
				'permalink' => $permalink,
				'user_id' => $user_id,
				'user_login' => $user_login,
				'user_email' => $user_email,
				'found_at' => current_time('mysql'),
				'post_title' => '[Kommentar] ' . mb_substr($original_content, 0, 40) . (mb_strlen($original_content) > 40 ? '...' : ''),
			);
			update_site_option( 'content_monitor_log', $badword_log );
		}
		if ( $filtered_content !== $original_content ) {
			$commentdata['comment_content'] = $filtered_content;
		}
		return $commentdata;
	}

	public function plug_pages() {
		// Menü nicht mehr im Admin anzeigen
		//add_submenu_page( 'settings.php', __( 'Content Monitor', 'contentmon' ), __( 'Content Monitor', 'contentmon' ), 'manage_network_options', 'content-monitor', array( &$this, 'page_main_output' ) );
	}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

	public function page_main_output() {

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( 'Nice Try...' );  //If accessed properly, this message doesn't appear.
		}

		// Sicherstellen, dass $badword_log immer initialisiert ist
		$badword_log = get_site_option( 'content_monitor_log', array() );
		$badword_log = is_array($badword_log) ? $badword_log : array();

		// --- Paginierung für Log-Tabelle ---
		$log_per_page = 25;
		$log_total = count($badword_log);
		$log_page = isset($_GET['log_page']) && is_numeric($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
		$log_max_page = max(1, ceil($log_total / $log_per_page));
		$log_start = ($log_page - 1) * $log_per_page;
		$log_slice = array_slice(array_reverse($badword_log), $log_start, $log_per_page);

		echo '<div class="wrap">';

		if ( isset( $_POST['content_monitor_email'] ) ) {
			update_site_option( "content_monitor_email", stripslashes( $_POST['content_monitor_email'] ) );
			update_site_option( "content_monitor_post_monitoring", (int) $_POST['content_monitor_post_monitoring'] );
			update_site_option( "content_monitor_bad_words", stripslashes( $_POST['content_monitor_bad_words'] ) );
			update_site_option( 'content_monitor_replace_word', stripslashes( $_POST['content_monitor_replace_word'] ) );
			if ( isset( $_POST['content_monitor_comment_monitoring'] ) ) {
				update_site_option( 'content_monitor_comment_monitoring', (int) $_POST['content_monitor_comment_monitoring'] );
			}
			// Neue Option speichern
			update_site_option( 'content_monitor_use_default_list', isset($_POST['content_monitor_use_default_list']) ? 1 : 0 );
			// Hooks nach Änderung der Optionen neu setzen
			if ( get_site_option( 'content_monitor_post_monitoring' ) ) {
				add_action( 'save_post', array( &$this, 'post_monitor' ), 10, 2 );
			} else {
				remove_action( 'save_post', array( &$this, 'post_monitor' ), 10 );
			}
			if ( get_site_option( 'content_monitor_comment_monitoring' ) ) {
				add_filter( 'preprocess_comment', array( &$this, 'comment_monitor' ), 10, 1 );
			} else {
				remove_filter( 'preprocess_comment', array( &$this, 'comment_monitor' ), 10 );
			}
			?>
			<div id="message" class="updated fade"><p><?php _e( 'Einstellungen gespeichert.', 'postindexer' ) ?></p></div><?php
		}

		?>
		<h2><?php _e( 'Content Monitor', 'postindexer' ) ?></h2>
		<form method="post" action="">
			<style>
				.cm-status-enabled { background: #27ae60 !important; color: #fff !important; font-weight: bold; }
				.cm-status-disabled { background: #e74c3c !important; color: #fff !important; font-weight: bold; }
			</style>
			<script>
			jQuery(document).ready(function($){
				function updateCMStatus(sel) {
					var val = $(sel).val();
					if(val == '1') {
						$(sel).removeClass('cm-status-disabled').addClass('cm-status-enabled');
					} else {
						$(sel).removeClass('cm-status-enabled').addClass('cm-status-disabled');
					}
				}
				$('#content_monitor_post_monitoring, #content_monitor_comment_monitoring').each(function(){
					updateCMStatus(this);
				}).on('change', function(){
					updateCMStatus(this);
				});
			});
			</script>
			<table class="form-table">
				<tr>
					<td colspan="2">
						<div style="display: flex; flex-direction: column; gap: 24px;">
							<div style="display: flex; gap: 32px; align-items: flex-start; flex-wrap: wrap;">
								<fieldset style="min-width:220px; border:1px solid #ccc; padding:16px; border-radius:6px; flex:1;">
									<legend style="font-weight:bold;">Post/Page Monitoring</legend>
									<label for="content_monitor_post_monitoring" style="display:block; margin-bottom:8px;">
										<?php _e( 'Überwache Beiträge und Seiten auf Badwords.', 'postindexer' ) ?>
									</label>
									<select name="content_monitor_post_monitoring" id="content_monitor_post_monitoring" style="width:100%;">
										<?php $enabled = (bool) get_site_option( 'content_monitor_post_monitoring' ); ?>
										<option value="1"<?php selected( $enabled, true ); ?>><?php _e( 'Aktiviert', 'postindexer' ) ?></option>
										<option value="0"<?php selected( $enabled, false ); ?>><?php _e( 'Deaktiviert', 'postindexer' ) ?></option>
									</select>
								</fieldset>
								<fieldset style="min-width:220px; border:1px solid #ccc; padding:16px; border-radius:6px; flex:1;">
									<legend style="font-weight:bold;">Comment Monitoring</legend>
									<label for="content_monitor_comment_monitoring" style="display:block; margin-bottom:8px;">
										<?php _e( 'Kommentare werden auf Badwords geprüft und ggf. ersetzt.', 'postindexer' ) ?>
									</label>
									<select name="content_monitor_comment_monitoring" id="content_monitor_comment_monitoring" style="width:100%;">
										<?php $enabled = (bool) get_site_option( 'content_monitor_comment_monitoring' ); ?>
										<option value="1"<?php selected( $enabled, true ); ?>><?php _e( 'Aktiviert', 'postindexer' ) ?></option>
										<option value="0"<?php selected( $enabled, false ); ?>><?php _e( 'Deaktiviert', 'postindexer' ) ?></option>
									</select>
								</fieldset>
							</div>
							<div style="display: flex; gap: 32px; align-items: flex-start; flex-wrap: wrap; margin-top: 0;">
								<fieldset style="min-width:220px; border:1px solid #ccc; padding:16px; border-radius:6px; flex:1;">
									<legend style="font-weight:bold;">Badword Replacement</legend>
									<label for="content_monitor_replace_word" style="display:block; margin-bottom:8px;">
										<?php _e( 'Alle Badwords werden durch dieses Wort ersetzt.', 'postindexer' ) ?>
									</label>
									<input name="content_monitor_replace_word" type="text" id="content_monitor_replace_word" style="width:100%;" value="<?php echo esc_attr( get_site_option( 'content_monitor_replace_word', 'PIEPS' ) ); ?>" size="20"/>
								</fieldset>
								<fieldset style="min-width:220px; border:1px solid #ccc; padding:16px; border-radius:6px; flex:1;">
									<legend style="font-weight:bold;">Integrierte Badword-Liste</legend>
									<label for="content_monitor_use_default_list" style="display:block; margin-bottom:8px;">
										<?php _e( 'Sehr strenge, integrierte Liste verwenden', 'postindexer' ) ?>
									</label>
									<input type="checkbox" name="content_monitor_use_default_list" id="content_monitor_use_default_list" value="1" <?php checked( get_site_option('content_monitor_use_default_list'), 1 ); ?> />
									<span style="font-size:11px; color:#666; display:block; margin-top:8px;">(<?php _e('Ergänzt die eigene Liste, enthält viele beleidigende Begriffe.', 'postindexer'); ?>)</span>
								</fieldset>
							</div>
						</div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'E-Mail-Adresse', 'postindexer' ) ?></th>
					<td>
						<?php $email = get_site_option( 'content_monitor_email' );
						$email       = is_email( $email ) ? $email : get_site_option( "admin_email" );
						?>
						<input name="content_monitor_email" type="text" id="content_monitor_email" style="width: 95%"
						       value="<?php echo esc_attr( $email ); ?>" size="45"/>
						<br/><?php _e( 'Benachrichtigungen werden an diese Adresse gesendet.', 'postindexer' ) ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Badwords', 'postindexer' ) ?></th>
					<td>
					<textarea name="content_monitor_bad_words" type="text" rows="5" wrap="soft"
					          id="content_monitor_bad_words"
					          style="width: 95%"/><?php echo esc_textarea( get_site_option( 'content_monitor_bad_words' ) ); ?></textarea>
						<br/><?php _e( 'Trenne jedes Wort mit einem Komma (z.B. böse, wort).', 'postindexer' ) ?></td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="Submit" class="button-primary"
				       value="<?php _e( 'Änderungen speichern', 'postindexer' ) ?>"/>
			</p>
		</form>

		<?php
		// Meldung löschen
		if ( isset($_GET['content_monitor_delete']) && is_numeric($_GET['content_monitor_delete']) ) {
			$badword_log = get_site_option( 'content_monitor_log', array() );
			$idx = (int)$_GET['content_monitor_delete'];
			if (isset($badword_log[$idx])) {
				unset($badword_log[$idx]);
				$badword_log = array_values($badword_log);
				update_site_option( 'content_monitor_log', $badword_log );
				?><div id="message" class="updated fade"><p><?php _e( 'Meldung gelöscht.', 'postindexer' ) ?></p></div><?php
				// Nach dem Löschen neu laden!
				$badword_log = get_site_option( 'content_monitor_log', array() );
			}
		}
		// AJAX-Tabumschaltung
		?>
		<script>
		jQuery(document).ready(function($){
			function showTab(tab) {
				$('.content-monitor-tab-content').hide();
				$('#content-monitor-tab-'+tab).show();
				$('.nav-tab').removeClass('nav-tab-active');
				$(".nav-tab[href='#content-monitor-tab-"+tab+"']").addClass('nav-tab-active');
			}
			$('.nav-tab').on('click', function(e){
				e.preventDefault();
				var tab = $(this).attr('href').split('content-monitor-tab-')[1];
				showTab(tab);
			});
			// Standard: aktiven Tab anzeigen (Fallback: meldungen)
			var activeTab = '<?php echo esc_js($active_tab); ?>';
			if($('#content-monitor-tab-'+activeTab).length) {
				showTab(activeTab);
			} else {
				showTab('meldungen');
			}
		});
		</script>
		<?php
		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="#content-monitor-tab-meldungen" class="nav-tab' . ($active_tab=='meldungen'?' nav-tab-active':'') . '">' . __('Neue Meldungen', 'postindexer') . ' <span class="count">' . $counter . '</span></a>';
		echo '<a href="#content-monitor-tab-watchlist" class="nav-tab' . ($active_tab=='watchlist'?' nav-tab-active':'') . '">' . __('Watchlist', 'postindexer') . '</a>';
		echo '</h2>';
		echo '<div id="content-monitor-tab-meldungen" class="content-monitor-tab-content">';
		if (!empty($badword_log)) {
			echo '<h3>' . esc_html__('Gefundene Badwords', 'postindexer') . '</h3>';
			echo '<table class="widefat"><thead><tr>';
			echo '<th>' . esc_html__('Datum', 'postindexer') . '</th>';
			echo '<th>' . esc_html__('Beitrag', 'postindexer') . '</th>';
			echo '<th>' . esc_html__('User', 'postindexer') . '</th>';
			echo '<th>' . esc_html__('E-Mail', 'postindexer') . '</th>';
			echo '<th></th>';
			echo '</tr></thead><tbody>';
			foreach ($log_slice as $idx => $entry) {
				// Der Index muss auf den Original-Index gemappt werden, da array_slice auf das reversed Array angewendet wurde
				$orig_idx = $log_total - 1 - ($log_start + $idx);
				echo '<tr>';
				echo '<td>' . esc_html($entry['found_at']) . '</td>';
				echo '<td><a href="' . esc_url($entry['permalink']) . '" target="_blank">' . esc_html($entry['post_title']) . '</a></td>';
				echo '<td>' . esc_html($entry['user_login']) . ' (ID: ' . intval($entry['user_id']) . ')</td>';
				echo '<td>' . esc_html($entry['user_email']) . '</td>';
				echo '<td><a href="?page=content-monitor&content_monitor_tab=meldungen&content_monitor_delete=' . $orig_idx . '" onclick="return confirm(\'Meldung wirklich löschen?\');" style="color:red;font-weight:bold;">X</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			// --- Navigation ---
			if ($log_max_page > 1) {
				echo '<div style="margin:12px 0;">' . __('Seite:', 'postindexer') . ' ';
				for ($i = 1; $i <= $log_max_page; $i++) {
					if ($i == $log_page) {
						echo '<span style="font-weight:bold;">['.$i.']</span> ';
					} else {
						echo '<a href="?page=content-monitor&content_monitor_tab=meldungen&log_page='.$i.'">'.$i.'</a> ';
					}
				}
				echo '</div>';
			}
		} else {
			echo '<p>' . __('Keine neuen Meldungen.', 'postindexer') . '</p>';
		}
		echo '</div>';
		echo '<div id="content-monitor-tab-watchlist" class="content-monitor-tab-content">';
		// Watchlist: User-Counter und Wortstatistik
		$user_stats = array();
		$word_stats = array();
		foreach ($badword_log as $entry) {
			$uid = $entry['user_id'];
			if (!isset($user_stats[$uid])) {
				$user_stats[$uid] = array('user_login'=>$entry['user_login'],'user_email'=>$entry['user_email'],'count'=>0,'words'=>array());
			}
			$user_stats[$uid]['count']++;
			// Wort extrahieren (aus dem Titel oder Kommentar-Auszug)
			if (strpos($entry['post_title'], '[Kommentar]') === 0) {
				// Kommentar: Wort aus Auszug nicht eindeutig, daher nicht pro Wort zählbar
			} else {
				foreach (explode(' ', $entry['post_title']) as $w) {
					$w = trim($w, ",.?!:;()[]{}<>\"'\n\r\t");
					if ($w && !in_array($w, array('','[Kommentar]'))) {
						if (!isset($user_stats[$uid]['words'][$w])) $user_stats[$uid]['words'][$w]=0;
						$user_stats[$uid]['words'][$w]++;
					}
				}
			}
		}
		// --- Pagination für Watchlist ---
		$watchlist_per_page = 25;
		$watchlist_total = count($user_stats);
		$watchlist_page = isset($_GET['content_monitor_watchlist_page']) ? max(1, intval($_GET['content_monitor_watchlist_page'])) : 1;
		$watchlist_pages = max(1, ceil($watchlist_total / $watchlist_per_page));
		$user_stats_keys = array_keys($user_stats);
		$watchlist_start = ($watchlist_page-1)*$watchlist_per_page;
		$watchlist_slice = array_slice($user_stats_keys, $watchlist_start, $watchlist_per_page);
		echo '<h3>' . esc_html__('User-Watchlist', 'postindexer') . '</h3>';
		echo '<table class="widefat"><thead><tr>';
		echo '<th>' . esc_html__('User', 'postindexer') . '</th>';
		echo '<th>' . esc_html__('E-Mail', 'postindexer') . '</th>';
		echo '<th>' . esc_html__('Badword-Counter', 'postindexer') . '</th>';
		echo '<th>' . esc_html__('Wörter (Anzahl)', 'postindexer') . '</th>';
		echo '</tr></thead><tbody>';
		foreach ($watchlist_slice as $uid) {
			$u = $user_stats[$uid];
			echo '<tr>';
			echo '<td>' . esc_html($u['user_login']) . '</td>';
			echo '<td>' . esc_html($u['user_email']) . '</td>';
			echo '<td>' . intval($u['count']) . '</td>';
			$words = array();
			foreach ($u['words'] as $word=>$anz) {
				$words[] = esc_html($word) . ' (' . intval($anz) . ')';
			}
			echo '<td>' . implode(', ', $words) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		// --- Pagination Navigation ---
		if ($watchlist_pages > 1) {
			echo '<div style="margin:12px 0;">';
			for ($i=1; $i<=$watchlist_pages; $i++) {
				if ($i == $watchlist_page) {
					echo '<span style="font-weight:bold; margin-right:8px;">['.$i.']</span>';
				} else {
					$url = add_query_arg(array('content_monitor_tab'=>'watchlist','content_monitor_watchlist_page'=>$i));
					echo '<a href="'.$url.'" class="button" style="margin-right:8px;">'.$i.'</a>';
				}
			}
			echo '</div>';
		}
		echo '</div>';

		echo '</div>';
	}
}
new Content_Monitor();