<?php

/*
Usage:   display_recent_comments(NUMBER,CONTENT_CHARACTERS,GLOBAL_BEFORE,GLOBAL_AFTER,BEFORE,AFTER,LINK);
Example: display_recent_comments(10,150,'<ul>','</ul>','<li>','</li>','no');
*/

function display_recent_comments($tmp_number,$tmp_content_characters = 100,$tmp_global_before,$tmp_global_after,$tmp_before,$tmp_after,$link = 'no'){
	global $wpdb;
	$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "site_comments WHERE comment_approved = '1' ORDER BY site_comment_id DESC LIMIT %d", $tmp_number);
	$tmp_comments = $wpdb->get_results( $query, ARRAY_A );
	
	if (count($tmp_comments) > 0){
		echo $tmp_global_before;
		foreach ($tmp_comments as $tmp_comment){
			echo $tmp_before;
			if ( $tmp_content_characters > 0 ) {
				if ( $link == 'no' ) {
					echo substr($tmp_comment['comment_content_stripped'],0,$tmp_content_characters);
				} else {
					echo '<a href="' . $tmp_comment['comment_post_permalink'] . '#comment-' . $tmp_comment['comment_id'] . '" style="text-decoration:none;">' . substr($tmp_comment['comment_content_stripped'],0,$tmp_content_characters) . '</a>';
				}
			}
			echo $tmp_after;
		}
		echo $tmp_global_after;
	}
}

