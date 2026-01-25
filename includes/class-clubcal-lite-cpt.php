<?php

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite_Cpt {
	public function register(): void {
		add_action('init', [$this, 'register_cpt_and_taxonomies']);
	}

	public function register_cpt_and_taxonomies(): void {
		$labels = [
			'name' => __('Events', 'clubcal-lite'),
			'singular_name' => __('Event', 'clubcal-lite'),
			'menu_name' => __('Calendar', 'clubcal-lite'),
			'name_admin_bar' => __('Event', 'clubcal-lite'),
			'add_new' => __('Add New', 'clubcal-lite'),
			'add_new_item' => __('Add New Event', 'clubcal-lite'),
			'new_item' => __('New Event', 'clubcal-lite'),
			'edit_item' => __('Edit Event', 'clubcal-lite'),
			'view_item' => __('View Event', 'clubcal-lite'),
			'all_items' => __('All Events', 'clubcal-lite'),
			'search_items' => __('Search Events', 'clubcal-lite'),
			'not_found' => __('No events found.', 'clubcal-lite'),
			'not_found_in_trash' => __('No events found in Trash.', 'clubcal-lite'),
		];

		$args = [
			'labels' => $labels,
			'public' => true,
			'show_in_rest' => true,
			'has_archive' => true,
			'menu_icon' => 'dashicons-calendar-alt',
			'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
			'rewrite' => ['slug' => 'events'],
		];

		register_post_type(ClubCal_Lite::POST_TYPE, $args);

		$cat_labels = [
			'name' => __('Event Categories', 'clubcal-lite'),
			'singular_name' => __('Event Category', 'clubcal-lite'),
		];

		register_taxonomy(
			ClubCal_Lite::TAX_CATEGORY,
			[ClubCal_Lite::POST_TYPE],
			[
				'labels' => $cat_labels,
				'public' => true,
				'show_in_rest' => true,
				'hierarchical' => true,
				'rewrite' => ['slug' => 'event-category'],
			]
		);

		$tag_labels = [
			'name' => __('Event Tags', 'clubcal-lite'),
			'singular_name' => __('Event Tag', 'clubcal-lite'),
		];

		register_taxonomy(
			ClubCal_Lite::TAX_TAG,
			[ClubCal_Lite::POST_TYPE],
			[
				'labels' => $tag_labels,
				'public' => true,
				'show_in_rest' => true,
				'hierarchical' => false,
				'rewrite' => ['slug' => 'event-tag'],
			]
		);
	}
}
