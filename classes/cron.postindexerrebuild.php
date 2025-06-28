<?php

if ( ! class_exists( 'postindexercron' ) ) {

	include_once( dirname( __FILE__ ) . '/class.processlocker.php' );

	class postindexercron {

		var $build = 1;

		var $rebuildperiod = '5mins';

		// The post indexer model
		var $model;

		var $lockers;
		var $lock_folder;

		function __construct() {

			$this->model = new postindexermodel();

			$this->lockers = array();

			add_action( 'init', array( &$this, 'set_up_schedule' ) );
			//add_action( 'autoblog_process_feeds', array(&$this, 'always_process_autoblog') );
			add_filter( 'cron_schedules', array( &$this, 'add_time_period' ) );

			// The cron action s
			add_action( 'postindexer_firstpass_cron', array( &$this, 'process_rebuild_firstpass' ) );
			add_action( 'postindexer_secondpass_cron', array( &$this, 'process_rebuild_secondpass' ) );

			add_action( 'postindexer_tagtidy_cron', array( &$this, 'process_tidy_tags' ) );
			add_action( 'postindexer_postmetatidy_cron', array( &$this, 'process_tidy_postmeta' ) );
			add_action( 'postindexer_agedpoststidy_cron', array( &$this, 'process_tidy_agedposts' ) );

		}

		function postindexercron() {
			$this->__construct();
		}

		function process_rebuild_firstpass( $DEBUG = false ) {
			//if ($DEBUG != true) return;
			$locker_key  = 'postindexer_firstpass_cron';
			$locker_info = array();

			$this->debug_message( __( 'Beitragsindex Erster Durchlauf', 'postindexer' ), __( "Initialisieren...", "postindexer" ) );

			$this->lockers[ $locker_key ] = new ProcessLocker( $locker_key );
			//if ($_locker->is_locked() == false) {
			//	unset($this->lockers[$locker_key]);
			//	$this->debug_message( __('Beitragsindex Erster Durchlauf','postindexer'), __("locked by previous process", "postindexer") );
			//	return;
			//}
			$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

			// First pass - loop through queue entries with a 0 in the rebuild_progress and set them up for the rebuild process
			$queue = $this->model->get_justqueued_blogs( PI_CRON_SITE_PROCESS_FIRSTPASS );

			$this->debug_message( __( 'Beitragsindex Erster Durchlauf', 'postindexer' ), sprintf( __( "Verarbeite %s in der Warteschlange befindliche Elemente.", "postindexer" ), count( $queue ) ) );

			if ( ! empty( $queue ) ) {


				foreach ( $queue as $item ) {

					if ( $this->model->is_blog_indexable( $item->blog_id ) ) {

						$this->debug_message( __( 'Beitragsindex Erster Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, ist indizierbar.", "postindexer" ), $item->blog_id ) );

						$locker_info['blog_id'] = $item->blog_id;
						$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

						// Get the highest post_id
						$max_id = $this->model->get_highest_post_for_blog( $item->blog_id );
						if ( ! empty( $max_id ) && $max_id > 0 ) {
							// We have posts - record the highest current Beitrag ID
							$this->debug_message( __( 'Beitragsindex Erster Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Höchste Beitrag-ID ist %d", "postindexer" ), $item->blog_id, $max_id ) );

							$this->model->update_blog_queue( $item->blog_id, $max_id );
						} else {
							// No posts, so we'll remove it from the queue
							$this->debug_message( __( 'Beitragsindex Erster Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Keine Beiträge gefunden, die aus der Warteschlange entfernt wurden.", "postindexer" ), $item->blog_id ) );

							$this->model->remove_blog_from_queue( $item->blog_id );
						}
						// Remove existing posts because we are going to rebuild
						$this->model->remove_indexed_entries_for_blog( $item->blog_id );

					} else {
						// Remove the blog from the queue
						$this->debug_message( __( 'Beitragsindex Erster Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, ist NICHT indizierbar - wird aus der Warteschlange entfernt.", "postindexer" ), $item->blog_id ) );

						$this->model->remove_blog_from_queue( $item->blog_id );
					}

				}
			} else {

			}

			$this->debug_message( __( 'Beitragsindex Erster Durchlauf', 'postindexer' ), __( "Durchlauf abgeschlossen", "postindexer" ) );
			unset( $this->lockers[ $locker_key ] );

		}

		function process_rebuild_secondpass( $DEBUG = false ) {
			//if ($DEBUG != true) return;

			$locker_key  = 'postindexer_secondpass_cron';
			$locker_info = array();

			$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), __( "Initialisieren...", "postindexer" ) );

			$this->lockers[ $locker_key ] = new ProcessLocker( $locker_key );
			//if ($_locker->is_locked() == false) {
			//	unset($this->lockers[$locker_key]);
			//	$this->debug_message( __('Beitragsindex Zweiter Durchlauf','postindexer'), __("locked by previous process", "postindexer") );
			//	return;
			//}
			$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

			// Second pass - loop through queue entries with a on 0 in the rebuild_progress and start rebuilding
			$queue = $this->model->get_rebuilding_blogs( PI_CRON_SITE_PROCESS_SECONDPASS );
			$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Verarbeite %d in der Warteschlange befindliche Elemente.", "postindexer" ), count( $queue ) ) );
			if ( ! empty( $queue ) ) {

				foreach ( $queue as $item ) {

					if ( $this->model->is_blog_indexable( $item->blog_id ) ) {

						$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, ist indexierbar.", "postindexer" ), $item->blog_id ) );

						$locker_info['blog_id'] = $item->blog_id;
						$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

						// Swtich to the blog so we don't have to keep doing it
						$this->model->switch_to_blog( $item->blog_id );
						$this->model->log_message( $item->blog_id, '' );

						$posts = $this->model->get_posts_for_indexing( $item->blog_id, $item->rebuild_progress );

						if ( ! empty( $posts ) ) {

							$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, %d Beiträge verarbeiten", "postindexer" ), $item->blog_id, count( $posts ) ) );

							foreach ( $posts as $key => $post ) {
								// Check if the post should be indexed or not
								if ( $this->model->is_post_indexable( $post, $item->blog_id ) ) {

									$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Beitrag ID: %d, Beginne mit der Verarbeitung", "postindexer" ), $item->blog_id, $post['ID'] ) );

									$locker_info['post_id'] = $post['ID'];
									$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

									// Get the local Beitrag ID
									$local_id = $post['ID'];
									// Add in the blog id to the post record
									$post['BLOG_ID'] = $item->blog_id;

									// Add the post record to the network tables
									$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Beitrag ID: %d, Indiziere Beitrag", "postindexer" ), $item->blog_id, $post['ID'] ) );
									$this->model->index_post( $post );

									// Get the post meta for this local post
									$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Beitrag ID: %d, Beitrag-Metadaten", "postindexer" ), $item->blog_id, $post['ID'] ) );

									$meta = $this->model->get_postmeta_for_indexing( $local_id, $item->blog_id );
									// Remove any existing ones that we are going to overwrite
									$this->model->remove_postmeta_for_post( $local_id );
									if ( ! empty( $meta ) ) {
										foreach ( $meta as $metakey => $postmeta ) {
											// Add in the blog_id to the table
											$postmeta['blog_id'] = $item->blog_id;
											// Add it to the network tables
											$this->model->index_postmeta( $postmeta );
										}
									}

									// Get the taxonomy for this local post
									$taxonomy = $this->model->get_taxonomy_for_indexing( $local_id, $item->blog_id );

									// Remove any existing ones that we are going to overwrite
									//$this->debug_message( __FUNCTION__,  "calling remove_term_relationships_for_post: local_id[". $local_id ."]");

									$this->model->remove_term_relationships_for_post( $local_id, $item->blog_id );
									if ( ! empty( $taxonomy ) ) {
										$taxonomy_out = '';
										foreach ( $taxonomy as $taxkey => $tax ) {
											if ( ! empty( $taxonomy_out ) ) {
												$taxonomy_out . ', ';
											}
											$taxonomy_out .= $tax['name'];
										}
										//echo "taxonomy<pre>"; print_r($taxonomy); echo "</pre>";
										$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Beitrag ID: %d,  Verarbeitung von Taxonomie: %s", "postindexer" ), $item->blog_id, $post['ID'], $taxonomy_out ) );

										foreach ( $taxonomy as $taxkey => $tax ) {
											$tax['blog_id']   = $item->blog_id;
											$tax['object_id'] = $local_id;

											$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "BLog: %d, Beitrag ID: %d, Verarbeitung von Taxonomie: %s", "postindexer" ), $item->blog_id, $post['ID'], $tax['name'] ) );

											$this->model->index_tax( $tax );
										}
									} else {
										$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Beitrag ID: %d, keine zugehörigen Taxonomien", "postindexer" ), $item->blog_id, $post['ID'] ) );
									}
									$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Beitrag ID: %d, Verarbeitung beenden", "postindexer" ), $item->blog_id, $post['ID'] ) );

								}

								// Update the rebuild queue with the next post to be processed
								$previous_id = (int) ( $local_id - 1 );
								if ( $previous_id > 0 ) {
									// We may still have posts to process
									$this->model->update_blog_queue( $item->blog_id, $previous_id );
								} else {
									// We've run out of posts now so remove us from the queue
									$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Keine Beiträge mehr aus der Warteschlange entfernt.", "postindexer" ), $item->blog_id ) );

									$this->model->remove_blog_from_queue( $item->blog_id );
								}

							}
						} else {
							// We've run out of posts so remove our entry from the queue
							$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, Keine Beiträge mehr aus der Warteschlange entfernt.", "postindexer" ), $item->blog_id ) );

							$this->model->remove_blog_from_queue( $item->blog_id );
						}

						// Switch back from the blog
						$this->model->restore_current_blog();

					} else {
						// Remove the blog from the queue as something has changed
						$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), sprintf( __( "Blog: %d, ist NICHT indizierbar und wird aus der Warteschlange entfernt.", "postindexer" ), $item->blog_id ) );

						$this->model->remove_blog_from_queue( $item->blog_id );
						// Remove any existing posts in case we've already indexed them
						$this->model->remove_indexed_entries_for_blog( $item->blog_id );
					}

				}

			}

			$this->debug_message( __( 'Beitragsindex Zweiter Durchlauf', 'postindexer' ), __( "Verarbeitung abgeschlossen", "postindexer" ) );
			unset( $this->lockers[ $locker_key ] );

		}

		function process_tidy_tags( $DEBUG = false ) {
			//if ($DEBUG != true) return;

			$locker_key  = 'process_tidy_tags';
			$locker_info = array();

			// Hourly tidy up of tags and tag counts
			$this->debug_message( __( 'Beitragsindex Tags aufräumen', 'postindexer' ), __( "Initialisieren...", "postindexer" ) );

			$this->lockers[ $locker_key ] = new ProcessLocker( $locker_key );
			//if ($_locker->is_locked() == false) {
			//	unset($this->lockers[$locker_key]);
			//	$this->debug_message( __('Beitragsindex Tags aufräumen','postindexer'), __("locked by previous process", "postindexer") );
			//	return;
			//}
			$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

			// Remove any orphan tax entries from the table
			$this->debug_message( __( 'Beitragsindex Tags aufräumen', 'postindexer' ), __( "Verwaiste Taxonomieeinträge entfernen", "postindexer" ) );
			$this->model->remove_orphaned_tax_entries();

			// Recalculate the counts for the remaining tax entries
			$this->debug_message( __( 'Beitragsindex Tags aufräumen', 'postindexer' ), __( "Neuberechnung der Taxonomiezähler", "postindexer" ) );
			$this->model->recalculate_tax_counts();

			$this->debug_message( __( 'Beitragsindex Tags aufräumen', 'postindexer' ), __( "Verarbeitung abgeschlossen", "postindexer" ) );

			unset( $this->lockers[ $locker_key ] );

		}

		function process_tidy_postmeta( $DEBUG = false ) {
			//if ($DEBUG != true) return;

			$locker_key  = 'postindexer_postmetatidy_cron';
			$locker_info = array();

			$this->debug_message( __( 'Beitragsindex Beitragsmeta aufräumen', 'postindexer' ), __( "Initialisieren...", "postindexer" ) );

			$this->lockers[ $locker_key ] = new ProcessLocker( $locker_key );
			//if ($_locker->is_locked() == false) {
			//	unset($this->lockers[$locker_key]);
			//	$this->debug_message( __('Beitragsindex Beitragsmeta aufräumen','postindexer'), __("locked by previous process", "postindexer") );
			//	return;
			//}
			$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

			// Hourly tidy up of postmeta entries

			// Remove any orphaned postmeta entries from the table
			$this->debug_message( __( 'Beitragsindex Beitragsmeta aufräumen', 'postindexer' ), __( "Verwaiste Beitragsmeta-Einträge entfernen", "postindexer" ) );
			$this->model->remove_orphaned_postmeta_entries();

			$this->debug_message( __( 'Beitragsindex Beitragsmeta aufräumen', 'postindexer' ), __( "Verarbeitung abgeschlossen", "postindexer" ) );

			unset( $this->lockers[ $locker_key ] );

		}

		function process_tidy_agedposts( $DEBUG = false ) {
			//if ($DEBUG != true) return;

			$this->debug_message( __( 'Beitragsindex Alte Beiträge aufräumen', 'postindexer' ), __( "Initialisieren...", "postindexer" ) );

			$locker_key  = 'postindexer_agedpoststidy_cron';
			$locker_info = array();

			$this->lockers[ $locker_key ] = new ProcessLocker( $locker_key );
			//if ($_locker->is_locked() == false) {
			//	unset($this->lockers[$locker_key]);
			//	$this->debug_message( __('Beitragsindex Alte Beiträge aufräumen','postindexer'), __("locked by previous process", "postindexer") );
			//	return;
			//}
			$this->lockers[ $locker_key ]->set_locker_info( $locker_info );

			// Hourly tidy up of old posts
			// Remove any posts and associated information older than a specified period of time

			// The default is to remove posts from the index when they are over a year old
			$agedposts = get_site_option( 'postindexer_agedposts', array( 'agedunit' => 1, 'agedperiod' => 'year' ) );

			$this->debug_message( __( 'Beitragsindex Alte Beiträge aufräumen', 'postindexer' ), sprintf( __( "Beiträge entfernen, die älter sind als: %d %s", "postindexer" ), $agedposts['agedunit'], $agedposts['agedperiod'] ) );
			$this->model->remove_posts_older_than( $agedposts['agedunit'], $agedposts['agedperiod'] );

			$this->debug_message( __( 'Beitragsindex Alte Beiträge aufräumen', 'postindexer' ), __( "Verarbeitung abgeschlossen", "postindexer" ) );

			unset( $this->lockers[ $locker_key ] );
		}

		function add_time_period( $periods ) {

			if ( ! is_array( $periods ) ) {
				$periods = array();
			}

			$periods['10mins'] = array( 'interval' => 600, 'display' => __( 'Alle 10 Minuten', 'postindexer' ) );
			$periods['5mins']  = array( 'interval' => 300, 'display' => __( 'Alle 5 Minuten', 'postindexer' ) );

			return $periods;
		}

		function set_up_schedule() {

			if ( ! wp_next_scheduled( 'postindexer_firstpass_cron' ) ) {
				wp_schedule_event( time(), $this->rebuildperiod, 'postindexer_firstpass_cron' );
			}

			if ( ! wp_next_scheduled( 'postindexer_secondpass_cron' ) ) {
				wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, $this->rebuildperiod, 'postindexer_secondpass_cron' );
			}

			if ( ! wp_next_scheduled( 'postindexer_tagtidy_cron' ) ) {
				wp_schedule_event( time() + 20 * MINUTE_IN_SECONDS, 'hourly', 'postindexer_tagtidy_cron' );
			}

			if ( ! wp_next_scheduled( 'postindexer_postmetatidy_cron' ) ) {
				wp_schedule_event( time() + 30 * MINUTE_IN_SECONDS, 'hourly', 'postindexer_postmetatidy_cron' );
			}

			if ( ! wp_next_scheduled( 'postindexer_agedpoststidy_cron' ) ) {
				wp_schedule_event( time() + 40 * MINUTE_IN_SECONDS, 'hourly', 'postindexer_agedpoststidy_cron' );
			}

		}

		function debug_message( $title, $message ) {
            if ( defined( 'PI_CRON_DEBUG' ) && PI_CRON_DEBUG === true ) {
                if ( function_exists( 'error_log' ) ) {
                    error_log( '[' . $title . '] ' . $message );
                }
                if ( method_exists( $this->model, 'log_message' ) ) {
                    $this->model->log_message( $title, $message );
                }
                if ( method_exists( $this->model, 'clear_messages' ) && defined( 'PI_CRON_DEBUG_KEEP_LAST' ) ) {
                    $this->model->clear_messages( PI_CRON_DEBUG_KEEP_LAST );
                }
            }
        }

	}

}

$postindexercron = new postindexercron();

?>