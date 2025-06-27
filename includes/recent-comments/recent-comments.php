<?php
/*
Plugin Name: Recent Comments
Plugin URI: http://premium.wpmudev.org/project/recent-comments
Description: Allows you to display a list of recent comments on your front page.
Author: Ivan Shaovchev & Andrew Billits (Incsub)
Author URI: http://ivan.sh
Version: 1.0.2.1
Network: true
WDP ID: 28
*/

/* 
Copyright 2007-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
Usage:   display_recent_comments(NUMBER,CONTENT_CHARACTERS,GLOBAL_BEFORE,GLOBAL_AFTER,BEFORE,AFTER,LINK);
Example: display_recent_comments(10,150,'<ul>','</ul>','<li>','</li>','no');
*/

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

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

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

