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

		$start_ts = $this->utils->parse_any_datetime_to_timestamp($start);
		$end_ts = $this->utils->parse_any_datetime_to_timestamp($end);

		if ($start_ts <= 0 || $end_ts <= 0) {
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

			$start_meta_ts = $this->utils->parse_stored_datetime_to_timestamp($start_meta);
			if ($start_meta_ts <= 0) {
				continue;
			}

			$end_meta = trim((string) get_post_meta($post->ID, '_clubcal_end', true));
			$end_meta_ts = ($end_meta !== '') ? $this->utils->parse_stored_datetime_to_timestamp($end_meta) : 0;
			$has_end_date = ($end_meta_ts > $start_meta_ts);

			$all_day_meta = (string) get_post_meta($post->ID, '_clubcal_all_day', true);
			$is_all_day = ($all_day_meta === '1');

			$event_end_ts = $start_meta_ts;
			if ($has_end_date) {
				$event_end_ts = $end_meta_ts;
			} elseif ($is_all_day) {
				$day = wp_date('Y-m-d', $start_meta_ts);
				$dt_end = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $day . ' 23:59:59', wp_timezone());
				if ($dt_end instanceof \DateTimeImmutable) {
					$event_end_ts = (int) $dt_end->getTimestamp();
				}
			}

			if ($start_meta_ts > $end_ts || $event_end_ts < $start_ts) {
				continue;
			}

			$location = trim((string) get_post_meta($post->ID, '_clubcal_location', true));

			$start_iso = $this->utils->format_datetime_for_iso($start_meta);
			if ($start_iso === '') {
				continue;
			}

			$end_iso = '';
			if ($has_end_date) {
				$end_iso = $this->utils->format_datetime_for_iso($end_meta);
			}

			// FullCalendar allDay events use end-exclusive semantics and should be sent as date-only strings.
			// Example: 2026-03-01 to 2026-03-03 => start=2026-03-01, end=2026-03-04
			if ($is_all_day) {
				$start_day = wp_date('Y-m-d', $start_meta_ts);
				$end_day_inclusive = $has_end_date ? wp_date('Y-m-d', $end_meta_ts) : $start_day;
				$end_exclusive_dt = \DateTimeImmutable::createFromFormat('Y-m-d', $end_day_inclusive, wp_timezone());
				if ($end_exclusive_dt instanceof \DateTimeImmutable) {
					$end_exclusive_dt = $end_exclusive_dt->modify('+1 day');
					$end_iso = $end_exclusive_dt->format('Y-m-d');
				}
				$start_iso = $start_day;
			}

			$event = [
				'id' => $post->ID,
				'title' => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				'start' => $start_iso,
				'url' => get_permalink($post),
				'allDay' => $is_all_day,
			];

			if ($end_iso !== '') {
				$event['end'] = $end_iso;
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

			// Add booking info
			$booking_enabled = get_post_meta($post->ID, '_clubcal_booking_enabled', true) === '1';
			$event['extendedProps']['bookingEnabled'] = $booking_enabled;

			if ($booking_enabled && class_exists('ClubCal_Lite_Booking')) {
				$spots_remaining = ClubCal_Lite_Booking::get_spots_remaining($post->ID);
				$event['extendedProps']['spotsRemaining'] = $spots_remaining;
				$event['extendedProps']['isFullyBooked'] = ($spots_remaining !== null && $spots_remaining <= 0);
				
				$price = get_post_meta($post->ID, '_clubcal_price', true);
				if ($price) {
					$event['extendedProps']['price'] = $price;
				}
			}

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

		$start_ts = $this->utils->parse_stored_datetime_to_timestamp($start_meta);
		$end_ts = $end_meta !== '' ? $this->utils->parse_stored_datetime_to_timestamp($end_meta) : 0;

		$date_text = '';
		if ($start_ts > 0) {
			$has_end = ($end_meta !== '' && $end_ts > $start_ts);
			$date_format = (string) get_option('date_format');
			$time_format = (string) get_option('time_format');
			$format = ($all_day === '1') ? $date_format : ($date_format . ' ' . $time_format);
			$start_text = wp_date($format, $start_ts);
			$date_text = $start_text;

			if ($has_end) {
				$end_text = wp_date($format, $end_ts);
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

		// Add booking form if enabled
		$html .= $this->render_booking_form($post->ID);

		$html .= '</div>';

		wp_send_json_success(['html' => $html]);
	}

	/**
	 * Render booking form HTML for an event
	 */
	private function render_booking_form(int $event_id): string {
		$booking_enabled = get_post_meta($event_id, '_clubcal_booking_enabled', true) === '1';
		if (!$booking_enabled) {
			return '';
		}

		$price = get_post_meta($event_id, '_clubcal_price', true);
		$max_spots = (int) get_post_meta($event_id, '_clubcal_max_spots', true);
		
		$spots_remaining = null;
		$is_fully_booked = false;
		
		if (class_exists('ClubCal_Lite_Booking')) {
			$spots_remaining = ClubCal_Lite_Booking::get_spots_remaining($event_id);
			$is_fully_booked = ClubCal_Lite_Booking::is_fully_booked($event_id);
		}

		$html = '<div class="clubcal-lite-booking" data-event-id="' . esc_attr($event_id) . '">';
		$html .= '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;" />';
		$html .= '<h4 class="clubcal-lite-booking__title">' . esc_html__('Book this event', 'clubcal-lite') . '</h4>';

		// Show price and spots
		if ($price || $spots_remaining !== null) {
			$html .= '<p class="clubcal-lite-booking__meta">';
			if ($price) {
				$html .= '<span class="clubcal-lite-booking__price">' . esc_html__('Price:', 'clubcal-lite') . ' <strong>' . esc_html($price) . '</strong></span>';
			}
			if ($spots_remaining !== null) {
				if ($price) {
					$html .= ' &bull; ';
				}
				$spots_text = $is_fully_booked
					? __('Fully booked', 'clubcal-lite')
					: sprintf(__('%d spots left', 'clubcal-lite'), $spots_remaining);
				$html .= '<span class="clubcal-lite-booking__spots' . ($is_fully_booked ? ' clubcal-lite-booking__spots--full' : '') . '">' . esc_html($spots_text) . '</span>';
			}
			$html .= '</p>';
		}

		if ($is_fully_booked) {
			$html .= '<p class="clubcal-lite-booking__full">' . esc_html__('This event is fully booked. Please check back later or contact us.', 'clubcal-lite') . '</p>';
		} else {
			// Booking form
			$html .= '<form class="clubcal-lite-booking__form" data-clubcal-booking-form>';
			$html .= '<input type="hidden" name="event_id" value="' . esc_attr($event_id) . '" />';
			
			$html .= '<p class="clubcal-lite-booking__field">';
			$html .= '<label for="clubcal_book_name">' . esc_html__('Name', 'clubcal-lite') . ' <span class="required">*</span></label>';
			$html .= '<input type="text" id="clubcal_book_name" name="name" required />';
			$html .= '</p>';

			$html .= '<p class="clubcal-lite-booking__field">';
			$html .= '<label for="clubcal_book_email">' . esc_html__('Email', 'clubcal-lite') . ' <span class="required">*</span></label>';
			$html .= '<input type="email" id="clubcal_book_email" name="email" required />';
			$html .= '</p>';

			$html .= '<p class="clubcal-lite-booking__field">';
			$html .= '<label for="clubcal_book_phone">' . esc_html__('Phone', 'clubcal-lite') . ' <span class="optional">(' . esc_html__('optional', 'clubcal-lite') . ')</span></label>';
			$html .= '<input type="tel" id="clubcal_book_phone" name="phone" />';
			$html .= '</p>';

			$html .= '<p class="clubcal-lite-booking__submit">';
			$html .= '<button type="submit" class="clubcal-lite-booking__button">' . esc_html__('Book now', 'clubcal-lite') . '</button>';
			$html .= '</p>';

			$html .= '<p class="clubcal-lite-booking__message" data-clubcal-booking-message style="display: none;"></p>';
			$html .= '</form>';
		}

		$html .= '</div>';
		return $html;
	}
}
