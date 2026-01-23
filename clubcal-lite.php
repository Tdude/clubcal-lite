<?php
/**
 * Plugin Name: ClubCal Lite
 * Description: Lightweight club calendar using a custom post type. Xtremely lightweight, 200kb including FullCalendar with AJAX events loading, modal event details and minimal styling.
 * Version: 0.2.2
 * Author: Tibor Berki <https://github.com/Tdude>
 * Text Domain: clubcal-lite
 */

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite {
	public const VERSION = '0.2.2';
	public const POST_TYPE = 'club_event';
	public const TAX_CATEGORY = 'event_category';
	private const CATEGORY_FALLBACK_PALETTE = [
		'#2563eb',
		'#16a34a',
		'#dc2626',
		'#7c3aed',
		'#ea580c',
		'#0891b2',
		'#ca8a04',
		'#be185d',
	];
	public const TAX_TAG = 'event_tag';
	private const AJAX_ACTION_EVENTS = 'clubcal_lite_events';
	private const AJAX_ACTION_EVENT_DETAILS = 'clubcal_lite_event_details';
	private bool $frontend_assets_enqueued = false;
	private bool $modal_markup_rendered = false;

	public function init(): void {
		add_action('plugins_loaded', [$this, 'load_textdomain']);
		add_action('init', [$this, 'register_cpt_and_taxonomies']);
		add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
		add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta_boxes']);
		add_action('init', [$this, 'register_shortcodes']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
		add_action('wp_ajax_' . self::AJAX_ACTION_EVENTS, [$this, 'ajax_events']);
		add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_EVENTS, [$this, 'ajax_events']);
		add_action('wp_ajax_' . self::AJAX_ACTION_EVENT_DETAILS, [$this, 'ajax_event_details']);
		add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_EVENT_DETAILS, [$this, 'ajax_event_details']);
	}

	public function load_textdomain(): void {
		load_plugin_textdomain('clubcal-lite', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	public function activate(): void {
		$this->register_cpt_and_taxonomies();
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
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

		register_post_type(self::POST_TYPE, $args);

		$cat_labels = [
			'name' => __('Event Categories', 'clubcal-lite'),
			'singular_name' => __('Event Category', 'clubcal-lite'),
		];

		register_taxonomy(
			self::TAX_CATEGORY,
			[self::POST_TYPE],
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
			self::TAX_TAG,
			[self::POST_TYPE],
			[
				'labels' => $tag_labels,
				'public' => true,
				'show_in_rest' => true,
				'hierarchical' => false,
				'rewrite' => ['slug' => 'event-tag'],
			]
		);
	}

	public function register_meta_boxes(): void {
		add_meta_box(
			'clubcal_lite_event_details',
			__('Event Details', 'clubcal-lite'),
			[$this, 'render_event_details_meta_box'],
			self::POST_TYPE,
			'normal',
			'high'
		);
		
		add_action(self::TAX_CATEGORY . '_add_form_fields', [$this, 'render_category_color_field_add']);
		add_action(self::TAX_CATEGORY . '_edit_form_fields', [$this, 'render_category_color_field_edit']);
		add_action('created_' . self::TAX_CATEGORY, [$this, 'save_category_color']);
		add_action('edited_' . self::TAX_CATEGORY, [$this, 'save_category_color']);
	}

	public function render_category_color_field_add(): void {
		?>
		<div class="form-field">
			<label for="clubcal_category_color"><?php esc_html_e('Color', 'clubcal-lite'); ?></label>
			<input type="color" id="clubcal_category_color" name="clubcal_category_color" value="#3788d8" />
			<p class="description"><?php esc_html_e('Choose a color for events in this category.', 'clubcal-lite'); ?></p>
		</div>
		<?php
	}

	public function render_category_color_field_edit(\WP_Term $term): void {
		$color = get_term_meta($term->term_id, 'clubcal_category_color', true);
		if (empty($color)) {
			$color = '#3788d8';
		}
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="clubcal_category_color"><?php esc_html_e('Color', 'clubcal-lite'); ?></label>
			</th>
			<td>
				<input type="color" id="clubcal_category_color" name="clubcal_category_color" value="<?php echo esc_attr($color); ?>" />
				<p class="description"><?php esc_html_e('Choose a color for events in this category.', 'clubcal-lite'); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save_category_color(int $term_id): void {
		if (!isset($_POST['clubcal_category_color'])) {
			return;
		}

		// Check user capabilities
		if (!current_user_can('manage_categories')) {
			return;
		}

		$color = sanitize_hex_color($_POST['clubcal_category_color']);
		if ($color) {
			update_term_meta($term_id, 'clubcal_category_color', $color);
		} else {
			delete_term_meta($term_id, 'clubcal_category_color');
		}
	}

	public function render_event_details_meta_box(\WP_Post $post): void {
		wp_nonce_field('clubcal_lite_save_event_details', 'clubcal_lite_event_details_nonce');

		$start = $this->format_datetime_for_input(get_post_meta($post->ID, '_clubcal_start', true));
		$end = $this->format_datetime_for_input(get_post_meta($post->ID, '_clubcal_end', true));
		$all_day = get_post_meta($post->ID, '_clubcal_all_day', true);
		$location = get_post_meta($post->ID, '_clubcal_location', true);

		$all_day_checked = (($all_day === '1') || ($all_day === '' && $post->post_status === 'auto-draft')) ? 'checked' : '';

		echo '<p>';
		echo '<label for="clubcal_lite_start"><strong>' . esc_html__('Start date/time', 'clubcal-lite') . '</strong></label><br />';
		echo '<input type="datetime-local" id="clubcal_lite_start" name="clubcal_lite_start" value="' . esc_attr($start) . '" style="width: 100%; max-width: 320px;" />';
		echo '</p>';

		echo '<p>';
		echo '<label for="clubcal_lite_end"><strong>' . esc_html__('End date/time', 'clubcal-lite') . '</strong> <span style="font-weight: normal; color: #666;">(' . esc_html__('optional', 'clubcal-lite') . ')</span></label><br />';
		echo '<input type="datetime-local" id="clubcal_lite_end" name="clubcal_lite_end" value="' . esc_attr($end) . '" style="width: 100%; max-width: 320px;" />';
		echo '<p class="description" style="margin-top: 4px;">' . esc_html__('Leave empty for single-day events.', 'clubcal-lite') . '</p>';
		echo '</p>';

		echo '<p>';
		echo '<label for="clubcal_lite_all_day">';
		echo '<input type="checkbox" id="clubcal_lite_all_day" name="clubcal_lite_all_day" value="1" ' . esc_attr($all_day_checked) . ' /> ';
		echo esc_html__('All day', 'clubcal-lite');
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label for="clubcal_lite_location"><strong>' . esc_html__('Location', 'clubcal-lite') . '</strong></label><br />';
		echo '<input type="text" id="clubcal_lite_location" name="clubcal_lite_location" value="' . esc_attr($location) . '" style="width: 100%;" />';
		echo '</p>';
	}

	public function save_meta_boxes(int $post_id): void {
		if (!isset($_POST['clubcal_lite_event_details_nonce'])) {
			return;
		}

		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['clubcal_lite_event_details_nonce'])), 'clubcal_lite_save_event_details')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$start_raw = isset($_POST['clubcal_lite_start']) ? sanitize_text_field(wp_unslash($_POST['clubcal_lite_start'])) : '';
		$end_raw = isset($_POST['clubcal_lite_end']) ? sanitize_text_field(wp_unslash($_POST['clubcal_lite_end'])) : '';
		$all_day = isset($_POST['clubcal_lite_all_day']) ? '1' : '0';
		$location = isset($_POST['clubcal_lite_location']) ? sanitize_text_field(wp_unslash($_POST['clubcal_lite_location'])) : '';

		$start = $this->normalize_datetime_for_storage($start_raw);
		$end = $this->normalize_datetime_for_storage($end_raw);

		if ($start !== '') {
			update_post_meta($post_id, '_clubcal_start', $start);
		} else {
			delete_post_meta($post_id, '_clubcal_start');
		}

		if ($end !== '') {
			update_post_meta($post_id, '_clubcal_end', $end);
		} else {
			delete_post_meta($post_id, '_clubcal_end');
		}

		update_post_meta($post_id, '_clubcal_all_day', $all_day);

		if ($location !== '') {
			update_post_meta($post_id, '_clubcal_location', $location);
		} else {
			delete_post_meta($post_id, '_clubcal_location');
		}
	}

	private function normalize_datetime_for_storage(string $value): string {
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		// Handle datetime-local format (YYYY-MM-DDTHH:MM)
		// Also handle date-only format (YYYY-MM-DD)
		$timestamp = strtotime($value);
		if ($timestamp === false) {
			// Try adding default time if it's a date-only value
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				$timestamp = strtotime($value . ' 00:00:00');
			}
		}

		if ($timestamp === false) {
			return '';
		}

		return wp_date('Y-m-d H:i:s', $timestamp);
	}

	private function format_datetime_for_input(string $stored): string {
		$stored = trim($stored);
		if ($stored === '') {
			return '';
		}

		$timestamp = strtotime($stored);
		if ($timestamp === false) {
			return '';
		}

		return wp_date('Y-m-d\\TH:i', $timestamp);
	}

	private function format_datetime_for_iso(string $stored): string {
		$stored = trim($stored);
		if ($stored === '') {
			return '';
		}

		$timestamp = strtotime($stored);
		if ($timestamp === false) {
			return '';
		}

		return wp_date('c', $timestamp);
	}

	private function get_category_display_data(int $post_id): array {
		$categories = wp_get_post_terms($post_id, self::TAX_CATEGORY);
		if (!empty($categories) && !is_wp_error($categories)) {
			$primary_category = $categories[0];
			$color = (string) get_term_meta($primary_category->term_id, 'clubcal_category_color', true);
			if ($color === '') {
				$idx = absint($primary_category->term_id) % count(self::CATEGORY_FALLBACK_PALETTE);
				$color = self::CATEGORY_FALLBACK_PALETTE[$idx];
			}
			return [
				'has_category' => true,
				'name' => $primary_category->name,
				'color' => $color,
			];
		}

		return [
			'has_category' => false,
			'name' => esc_html__('Uncategorized', 'clubcal-lite'),
			'color' => '#111827',
		];
	}

	public function register_shortcodes(): void {
		add_shortcode('club_calendar', [$this, 'shortcode_club_calendar']);
		add_shortcode('club_events_list', [$this, 'shortcode_club_events_list']);
	}

	public function shortcode_club_calendar(array $atts = []): string {
		$this->maybe_enqueue_frontend_assets();

		$atts = shortcode_atts(
			[
				'category' => '',
				'view' => 'dayGridMonth',
				'initial_date' => '',
				'list_months' => '3',
			],
			$atts,
			'club_calendar'
		);

		// Sanitize list_months to be between 1 and 12
		$list_months = max(1, min(12, intval($atts['list_months'])));

		$id = 'clubcal-lite-calendar-' . wp_generate_uuid4();

		$calendar = sprintf(
			'<div id="%s" class="clubcal-lite-calendar" data-category="%s" data-view="%s" data-initial-date="%s" data-list-months="%d"></div>',
			esc_attr($id),
			esc_attr((string) $atts['category']),
			esc_attr((string) $atts['view']),
			esc_attr((string) $atts['initial_date']),
			$list_months
		);

		if ($this->modal_markup_rendered) {
			return $calendar;
		}

		$this->modal_markup_rendered = true;

		$modal = '';
		$modal .= '<div class="clubcal-lite-modal" aria-hidden="true" style="display:none">';
		$modal .= '<div class="clubcal-lite-modal__backdrop" data-clubcal-lite-modal-close></div>';
		$modal .= '<div class="clubcal-lite-modal__dialog" role="dialog" aria-modal="true" aria-label="Event">';
		$modal .= '<button type="button" class="clubcal-lite-modal__close" data-clubcal-lite-modal-close aria-label="Close">&times;</button>';
		$modal .= '<div class="clubcal-lite-modal__content" data-clubcal-lite-modal-content></div>';
		$modal .= '</div>';
		$modal .= '</div>';

		return $calendar . $modal;
	}

	/**
	 * Shortcode: [club_events_list]
	 * Displays events in a news-style list view with expandable content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function shortcode_club_events_list(array $atts = []): string {
		$atts = shortcode_atts(
			[
				'category'     => '',
				'limit'        => 10,
				'order'        => 'ASC',
				'show_past'    => 'no',
			],
			$atts,
			'club_events_list'
		);

		$this->maybe_enqueue_list_assets();

		$post_status = ['publish', 'future'];
		if (is_user_logged_in() && current_user_can('edit_posts')) {
			$post_status = 'any';
		}

		$args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => $post_status,
			'posts_per_page' => intval($atts['limit']),
			'orderby'        => 'meta_value',
			'meta_key'       => '_clubcal_start',
			'order'          => strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC',
			'meta_query'     => [
				[
					'key'     => '_clubcal_start',
					'compare' => 'EXISTS',
				],
			],
		];

		// Filter by category if specified
		if ($atts['category'] !== '') {
			$args['tax_query'] = [
				[
					'taxonomy' => self::TAX_CATEGORY,
					'field'    => 'slug',
					'terms'    => array_map('trim', explode(',', $atts['category'])),
				],
			];
		}

		// Only show future events by default
		if ($atts['show_past'] !== 'yes') {
			$args['meta_query'][] = [
				'key'     => '_clubcal_start',
				'value'   => current_time('Y-m-d H:i:s'),
				'compare' => '>=',
				'type'    => 'DATETIME',
			];
		}

		$query = new \WP_Query($args);

		if (!$query->have_posts()) {
			return '<p class="clubcal-lite-list__empty">' . esc_html__('No upcoming events.', 'clubcal-lite') . '</p>';
		}

		$html = '<div class="clubcal-lite-list">';

		while ($query->have_posts()) {
			$query->the_post();
			$post_id = get_the_ID();

			$start_meta = (string) get_post_meta($post_id, '_clubcal_start', true);
			$end_meta   = (string) get_post_meta($post_id, '_clubcal_end', true);
			$all_day    = (string) get_post_meta($post_id, '_clubcal_all_day', true);
			$location   = (string) get_post_meta($post_id, '_clubcal_location', true);

			$start_ts = strtotime($start_meta);
			$end_ts = strtotime($end_meta);
			$date_text = '';
			if ($start_ts !== false) {
				$has_end = ($end_meta !== '' && $end_ts !== false);

				if ($all_day === '1') {
					$date_text = wp_date(__('F j, Y', 'clubcal-lite'), $start_ts);
					if ($has_end) {
						$date_text .= ' – ' . wp_date(__('F j, Y', 'clubcal-lite'), $end_ts);
					}
				} else {
					$date_text = wp_date(__('F j, Y \a\t H:i', 'clubcal-lite'), $start_ts);
					if ($has_end) {
						$date_text .= ' – ' . wp_date(__('F j, Y \a\t H:i', 'clubcal-lite'), $end_ts);
					}
				}
			}

			// Get excerpt or truncate content
			$excerpt = get_the_excerpt();
			if (empty($excerpt)) {
				$content = get_the_content();
				$excerpt = $this->safe_truncate_html($content, 100);
			}

			// Get full content for expansion
			$full_content = apply_filters('the_content', get_the_content());

			// Get category color for accent
			$accent_color = '#3788d8';
			$categories = wp_get_post_terms($post_id, self::TAX_CATEGORY);
			if (!empty($categories) && !is_wp_error($categories)) {
				$cat_color = get_term_meta($categories[0]->term_id, 'clubcal_category_color', true);
				if (!empty($cat_color)) {
					$accent_color = $cat_color;
				}
			}

			$html .= '<article class="clubcal-lite-list__item" data-clubcal-list-item>';
			$html .= '<div class="clubcal-lite-list__header" data-clubcal-list-toggle style="border-left-color: ' . esc_attr($accent_color) . ';">';
			$html .= '<div class="clubcal-lite-list__info">';
			$html .= '<h3 class="clubcal-lite-list__title">' . esc_html(get_the_title()) . '</h3>';
			$html .= '<div class="clubcal-lite-list__meta">';
			if ($location !== '') {
				$html .= '<span class="clubcal-lite-list__location">' . esc_html($location) . '</span>';
			}
			$html .= '</div>';
			$html .= '<div class="clubcal-lite-list__excerpt">';
			if ($date_text !== '') {
				$html .= '<span class="clubcal-lite-list__date-inline">' . esc_html($date_text) . '</span> ';
			}
			$html .= wp_kses_post($excerpt);
			$html .= '</div>';
			$html .= '</div>';
			$html .= '<div class="clubcal-lite-list__chevron" aria-hidden="true">';
			$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '<div class="clubcal-lite-list__content" data-clubcal-list-content aria-hidden="true">';
			$html .= '<div class="clubcal-lite-list__content-inner">' . wp_kses_post($full_content) . '</div>';
			$html .= '<p class="clubcal-lite-list__link"><a href="' . esc_url(get_permalink()) . '">' . esc_html__('Open event page', 'clubcal-lite') . '</a></p>';
			$html .= '</div>';
			$html .= '</article>';
		}

		wp_reset_postdata();

		$html .= '</div>';

		return $html;
	}

	/**
	 * Safely truncate HTML content without breaking tags.
	 *
	 * @param string $html    The HTML content to truncate.
	 * @param int    $length  Maximum character length (default 100).
	 * @param string $suffix  Suffix to append if truncated (default '...').
	 * @return string Truncated HTML with closed tags.
	 */
	private function safe_truncate_html(string $html, int $length = 100, string $suffix = '...'): string {
		// Strip HTML tags first to get plain text length
		$plain_text = wp_strip_all_tags($html);
		$plain_text = html_entity_decode($plain_text, ENT_QUOTES, 'UTF-8');
		$plain_text = trim($plain_text);

		// If content is short enough, return sanitized HTML
		if (mb_strlen($plain_text) <= $length) {
			return wp_kses_post($html);
		}

		// Truncate plain text and add suffix
		$truncated = mb_substr($plain_text, 0, $length);

		// Try to break at a word boundary
		$last_space = mb_strrpos($truncated, ' ');
		if ($last_space !== false && $last_space > $length * 0.7) {
			$truncated = mb_substr($truncated, 0, $last_space);
		}

		return esc_html($truncated) . $suffix;
	}

	/**
	 * Enqueue assets for the events list shortcode.
	 */
	private function maybe_enqueue_list_assets(): void {
		// Enqueue base stylesheet
		wp_enqueue_style(
			'clubcal-lite',
			plugins_url('style.css', __FILE__),
			[],
			self::VERSION
		);

		// Inline JavaScript for list expand/collapse
		$inline_js = "(function(){\n"
			. "  function initListItems(){\n"
			. "    var items = document.querySelectorAll('[data-clubcal-list-item]');\n"
			. "    items.forEach(function(item){\n"
			. "      var toggle = item.querySelector('[data-clubcal-list-toggle]');\n"
			. "      var content = item.querySelector('[data-clubcal-list-content]');\n"
			. "      if(!toggle || !content){ return; }\n"
			. "      toggle.addEventListener('click', function(e){\n"
			. "        e.preventDefault();\n"
			. "        var isExpanded = item.classList.contains('is-expanded');\n"
			. "        item.classList.toggle('is-expanded');\n"
			. "        content.setAttribute('aria-hidden', isExpanded ? 'true' : 'false');\n"
			. "      });\n"
			. "    });\n"
			. "  }\n"
			. "  if(document.readyState === 'loading'){\n"
			. "    document.addEventListener('DOMContentLoaded', initListItems);\n"
			. "  } else {\n"
			. "    initListItems();\n"
			. "  }\n"
			. "})();";

		wp_add_inline_script('clubcal-lite-list', $inline_js);

		// Register a dummy script to attach the inline JS
		wp_register_script('clubcal-lite-list', '', [], self::VERSION, true);
		wp_enqueue_script('clubcal-lite-list');
	}

	public function enqueue_frontend_assets(): void {
		$this->maybe_enqueue_frontend_assets();
	}

	private function maybe_enqueue_frontend_assets(): void {
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
			plugins_url('style.css', __FILE__),
			[],
			self::VERSION
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

		$nonce_events = wp_create_nonce('clubcal_lite_events');
		$nonce_details = wp_create_nonce('clubcal_lite_event_details');
		$ajax_url = admin_url('admin-ajax.php');
		$action_events = self::AJAX_ACTION_EVENTS;
		$action_details = self::AJAX_ACTION_EVENT_DETAILS;

		$inline = "(function(){\n"
			. "  if(window.FullCalendar){\n"
			. "    window.FullCalendar.globalLocales = window.FullCalendar.globalLocales || [];\n"
			. "    window.FullCalendar.globalLocales.push({\n"
			. "      code: 'sv',\n"
			. "      week: { dow: 1, doy: 4 },\n"
			. "      buttonText: { prev: 'Föregående', next: 'Nästa', today: 'Idag', month: 'Månad', week: 'Vecka', day: 'Dag', list: 'Lista' },\n"
			. "      weekText: 'V',\n"
			. "      allDayText: 'Hela dagen',\n"
			. "      moreLinkText: function(n){ return '+' + n + ' till'; },\n"
			. "      noEventsText: 'Inga händelser att visa'\n"
			. "    });\n"
			. "  }\n"
			. "  function qs(sel, root){ return (root||document).querySelector(sel); }\n"
			. "  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }\n"
			. "  function getModal(){ return qs('.clubcal-lite-modal'); }\n"
			. "  function renderLegend(calEl, events){\n"
			. "    try {\n"
			. "      if(!calEl){ return; }\n"
			. "      var table = qs('.fc-list-table', calEl);\n"
			. "      if(!table){ return; }\n"
			. "      var existing = qs('[data-clubcal-legend]', calEl);\n"
			. "      if(existing && existing.parentNode){ existing.parentNode.removeChild(existing); }\n"
			. "      var map = {};\n"
			. "      var isUncatMap = {};\n"
			. "      (events||[]).forEach(function(ev){\n"
			. "        var name = ev && ev.extendedProps ? ev.extendedProps.categoryName : '';\n"
			. "        var color = ev && ev.extendedProps ? ev.extendedProps.dotColor : '';\n"
			. "        var isUncat = !!(ev && ev.extendedProps && ev.extendedProps.isUncategorized);\n"
			. "        if(!name || !color){ return; }\n"
			. "        name = String(name).trim();\n"
			. "        color = String(color).trim();\n"
			. "        if(!name || !color){ return; }\n"
			. "        if(!map[name]){ map[name] = color; isUncatMap[name] = isUncat; }\n"
			. "      });\n"
			. "      var names = Object.keys(map);\n"
			. "      if(!names.length){ return; }\n"
			. "      names.sort(function(a,b){\n"
			. "        var au = !!isUncatMap[a];\n"
			. "        var bu = !!isUncatMap[b];\n"
			. "        if(au !== bu){ return au ? 1 : -1; }\n"
			. "        return a.localeCompare(b);\n"
			. "      });\n"
			. "      var wrap = document.createElement('div');\n"
			. "      wrap.setAttribute('data-clubcal-legend', '1');\n"
			. "      wrap.className = 'clubcal-lite-legend';\n"
			. "      names.forEach(function(name){\n"
			. "        var item = document.createElement('span');\n"
			. "        item.className = 'clubcal-lite-legend__item';\n"
			. "        var dot = document.createElement('span');\n"
			. "        dot.className = 'clubcal-lite-legend__dot';\n"
			. "        dot.style.borderColor = map[name];\n"
			. "        dot.title = name;\n"
			. "        var label = document.createElement('span');\n"
			. "        label.className = 'clubcal-lite-legend__label';\n"
			. "        label.textContent = name;\n"
			. "        item.appendChild(dot);\n"
			. "        item.appendChild(label);\n"
			. "        wrap.appendChild(item);\n"
			. "      });\n"
			. "      table.parentNode.insertBefore(wrap, table);\n"
			. "    } catch(e) {}\n"
			. "  }\n"
			. "  function normalizeListView(calEl){\n"
			. "    try {\n"
			. "      if(!calEl){ return; }\n"
			. "      // Always hide the separate date header rows in list view\n"
			. "      qsa('tr.fc-list-day', calEl).forEach(function(dayRow){ dayRow.style.display = 'none'; });\n"
			. "    } catch(e) {}\n"
			. "  }\n"
			. "  function decorateListEventRow(info){\n"
			. "    try {\n"
			. "      if(!info || !info.el || !info.event){ return; }\n"
			. "      var tr = info.el.closest('tr');\n"
			. "      if(!tr || !tr.classList.contains('fc-list-event')){ return; }\n"
			. "      if(!tr.closest('.fc-list')){ return; }\n"
			. "      qsa('[data-clubcal-list-excerpt]', tr).forEach(function(el){ el.parentNode && el.parentNode.removeChild(el); });\n"
			. "      // Rebuild time cell as: Date, then timeText (e.g. 'Hela dagen')\n"
			. "      var timeCell = qs('td.fc-list-event-time', tr);\n"
			. "      if(timeCell){\n"
			. "        var startStr = info.event.startStr || '';\n"
			. "        var dateStr = startStr.split('T')[0];\n"
			. "        var restText = (info.timeText || '').trim();\n"
			. "        timeCell.textContent = '';\n"
			. "        var strong = document.createElement('span');\n"
			. "        strong.style.fontWeight = '600';\n"
			. "        strong.textContent = dateStr;\n"
			. "        timeCell.appendChild(strong);\n"
			. "        if(restText){ timeCell.appendChild(document.createTextNode(' ' + restText)); }\n"
			. "      }\n"
			. "      // Append excerpt under title\n"
			. "      var titleCell = qs('td.fc-list-event-title', tr) || qs('.fc-list-event-title', tr);\n"
			. "      if(titleCell){\n"
			. "        var titleLink = qs('a', titleCell);\n"
			. "        if(titleLink){ titleLink.classList.add('clubcal-lite-fc-title'); }\n"
			. "        var catName = (info.event.extendedProps && info.event.extendedProps.categoryName) ? String(info.event.extendedProps.categoryName) : '';\n"
			. "        var dotColor = (info.event.extendedProps && info.event.extendedProps.dotColor) ? String(info.event.extendedProps.dotColor) : '';\n"
			. "        catName = catName.trim();\n"
			. "        dotColor = dotColor.trim();\n"
			. "        var dotEl = qs('.fc-list-event-dot', tr);\n"
			. "        var graphicCell = qs('td.fc-list-event-graphic', tr);\n"
			. "        if(dotEl && dotColor){\n"
			. "          dotEl.style.display = '';\n"
			. "          dotEl.style.borderColor = dotColor;\n"
			. "          dotEl.style.borderTopColor = dotColor;\n"
			. "          dotEl.style.borderRightColor = dotColor;\n"
			. "          dotEl.style.borderBottomColor = dotColor;\n"
			. "          dotEl.style.borderLeftColor = dotColor;\n"
			. "          if(graphicCell){ graphicCell.style.padding = ''; }\n"
			. "        } else if(dotEl){\n"
			. "          // No category selected -> no dot\n"
			. "          dotEl.style.display = 'none';\n"
			. "        }\n"
			. "        if(dotEl && catName){ dotEl.title = catName; }\n"
			. "        var excerpt = (info.event.extendedProps && info.event.extendedProps.excerpt) ? String(info.event.extendedProps.excerpt) : '';\n"
			. "        excerpt = excerpt.trim();\n"
			. "        if(excerpt){\n"
			. "          var ex = document.createElement('div');\n"
			. "          ex.setAttribute('data-clubcal-list-excerpt', '1');\n"
			. "          ex.className = 'clubcal-lite-fc-excerpt';\n"
			. "          ex.style.opacity = '0.85';\n"
			. "          ex.style.marginTop = '2px';\n"
			. "          ex.textContent = excerpt;\n"
			. "          titleCell.appendChild(ex);\n"
			. "        }\n"
			. "      }\n"
			. "    } catch(e) {}\n"
			. "  }\n"
			. "  var hoverCardEl = null;\n"
			. "  function removeHoverCard(immediate){\n"
			. "    if(!hoverCardEl){ return; }\n"
			. "    var el = hoverCardEl;\n"
			. "    hoverCardEl = null;\n"
			. "    if(immediate){\n"
			. "      if(el.parentNode){ el.parentNode.removeChild(el); }\n"
			. "      return;\n"
			. "    }\n"
			. "    el.classList.remove('is-visible');\n"
			. "    el.classList.add('is-leaving');\n"
			. "    window.setTimeout(function(){\n"
			. "      if(el.parentNode){ el.parentNode.removeChild(el); }\n"
			. "    }, 180);\n"
			. "  }\n"
			. "  function showHoverCard(sourceEl){\n"
			. "    removeHoverCard(true);\n"
			. "    if(!sourceEl || !sourceEl.getBoundingClientRect){ return; }\n"
			. "    var rect = sourceEl.getBoundingClientRect();\n"
			. "    hoverCardEl = sourceEl.cloneNode(true);\n"
			. "    hoverCardEl.classList.add('clubcal-lite-hovercard');\n"
			. "    hoverCardEl.style.position = 'fixed';\n"
			. "    hoverCardEl.style.left = rect.left + 'px';\n"
			. "    hoverCardEl.style.top = rect.top + 'px';\n"
			. "    hoverCardEl.style.width = rect.width + 'px';\n"
			. "    hoverCardEl.style.zIndex = '100000';\n"
			. "    // Preserve the dot's border-color from the original element\n"
			. "    var origDot = sourceEl.querySelector('.fc-list-event-dot');\n"
			. "    var cloneDot = hoverCardEl.querySelector('.fc-list-event-dot');\n"
			. "    if(origDot && cloneDot){\n"
			. "      var dotColor = origDot.style.borderColor || window.getComputedStyle(origDot).borderColor;\n"
			. "      if(dotColor){ cloneDot.style.borderColor = dotColor; }\n"
			. "    }\n"
			. "    document.body.appendChild(hoverCardEl);\n"
			. "    window.requestAnimationFrame(function(){\n"
			. "      if(hoverCardEl){ hoverCardEl.classList.add('is-visible'); }\n"
			. "    });\n"
			. "  }\n"
			. "  function closeModal(){\n"
			. "    var modal = getModal();\n"
			. "    if(!modal){ return; }\n"
			. "    modal.style.display = 'none';\n"
			. "    modal.setAttribute('aria-hidden', 'true');\n"
			. "    var content = qs('[data-clubcal-lite-modal-content]', modal);\n"
			. "    if(content){ content.innerHTML = ''; }\n"
			. "  }\n"
			. "  function openModal(html){\n"
			. "    var modal = getModal();\n"
			. "    if(!modal){ return; }\n"
			. "    var content = qs('[data-clubcal-lite-modal-content]', modal);\n"
			. "    if(content){ content.innerHTML = html; }\n"
			. "    modal.style.display = 'block';\n"
			. "    modal.setAttribute('aria-hidden', 'false');\n"
			. "  }\n"
			. "  function initOne(el){\n"
			. "    if(!window.FullCalendar){ return; }\n"
			. "    var category = el.getAttribute('data-category') || '';\n"
			. "    var initialView = el.getAttribute('data-view') || 'dayGridMonth';\n"
			. "    var initialDate = el.getAttribute('data-initial-date') || '';\n"
			. "    var listMonths = parseInt(el.getAttribute('data-list-months') || '3', 10);\n"
			. "    if(isNaN(listMonths) || listMonths < 1){ listMonths = 3; }\n"
			. "    if(listMonths > 12){ listMonths = 12; }\n"
			. "    var listDuration = { months: listMonths };\n"
			. "    var listButtonText = listMonths === 1 ? '1 månad' : listMonths + ' månader';\n"
			. "    var calendar = new FullCalendar.Calendar(el, {\n"
			. "      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listRange' },\n"
			. "      locale: 'sv',\n"
			. "      firstDay: 1,\n"
			. "      datesSet: function(){ window.setTimeout(function(){ normalizeListView(el); }, 0); },\n"
			. "      eventsSet: function(events){\n"
			. "        window.setTimeout(function(){ normalizeListView(el); }, 0);\n"
			. "        window.setTimeout(function(){ renderLegend(el, events); }, 0);\n"
			. "      },\n"
			. "      views: {\n"
			. "        listRange: {\n"
			. "          type: 'list',\n"
			. "          duration: listDuration,\n"
			. "          buttonText: listButtonText\n"
			. "        }\n"
			. "      },\n"
			. "      buttonText: { today: 'Idag', month: 'Månad', week: 'Vecka', day: 'Dag', list: 'Lista', dayGridMonth: 'Månad' },\n"
			. "      height: 'auto',\n"
			. "      expandRows: true,\n"
			. "      initialView: initialView === 'listWeek' ? 'listRange' : initialView,\n"
			. "      initialDate: initialDate || undefined,\n"
			. "      eventMouseEnter: function(info){\n"
			. "        try {\n"
			. "          if(!info || !info.el){ return; }\n"
			. "          showHoverCard(info.el);\n"
			. "        } catch(e) {}\n"
			. "      },\n"
			. "      eventMouseLeave: function(){\n"
			. "        removeHoverCard(false);\n"
			. "      },\n"
			. "      eventDidMount: function(info){\n"
			. "        window.setTimeout(function(){ normalizeListView(el); }, 0);\n"
			. "        window.setTimeout(function(){ decorateListEventRow(info); }, 0);\n"
			. "      },\n"
			. "      eventClick: function(info){\n"
			. "        if(info && info.jsEvent){ info.jsEvent.preventDefault(); }\n"
			. "        var eventId = info && info.event ? info.event.id : null;\n"
			. "        if(!eventId){ return; }\n"
			. "        openModal('<p>Loading...</p>');\n"
			. "        var url = new URL('{$ajax_url}');\n"
			. "        url.searchParams.set('action', '{$action_details}');\n"
			. "        url.searchParams.set('_ajax_nonce', '{$nonce_details}');\n"
			. "        url.searchParams.set('event_id', eventId);\n"
			. "        fetch(url.toString(), { credentials: 'same-origin' })\n"
			. "          .then(function(r){ return r.json(); })\n"
			. "          .then(function(data){\n"
			. "            if(data && data.success && data.data && data.data.html){ openModal(data.data.html); }\n"
			. "            else { openModal('<p>Could not load event.</p>'); }\n"
			. "          })\n"
			. "          .catch(function(){ openModal('<p>Could not load event.</p>'); });\n"
			. "      },\n"
			. "      events: function(info, success, failure){\n"
			. "        var url = new URL('{$ajax_url}');\n"
			. "        url.searchParams.set('action', '{$action_events}');\n"
			. "        url.searchParams.set('_ajax_nonce', '{$nonce_events}');\n"
			. "        url.searchParams.set('start', info.startStr);\n"
			. "        url.searchParams.set('end', info.endStr);\n"
			. "        if(category){ url.searchParams.set('category', category); }\n"
			. "        fetch(url.toString(), { credentials: 'same-origin' })\n"
			. "          .then(function(r){ return r.json(); })\n"
			. "          .then(function(data){\n"
			. "            if(data && data.success && Array.isArray(data.data)){ success(data.data); }\n"
			. "            else { failure(data && data.data ? data.data : 'Invalid response'); }\n"
			. "          })\n"
			. "          .catch(function(err){ failure(err); });\n"
			. "      }\n"
			. "    });\n"
			. "    calendar.render();\n"
			. "  }\n"
			. "  function initModal(){\n"
			. "    var modal = getModal();\n"
			. "    if(!modal){ return; }\n"
			. "    qsa('[data-clubcal-lite-modal-close]', modal).forEach(function(btn){\n"
			. "      btn.addEventListener('click', function(){ closeModal(); });\n"
			. "    });\n"
			. "    document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ closeModal(); } });\n"
			. "  }\n"
			. "  initModal();\n"
			. "  document.querySelectorAll('.clubcal-lite-calendar').forEach(initOne);\n"
			. "})();";

		wp_add_inline_script('fullcalendar', $inline, 'after');
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
			'post_type' => self::POST_TYPE,
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
					'taxonomy' => self::TAX_CATEGORY,
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

			// If no end date is set, treat as all-day event (user's preference)
			$is_all_day = ($all_day_meta === '1') || !$has_end_date;

			// For range comparison: use end date if available, otherwise end of start day
			$event_end_ts = $has_end_date ? $end_meta_ts : strtotime(wp_date('Y-m-d', $start_meta_ts) . ' 23:59:59');

			// Show event if it overlaps with the visible range
			if ($start_meta_ts > $end_ts || $event_end_ts < $start_ts) {
				continue;
			}

			$location = trim((string) get_post_meta($post->ID, '_clubcal_location', true));

			// Format start date for FullCalendar
			$start_iso = $this->format_datetime_for_iso($start_meta);
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

			// Add end date only if explicitly set
			if ($has_end_date) {
				$end_iso = $this->format_datetime_for_iso($end_meta);
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

			$cat = $this->get_category_display_data($post->ID);
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

		if ($post->post_type !== self::POST_TYPE) {
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
			if ($all_day === '1') {
				$date_text = wp_date('Y-m-d', $start_ts);
			} else {
				$date_text = wp_date('Y-m-d H:i', $start_ts);
			}

			if ($end_ts !== false) {
				$date_text .= ' – ';
				$date_text .= ($all_day === '1') ? wp_date('Y-m-d', $end_ts) : wp_date('Y-m-d H:i', $end_ts);
			}
		}

		$title = get_the_title($post);
		$permalink = get_permalink($post);
		$content_html = apply_filters('the_content', $post->post_content);

		$cat = $this->get_category_display_data($post->ID);
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

$clubcal_lite = new ClubCal_Lite();
$clubcal_lite->init();

register_activation_hook(__FILE__, [$clubcal_lite, 'activate']);
register_deactivation_hook(__FILE__, [$clubcal_lite, 'deactivate']);
