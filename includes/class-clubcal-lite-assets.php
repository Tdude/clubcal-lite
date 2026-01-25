<?php

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite_Assets {
	private ClubCal_Lite $plugin;
	private bool $frontend_assets_enqueued = false;

	public function __construct(ClubCal_Lite $plugin) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_frontend_assets']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
	}

	public function maybe_enqueue_list_assets(): void {
		wp_enqueue_style(
			'clubcal-lite',
			plugins_url('style.css', $this->plugin->plugin_file()),
			[],
			ClubCal_Lite::VERSION
		);

		wp_enqueue_script(
			'clubcal-lite-list',
			plugins_url('assets/clubcal-lite-list.js', $this->plugin->plugin_file()),
			[],
			ClubCal_Lite::VERSION,
			true
		);
	}

	public function maybe_enqueue_frontend_assets(): void {
		if ($this->frontend_assets_enqueued) {
			return;
		}

		$should_enqueue = false;
		if (is_singular()) {
			$post = get_post();
			if ($post instanceof \WP_Post) {
				$should_enqueue = has_shortcode($post->post_content, 'club_calendar');
			}
		}

		if (!$should_enqueue && !is_admin()) {
			return;
		}

		$this->frontend_assets_enqueued = true;

		wp_enqueue_style(
			'clubcal-lite',
			plugins_url('style.css', $this->plugin->plugin_file()),
			[],
			ClubCal_Lite::VERSION
		);

		wp_enqueue_style(
			'fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css',
			[],
			'6.1.15'
		);

		wp_enqueue_script(
			'fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
			[],
			'6.1.15',
			true
		);

		wp_enqueue_script(
			'clubcal-lite-calendar',
			plugins_url('assets/clubcal-lite-calendar.js', $this->plugin->plugin_file()),
			['fullcalendar'],
			ClubCal_Lite::VERSION,
			true
		);

		wp_localize_script(
			'clubcal-lite-calendar',
			'ClubCalLite',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'actionEvents' => ClubCal_Lite::AJAX_ACTION_EVENTS,
				'actionDetails' => ClubCal_Lite::AJAX_ACTION_EVENT_DETAILS,
				'nonceEvents' => wp_create_nonce('clubcal_lite_events'),
				'nonceDetails' => wp_create_nonce('clubcal_lite_event_details'),
			]
		);
	}

	public function enqueue_admin_assets(string $hook_suffix): void {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen) {
			return;
		}

		if ($screen->base !== 'post' || $screen->post_type !== ClubCal_Lite::POST_TYPE) {
			return;
		}

		wp_enqueue_script(
			'clubcal-lite-admin',
			plugins_url('assets/clubcal-lite-admin.js', $this->plugin->plugin_file()),
			[],
			ClubCal_Lite::VERSION,
			true
		);
	}
}
