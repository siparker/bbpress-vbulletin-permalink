<?php
/**
 * Plugin Name: bbPress vBulletin Permalinks
 * Plugin URI: https://wordpress.org/
 * Description: vBulletin Like Permalinks.
 * Author: Coffeeblack
 * Author URI: http://www.coffeeblack.co.uk/
 * Version: 1.0.1
 * Text Domain: bbpress-vbulletin-permalinks
 * Domain Path: /languages/
 * Requires at least: 4.1.1
 * Tested up to: 4.5.0
 * License: GPLv2 or later
 */

/*
 * Add plugin actions and filters at bbp_init action which triggered only if bbPress activated.
 *
 * @since 1.0.0
 */
function bbp_permalinks_init() {
	$structure = get_option( 'permalink_structure' );
	if( $structure ) {
		// Run (add rewrite rules) only if WordPress permalink settings not default (default looks like site.com/?p=123)

		add_action( 'flush_rewrite_rules', 'bbp_permalinks_rewrites_init', 0 );
		add_action( 'bbp_add_rewrite_rules', 'bbp_permalinks_rewrites_init', 3 );
		// Create valid URL for our new rewrite rules
		add_filter( 'post_type_link', 'bbp_permalinks_post_type_link_pretty', 99, 2 );
	}
	else {
		// If permalink settings is default only change permalinks
		add_filter( 'post_type_link', 'bbp_permalinks_post_type_link_not_pretty', 99, 2 );
	}
}
add_action( 'bbp_init', 'bbp_permalinks_init' );

/*
 * Generate pretty permalinks for forums and topics.
 *
 * @since 1.0.0
 * @param string $link URL.
 * @param object $post An WordPress post object.
 */
function bbp_permalinks_post_type_link_pretty( $link, $post = 0 ) {
	if( $post->post_type == bbp_get_forum_post_type() ) {
		$parent = bbp_get_forum_parent_id($post->ID);
		if($parent == 0) {
			return home_url(
				user_trailingslashit( 'forums/' .$post->post_name .'-f'.$post->ID)
			);
		} else {
			$parents = bbp_get_forum_ancestors($post->ID);
			asort($parents);
			$url = 'forums/';
			foreach($parents as $parent) {
				$add = bbp_get_forum($parent);
				$url .= $add->post_name.'/';
			}
			$url .= $post->post_name.'-f'.$post->ID;
			return home_url(
				user_trailingslashit($url)
			);
		}
	}
	elseif( $post->post_type == bbp_get_topic_post_type() ) {
		// site.com/forums/topic/ID/
		$parent = bbp_get_forum_parent_id($post->ID);
		$parent = bbp_get_forum($parent);
		$parentofparent = bbp_get_forum_parent_id($parent->ID);
		if($parentofparent == 0) {
			return home_url(
				user_trailingslashit( 'forums/'.$parent->post_name.'/' .$post->ID .'-'.$post->post_name)
			);
		} else {
			$parents = bbp_get_forum_ancestors($parent->ID);
			asort($parents);
			$url = 'forums/';
			foreach($parents as $parent1) {
				$add = bbp_get_forum($parent1);
				$url .= $add->post_name.'/';
			}
			$url .= $parent->post_name.'/';
			$url .= $post->ID .'-'.$post->post_name;
			return home_url(
				user_trailingslashit($url)
			);
		}
	}
	elseif( $post->post_type == bbp_get_reply_post_type() ) {
		// site.com/forums/topic/ID/
		$topic = bbp_get_reply_topic_id();
		$topic = bbp_get_topic($topic);
		//var_dump($topic);
		$forum = bbp_get_forum_id($topic);
		$forum = bbp_get_forum($forum);
		//var_dump($forumcrumbs);
		$parents = bbp_get_forum_ancestors($forum);
		//var_dump($parents);
		asort($parents);
		$url = 'forums/';
		foreach($parents as $parent1) {
			$add = bbp_get_forum($parent1);
			$url .= $add->post_name.'/';
		}
		$url .= $forum->post_name.'/';
		$url .= $topic->post_name.'/';
		$url .= $post->ID;
		return home_url(
			user_trailingslashit($url)
		);
	}
	return $link;
}

/*
 * Generate default permalinks for forums and topics.
 *
 * @since 1.0.0
 * @param string $link URL.
 * @param object $post An WordPress post object.
 */
function bbp_permalinks_post_type_link_not_pretty( $link, $post = 0 ) {
	if( $post->post_type == bbp_get_forum_post_type() ) {
		// site.com/?post_type=forum&p=ID
		return home_url( '?post_type=' . bbp_get_forum_post_type() . '&p=' . $post->post_name );
	}
	elseif( $post->post_type == bbp_get_topic_post_type() ) {
		// site.com/?post_type=topic&p=ID
		return home_url( '?post_type=' . bbp_get_topic_post_type() . '&p=' . $post->post_name );
	}
	elseif( $post->post_type == bbp_get_reply_post_type() ) {
		// site.com/?post_type=topic&p=ID
		return home_url( '?post_type=' . bbp_get_reply_post_type() . '&p=' . $post->post_name );
	}
	return $link;
}

