<?php

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite_Ajax {
	private ClubCal_Lite_Utils $utils;

	public function __construct(ClubCal_Lite_Utils $utils) {
		$this->utils = $utils;
	}

	public function register(): void {
		add_action('wp_ajax_' . ClubCal_Lite::AJAX_ACTION_EVENTS, [$this, 'ajax_events']);
		add_action('wp_ajax_nopriv_' . ClubCal_Lite::AJAX_ACTION_EVENTS, [$this, 'ajax_events']);
		add_action('wp_ajax_' . ClubCal_Lite::AJAX_ACTION_EVENT_DETAILS, [$this, 'ajax_event_details']);
		add_action('wp_ajax_nopriv_' . ClubCal_Lite::AJAX_ACTION_EVENT_DETAILS, [$this, 'ajax_event_details']);
	}

	public function ajax_events(): void {
		check_ajax_referer('clubcal_lite_events');

		$start = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : '';
		$end = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : '';
		$category = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';

		$start_ts = strtotime($start);
		$end_ts = strtotime($end);

		if ($start_ts === false || $end_ts === false) {
			wp_send_json_error('Invalid date range', 400);
		}

		$post_status = ['publish', 'future'];
		if (is_user_logged_in() && current_user_can('edit_posts')) {
			$post_status = 'any';
		}

		$args = [
			'post_type' => ClubCal_Lite::POST_TYPE,
			'post_status' => $post_status,
			'posts_per_page' => 500,
			'orderby' => 'meta_value',
			'meta_key' => '_clubcal_start',
			'order' => 'ASC',
			'meta_query' => [
				[
					'key' => '_clubcal_start',
					'compare' => 'EXISTS',
				],
			],
		];

		if ($category !== '') {
			$args['tax_query'] = [
				[
					'taxonomy' => ClubCal_Lite::TAX_CATEGORY,
					'field' => 'slug',
					'terms' => [$category],
				],
			];
		}

		$query = new \WP_Query($args);
		$events = [];

		foreach ($query->posts as $post) {
			$start_meta = trim((string) get_post_meta($post->ID, '_clubcal_start', true));
			if ($start_meta === '') {
				continue;
			}

			$start_meta_ts = strtotime($start_meta);
			if ($start_meta_ts === false) {
				continue;
			}

			$end_meta = trim((string) get_post_meta($post->ID, '_clubcal_end', true));
			$end_meta_ts = ($end_meta !== '') ? strtotime($end_meta) : false;
			$has_end_date = ($end_meta_ts !== false && $end_meta_ts > 0);

			$all_day_meta = (string) get_post_meta($post->ID, '_clubcal_all_day', true);
			$is_all_day = ($all_day_meta === '1') || !$has_end_date;

			$event_end_ts = $has_end_date ? $end_meta_ts : strtotime(wp_date('Y-m-d', $start_meta_ts) . ' 23:59:59');

			if ($start_meta_ts > $end_ts || $event_end_ts < $start_ts) {
				continue;
			}

			$location = trim((string) get_post_meta($post->ID, '_clubcal_location', true));

			$start_iso = $this->utils->format_datetime_for_iso($start_meta);
			if ($start_iso === '') {
				continue;
			}

			$event = [
				'id' => $post->ID,
				'title' => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				'start' => $start_iso,
				'url' => get_permalink($post),
				'allDay' => $is_all_day,
			];

			if ($has_end_date) {
				$end_iso = $this->utils->format_datetime_for_iso($end_meta);
				if ($end_iso !== '') {
					$event['end'] = $end_iso;
				}
			}

			$excerpt_plain = trim(html_entity_decode(wp_strip_all_tags((string) $post->post_content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			if ($excerpt_plain !== '') {
				$was_truncated = (mb_strlen($excerpt_plain) > 100);
				$excerpt_plain = mb_substr($excerpt_plain, 0, 100);
				if ($was_truncated) {
					$excerpt_plain .= '...';
				}
			}

			$event['extendedProps'] = [];
			if ($location !== '') {
				$event['extendedProps']['location'] = $location;
			}
			if ($excerpt_plain !== '') {
				$event['extendedProps']['excerpt'] = $excerpt_plain;
			}

			$cat = $this->utils->get_category_display_data($post->ID);
			$color = (string) ($cat['color'] ?? '');
			$name = (string) ($cat['name'] ?? '');
			$has_category = (bool) ($cat['has_category'] ?? false);
			$event['extendedProps']['isUncategorized'] = !$has_category;
			if ($has_category) {
				$event['backgroundColor'] = $color;
				$event['borderColor'] = $color;
			} else {
				$event['backgroundColor'] = '#ffffff';
				$event['borderColor'] = $color;
				$event['textColor'] = $color;
			}
			$event['extendedProps']['categoryName'] = $name;
			$event['extendedProps']['dotColor'] = $color;

			$events[] = $event;
		}

		wp_send_json_success($events);
	}

	public function ajax_event_details(): void {
		check_ajax_referer('clubcal_lite_event_details');

		$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
		if ($event_id <= 0) {
			wp_send_json_error('Invalid event', 400);
		}

		$post = get_post($event_id);
		if (!$post instanceof \WP_Post) {
			wp_send_json_error('Event not found', 404);
		}

		if ($post->post_type !== ClubCal_Lite::POST_TYPE) {
			wp_send_json_error('Event not available', 404);
		}

		if ($post->post_status !== 'publish') {
			if (!(is_user_logged_in() && current_user_can('edit_posts'))) {
				wp_send_json_error('Event not available', 404);
			}
		}

		$start_meta = (string) get_post_meta($post->ID, '_clubcal_start', true);
		$end_meta = (string) get_post_meta($post->ID, '_clubcal_end', true);
		$all_day = (string) get_post_meta($post->ID, '_clubcal_all_day', true);
		$location = (string) get_post_meta($post->ID, '_clubcal_location', true);

		$start_ts = strtotime($start_meta);
		$end_ts = strtotime($end_meta);

		$date_text = '';
		if ($start_ts !== false) {
			$has_end = ($end_meta !== '' && $end_ts !== false && $end_ts > $start_ts);
			$start_text = ($all_day === '1') ? wp_date('Y-m-d', $start_ts) : wp_date('Y-m-d H:i', $start_ts);
			$date_text = $start_text;

			if ($has_end) {
				$end_text = ($all_day === '1') ? wp_date('Y-m-d', $end_ts) : wp_date('Y-m-d H:i', $end_ts);
				if ($end_text !== $start_text) {
					$date_text .= ' – ' . $end_text;
				}
			}
		}

		$title = get_the_title($post);
		$permalink = get_permalink($post);
		$content_html = apply_filters('the_content', $post->post_content);

		$cat = $this->utils->get_category_display_data($post->ID);
		$badge_name = (string) ($cat['name'] ?? '');
		$badge_color = (string) ($cat['color'] ?? '');

		$html = '';
		$html .= '<div class="clubcal-lite-event">';
		$html .= '<h3 class="clubcal-lite-event__title">' . esc_html($title) . '</h3>';

		if ($date_text !== '') {
			$html .= '<p class="clubcal-lite-event__datetime">' . esc_html($date_text);
			if ($badge_name !== '' && $badge_color !== '') {
				$html .= '<span class="clubcal-lite-event__badge" style="--clubcal-badge-color:' . esc_attr($badge_color) . '">' . esc_html($badge_name) . '</span>';
			}
		} else {
			if ($badge_name !== '' && $badge_color !== '') {
				$html .= '<span class="clubcal-lite-event__badge" style="--clubcal-badge-color:' . esc_attr($badge_color) . '">' . esc_html($badge_name) . '</span>';
			}
			$html .= '</p>';
		}

		if ($location !== '') {
			$html .= '<p class="clubcal-lite-event__location">' . esc_html($location) . '</p>';
		}

		$html .= '<div class="clubcal-lite-event__content">' . wp_kses_post($content_html) . '</div>';
		$html .= '<p class="clubcal-lite-event__link"><a href="' . esc_url($permalink) . '">'
			. esc_html__('Open event page', 'clubcal-lite')
			. ' <svg class="clubcal-lite-icon clubcal-lite-icon--external" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>'
			. '</a></p>';
		$html .= '</div>';

		wp_send_json_success(['html' => $html]);
	}
}
