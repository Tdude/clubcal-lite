<?php
/**
 * Plugin Name: ClubCal Lite
 * Description: Lightweight club calendar using a custom post type. Xtremely lightweight, 200kb including FullCalendar with AJAX events loading, modal event details and minimal styling.
 * Version: 0.1.0
 * Author: Tibor Berki <https://github.com/Tdude>
 * Text Domain: clubcal-lite
 */

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite {
	public const VERSION = '0.1.0';
	public const POST_TYPE = 'club_event';
	public const TAX_CATEGORY = 'event_category';
	public const TAX_TAG = 'event_tag';
	private const AJAX_ACTION_EVENTS = 'clubcal_lite_events';
	private const AJAX_ACTION_EVENT_DETAILS = 'clubcal_lite_event_details';
	private bool $frontend_assets_enqueued = false;
	private bool $modal_markup_rendered = false;

	public function init(): void {
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
			'menu_name' => __('Events', 'clubcal-lite'),
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
		echo '<label for="clubcal_lite_end"><strong>' . esc_html__('End date/time', 'clubcal-lite') . '</strong></label><br />';
		echo '<input type="datetime-local" id="clubcal_lite_end" name="clubcal_lite_end" value="' . esc_attr($end) . '" style="width: 100%; max-width: 320px;" />';
		echo '</p>';

		echo '<p>';
		echo '<label for="clubcal_lite_all_day">';
		echo '<input type="checkbox" id="clubcal_lite_all_day" name="clubcal_lite_all_day" value="1" ' . $all_day_checked . ' /> ';
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

		$timestamp = strtotime($value);
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

	public function register_shortcodes(): void {
		add_shortcode('club_calendar', [$this, 'shortcode_club_calendar']);
	}

	public function shortcode_club_calendar(array $atts = []): string {
		$this->maybe_enqueue_frontend_assets();

		$atts = shortcode_atts(
			[
				'category' => '',
				'view' => 'dayGridMonth',
				'initial_date' => '',
			],
			$atts,
			'club_calendar'
		);

		$id = 'clubcal-lite-calendar-' . wp_generate_uuid4();

		$calendar = sprintf(
			'<div id="%s" class="clubcal-lite-calendar" data-category="%s" data-view="%s" data-initial-date="%s"></div>',
			esc_attr($id),
			esc_attr((string) $atts['category']),
			esc_attr((string) $atts['view']),
			esc_attr((string) $atts['initial_date'])
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
			. "    var calendar = new FullCalendar.Calendar(el, {\n"
			. "      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },\n"
			. "      locale: 'sv',\n"
			. "      firstDay: 1,\n"
			. "      buttonText: { today: 'Idag', month: 'Månad', week: 'Vecka', day: 'Dag', list: 'Lista', dayGridMonth: 'Månad', listWeek: 'Veckolista' },\n"
			. "      height: 'auto',\n"
			. "      expandRows: true,\n"
			. "      initialView: initialView,\n"
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

		$args = [
			'post_type' => self::POST_TYPE,
			'post_status' => 'publish',
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
			$start_meta = (string) get_post_meta($post->ID, '_clubcal_start', true);
			$start_meta_ts = strtotime($start_meta);
			if ($start_meta_ts === false) {
				continue;
			}

			if ($start_meta_ts < $start_ts || $start_meta_ts > $end_ts) {
				continue;
			}

			$end_meta = (string) get_post_meta($post->ID, '_clubcal_end', true);
			$all_day = (string) get_post_meta($post->ID, '_clubcal_all_day', true);
			$location = (string) get_post_meta($post->ID, '_clubcal_location', true);

			$event = [
				'id' => $post->ID,
				'title' => get_the_title($post),
				'start' => $this->format_datetime_for_iso($start_meta),
				'url' => get_permalink($post),
				'allDay' => ($all_day === '1'),
			];

			$end_iso = $this->format_datetime_for_iso($end_meta);
			if ($end_iso !== '') {
				$event['end'] = $end_iso;
			}

			if ($location !== '') {
				$event['extendedProps'] = ['location' => $location];
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

		if ($post->post_type !== self::POST_TYPE || $post->post_status !== 'publish') {
			wp_send_json_error('Event not available', 404);
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

		$html = '';
		$html .= '<div class="clubcal-lite-event">';
		$html .= '<h3 class="clubcal-lite-event__title">' . esc_html($title) . '</h3>';

		if ($date_text !== '') {
			$html .= '<p class="clubcal-lite-event__datetime">' . esc_html($date_text) . '</p>';
		}

		if ($location !== '') {
			$html .= '<p class="clubcal-lite-event__location">' . esc_html($location) . '</p>';
		}

		$html .= '<div class="clubcal-lite-event__content">' . $content_html . '</div>';
		$html .= '<p class="clubcal-lite-event__link"><a href="' . esc_url($permalink) . '">' . esc_html__('Open event page', 'clubcal-lite') . '</a></p>';
		$html .= '</div>';

		wp_send_json_success(['html' => $html]);
	}
}

$clubcal_lite = new ClubCal_Lite();
$clubcal_lite->init();

register_activation_hook(__FILE__, [$clubcal_lite, 'activate']);
register_deactivation_hook(__FILE__, [$clubcal_lite, 'deactivate']);