/*
 * Generate rewrite rules for forums and topics based on bbPress settings.
 *
 * @since 1.0.0
 */
function bbp_permalinks_rewrites_init() {
	$priority = 'top';
	$edit_slug = 'edit';
	$cat_regex = '/[0-99](?=\/)/';
	$ids_regex = '/([0-9]+)/';

	$forum_slug = bbp_get_forum_slug(); // string 'forum'
	$topic_slug = bbp_get_topic_slug(); // string 'topic'
	$reply_slug = bbp_get_reply_slug(); // string 'slug'

	$paged_slug = bbp_get_paged_slug(); // string 'page'

	$paged_rule = '/([^/]+)/' . $paged_slug . '/?([0-9]{1,})/?$';
	$paged_rule_ids =  $ids_regex . $paged_slug . '/?([0-9]{1,})/?$';

	$view_id = bbp_get_view_rewrite_id();
	$paged_id = bbp_get_paged_rewrite_id();

	$edit_rule = $ids_regex . $edit_slug  . '/?$'; // for edit links
	$edit_id = bbp_get_edit_rewrite_id(); // for edit links


	/* From bbpress/bbpress.php (816 line)
	 * Edit Forum|Topic|Reply|Topic-tag
	 * forums/forum/ID/edit/
	 */
	add_rewrite_rule(
		'forums\/[\s\S]+\-f([0-9]+)\/edit/?$',
		'index.php?post_type=' . bbp_get_forum_post_type() . '&p=$matches[1]&' . $edit_id . '=1',
		$priority
	);
	// forums/topic/ID/edit/
	add_rewrite_rule(
		'forums\/[\s\S]+\/([0-9]+)\-[\s\S]+\/edit/?$',
		'index.php?post_type=' . bbp_get_topic_post_type() . '&p=$matches[1]&' . $edit_id . '=1',
		$priority
	);
	// forums/reply/ID/edit/
	add_rewrite_rule(
		'forums\/[\s\S]+\/([0-9]+)\/edit/?$',
		'index.php?post_type=' . bbp_get_reply_post_type() . '&p=$matches[1]&' . $edit_id . '=1',
		$priority
	);


	/* Forums
	 * /forums/forum/ID/page/2
	 */
	add_rewrite_rule(
		'forums\/[\s\S]+\-f([0-9]+)\/page\/([0-9]+)/?$',
		'index.php?post_type=' . bbp_get_forum_post_type() . '&p=$matches[1]&' . $paged_id .'=$matches[2]',
		$priority
	);
	// /forums/forum/ID/
	add_rewrite_rule(
		'forums\/[\s\S]+\-f([0-9]+)/?$',
		'index.php?post_type=' . bbp_get_forum_post_type() . '&p=$matches[1]',
		$priority
	);


	/* Topics
	 * /forums/topic/ID/page/2/
	 */
	add_rewrite_rule(
		'forums\/[\s\S]+\/([0-9]+)\-[\s\S]+\/page\/([0-9]+)/?$',
		'index.php?post_type=' . bbp_get_topic_post_type() . '&p=$matches[1]&' . $paged_id . '=$matches[2]',
		$priority
	);
	// /forums/topic/ID/
	add_rewrite_rule(
		'forums\/[\s\S]+\/([0-9]+)\-[\s\S]+/?$',
		'index.php?post_type=' . bbp_get_topic_post_type() .'&p=$matches[1]',
		$priority
	);
}

/*
 * Activation callback. Check if bbPress activated. Check permalink structure settings in WordPress.
 * If both of conditions comes to true then add new rewrite rules and flush it.
 *
 * @since 1.0.0
 */
function bbp_permalinks_activate() {
	/* 
	 * We need add new rewrite rules first and only after this call flush_rewrite_rules
	 * In other ways flush_rewrite_rules doesn't work.
	 */
	if( function_exists( 'bbpress' ) ) {
		/*
		 * Check if bbPress plugin activated
		 * bbp_permalinks_rewrites_init use bbPress links and if bbPress not activated we call undefined functions
		 * and got a fatal error.
		 */
		$structure = get_option( 'permalink_structure' );
		if( $structure ) {
			// Run (add rewrite rules) only if WordPress permalink settings not default (site.com/?p=123)
			bbp_permalinks_rewrites_init();
			flush_rewrite_rules( false );
		}
	}
}
// This stuff not working (Currently in progress)
//register_activation_hook( __FILE__, 'bbp_permalinks_activate' );

/*
 * Deactivation callback. Flush rewrite rules.
 *
 * @since 1.0.0
 */
function bbp_permalinks_deactivate() {
	flush_rewrite_rules( false );
}
// This stuff not working (Currently in progress)
// register_deactivation_hook( __FILE__, 'bbp_permalinks_deactivate' );
?>