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
		}
	}

	public function plug_pages() {
		add_submenu_page( 'settings.php', __( 'Content Monitor', 'contentmon' ), __( 'Content Monitor', 'contentmon' ), 'manage_network_options', 'content-monitor', array( &$this, 'page_main_output' ) );
	}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

	public function page_main_output() {

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( 'Nice Try...' );  //If accessed properly, this message doesn't appear.
		}

		echo '<div class="wrap">';

		if ( isset( $_POST['content_monitor_email'] ) ) {
			update_site_option( "content_monitor_email", stripslashes( $_POST['content_monitor_email'] ) );
			update_site_option( "content_monitor_post_monitoring", (int) $_POST['content_monitor_post_monitoring'] );
			update_site_option( "content_monitor_bad_words", stripslashes( $_POST['content_monitor_bad_words'] ) );
			?>
			<div id="message" class="updated fade"><p><?php _e( 'Settings saved.', 'contentmon' ) ?></p></div><?php
		}

		?>
		<h2><?php _e( 'Content Monitor', 'contentmon' ) ?></h2>
		<form method="post" action="">
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Email Address', 'contentmon' ) ?></th>
					<td>
						<?php $email = get_site_option( 'content_monitor_email' );
						$email       = is_email( $email ) ? $email : get_site_option( "admin_email" );
						?>
						<input name="content_monitor_email" type="text" id="content_monitor_email" style="width: 95%"
						       value="<?php echo esc_attr( $email ); ?>" size="45"/>
						<br/><?php _e( 'Content notifications will be sent to this address.', 'contentmon' ) ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Post/Page Monitoring', 'contentmon' ) ?></th>
					<td>
						<select name="content_monitor_post_monitoring" id="content_monitor_post_monitoring">
							<?php
							$enabled = (bool) get_site_option( 'content_monitor_post_monitoring' );
							?>
							<option
								value="1"<?php selected( $enabled, true ); ?>><?php _e( 'Enabled', 'contentmon' ) ?></option>
							<option
								value="0"<?php selected( $enabled, false ); ?>><?php _e( 'Disabled', 'contentmon' ) ?></option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Bad Words', 'contentmon' ) ?></th>
					<td>
					<textarea name="content_monitor_bad_words" type="text" rows="5" wrap="soft"
					          id="content_monitor_bad_words"
					          style="width: 95%"/><?php echo esc_textarea( get_site_option( 'content_monitor_bad_words' ) ); ?></textarea>
						<br/><?php _e( 'Place a comma between each word (ex bad, word).', 'contentmon' ) ?></td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="Submit" class="button-primary"
				       value="<?php _e( 'Save Changes', 'contentmon' ) ?>"/>
			</p>
		</form>
		<?php

		echo '</div>';
	}
}
new Content_Monitor();