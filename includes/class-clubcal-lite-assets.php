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

		$accent = get_theme_mod( 'upaif_color_accent_red', '#9b2d30' );
		$text = get_theme_mod( 'upaif_color_text_dark', '#2d1e12' );
		$bg = get_theme_mod( 'upaif_color_bg_primary', '#f5f0e6' );
		$accent = sanitize_hex_color( $accent ) ?: '#9b2d30';
		$text = sanitize_hex_color( $text ) ?: '#2d1e12';
		$bg = sanitize_hex_color( $bg ) ?: '#f5f0e6';

		$inline_css = ".clubcal-lite-list{--clubcal-list-bg:rgba(255,255,255,.88);--clubcal-list-text:{$text};--clubcal-list-muted:rgba(45,30,18,.65);--clubcal-list-border:rgba(45,30,18,.10);} .clubcal-lite-list__link a{color:{$accent};}";
		wp_add_inline_style( 'clubcal-lite', $inline_css );

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

		$accent = get_theme_mod( 'upaif_color_accent_red', '#9b2d30' );
		$text = get_theme_mod( 'upaif_color_text_dark', '#2d1e12' );
		$bg = get_theme_mod( 'upaif_color_bg_primary', '#f5f0e6' );
		$accent = sanitize_hex_color( $accent ) ?: '#9b2d30';
		$text = sanitize_hex_color( $text ) ?: '#2d1e12';
		$bg = sanitize_hex_color( $bg ) ?: '#f5f0e6';

		$inline_css = ".clubcal-lite-calendar,.clubcal-lite-modal{--clubcal-bg:{$bg};--clubcal-surface:rgba(255,255,255,.90);--clubcal-text:{$text};--clubcal-muted:rgba(45,30,18,.65);--clubcal-border:rgba(45,30,18,.12);--clubcal-accent:{$accent};--clubcal-backdrop:rgba(0,0,0,.55);} .clubcal-lite-event__link a{color:var(--clubcal-accent);}";
		wp_add_inline_style( 'clubcal-lite', $inline_css );

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
				'actionBook' => ClubCal_Lite::AJAX_ACTION_BOOK,
				'nonceEvents' => wp_create_nonce('clubcal_lite_events'),
				'nonceDetails' => wp_create_nonce('clubcal_lite_event_details'),
				'nonceBook' => wp_create_nonce('clubcal_lite_book'),
				'i18n' => [
					'booking' => __('Booking...', 'clubcal-lite'),
					'booked' => __('Booked!', 'clubcal-lite'),
					'bookingSuccess' => __('Your booking is confirmed! Confirmation code:', 'clubcal-lite'),
					'bookingError' => __('Booking failed. Please try again.', 'clubcal-lite'),
					'payWithSwish' => __('Pay with Swish:', 'clubcal-lite'),
					'swishMessage' => __('Message:', 'clubcal-lite'),
					'amount' => __('Amount:', 'clubcal-lite'),
					'openSwish' => __('Open Swish', 'clubcal-lite'),
					'scanQR' => __('Scan the QR code with the Swish app:', 'clubcal-lite'),
					'orPayManually' => __('Or pay manually to:', 'clubcal-lite'),
					'payToNumber' => __('Pay to:', 'clubcal-lite'),
					'awaitingPayment' => __('Awaiting payment...', 'clubcal-lite'),
					'paymentConfirmed' => __('Payment confirmed!', 'clubcal-lite'),
					'paymentFailed' => __('Payment failed', 'clubcal-lite'),
					'paymentTimeout' => __('Payment could not be confirmed. Contact us if you have paid.', 'clubcal-lite'),
					'redirectingToPayment' => __('Redirecting to payment...', 'clubcal-lite'),
					'proceedToPayment' => __('Proceed to Payment', 'clubcal-lite'),
				],
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
