<?php

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite_Admin {
	private ClubCal_Lite_Utils $utils;

	public function __construct(ClubCal_Lite_Utils $utils) {
		$this->utils = $utils;
	}

	public function register(): void {
		add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
		add_action('save_post_' . ClubCal_Lite::POST_TYPE, [$this, 'save_meta_boxes']);
		add_action('admin_menu', [$this, 'register_settings_page']);
	}

	public function register_settings_page(): void {
		add_options_page(
			__( 'Club Calendar', 'clubcal-lite' ),
			__( 'Club Calendar', 'clubcal-lite' ),
			'manage_options',
			'clubcal-lite',
			[$this, 'render_settings_page']
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$readme_excerpt = $this->get_readme_help_excerpt();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Club Calendar', 'clubcal-lite' ); ?></h1>
			<h2 style="margin-top: 18px;"><?php echo esc_html__( 'Shortcode usage', 'clubcal-lite' ); ?></h2>
			<div style="max-width: 980px; background: #fff; border: 1px solid #dcdcde; padding: 18px; border-radius: 8px; line-height: 1.6;">
				<?php if ( $readme_excerpt !== '' ) : ?>
					<pre style="white-space: pre-wrap; margin: 0;"><?php echo esc_html( $readme_excerpt ); ?></pre>
				<?php else : ?>
					<p style="margin-top: 0;"><?php echo esc_html__( 'No shortcode usage information found in the README files.', 'clubcal-lite' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function get_readme_help_excerpt(): string {
		$plugin_root = dirname( __DIR__ );
		$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : (string) get_locale();
		$locale_lower = strtolower( $locale );
		$is_swedish = ( strpos( $locale_lower, 'sv' ) === 0 );

		$preferred = $is_swedish ? $plugin_root . '/README-SVENSKA.md' : $plugin_root . '/README.md';
		$fallback = $is_swedish ? $plugin_root . '/README.md' : $plugin_root . '/README-SVENSKA.md';

		$preferred_content = file_exists( $preferred ) ? (string) file_get_contents( $preferred ) : '';
		$fallback_content = file_exists( $fallback ) ? (string) file_get_contents( $fallback ) : '';

		$sections = array(
			'## Shortcode options',
			'## Calendar Shortcode Updated',
		);

		$excerpt = $this->build_readme_excerpt_from_content( $preferred_content, $sections );
		if ( $excerpt !== '' ) {
			return $excerpt;
		}

		return $this->build_readme_excerpt_from_content( $fallback_content, $sections );
	}

	private function build_readme_excerpt_from_content( string $content, array $sections ): string {
		if ( $content === '' ) {
			return '';
		}

		$out = array();
		foreach ( $sections as $heading ) {
			$section = $this->extract_readme_section( $content, (string) $heading );
			if ( $section !== '' ) {
				$out[] = trim( $section );
			}
		}

		return trim( implode( "\n\n", $out ) );
	}

	private function extract_readme_section( string $content, string $heading ): string {
		$pos = stripos( $content, $heading );
		if ( $pos === false ) {
			return '';
		}

		$start = $pos;
		$next_pos = strpos( $content, "\n## ", $start + strlen( $heading ) );
		$end = $next_pos === false ? strlen( $content ) : $next_pos + 1;

		return substr( $content, $start, $end - $start );
	}

	public function register_meta_boxes(): void {
		add_meta_box(
			'clubcal_lite_event_details',
			__('Event Details', 'clubcal-lite'),
			[$this, 'render_event_details_meta_box'],
			ClubCal_Lite::POST_TYPE,
			'normal',
			'high'
		);

		add_action(ClubCal_Lite::TAX_CATEGORY . '_add_form_fields', [$this, 'render_category_color_field_add']);
		add_action(ClubCal_Lite::TAX_CATEGORY . '_edit_form_fields', [$this, 'render_category_color_field_edit']);
		add_action('created_' . ClubCal_Lite::TAX_CATEGORY, [$this, 'save_category_color']);
		add_action('edited_' . ClubCal_Lite::TAX_CATEGORY, [$this, 'save_category_color']);
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

		$start = $this->utils->format_datetime_for_input((string) get_post_meta($post->ID, '_clubcal_start', true));
		$end = $this->utils->format_datetime_for_input((string) get_post_meta($post->ID, '_clubcal_end', true));
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
		echo '<input type="text" id="clubcal_lite_location" name="clubcal_lite_location" value="' . esc_attr((string) $location) . '" style="width: 100%;" />';
		echo '</p>';

		// Booking fields
		$max_spots = get_post_meta($post->ID, '_clubcal_max_spots', true);
		$price = get_post_meta($post->ID, '_clubcal_price', true);
		$booking_enabled = get_post_meta($post->ID, '_clubcal_booking_enabled', true);

		echo '<hr style="margin: 20px 0;" />';
		echo '<h4 style="margin-bottom: 10px;">' . esc_html__('Booking Settings', 'clubcal-lite') . '</h4>';

		echo '<p>';
		echo '<label for="clubcal_lite_booking_enabled">';
		echo '<input type="checkbox" id="clubcal_lite_booking_enabled" name="clubcal_lite_booking_enabled" value="1" ' . checked($booking_enabled, '1', false) . ' /> ';
		echo esc_html__('Enable booking for this event', 'clubcal-lite');
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label for="clubcal_lite_max_spots"><strong>' . esc_html__('Max spots', 'clubcal-lite') . '</strong> <span style="font-weight: normal; color: #666;">(' . esc_html__('0 = unlimited', 'clubcal-lite') . ')</span></label><br />';
		echo '<input type="number" id="clubcal_lite_max_spots" name="clubcal_lite_max_spots" value="' . esc_attr((string) $max_spots) . '" min="0" style="width: 100px;" />';
		echo '</p>';

		echo '<p>';
		echo '<label for="clubcal_lite_price"><strong>' . esc_html__('Price', 'clubcal-lite') . '</strong> <span style="font-weight: normal; color: #666;">(' . esc_html__('e.g. 150 kr', 'clubcal-lite') . ')</span></label><br />';
		echo '<input type="text" id="clubcal_lite_price" name="clubcal_lite_price" value="' . esc_attr((string) $price) . '" style="width: 150px;" />';
		echo '</p>';

		// Show current bookings count
		if (class_exists('ClubCal_Lite_Booking') && $post->post_status !== 'auto-draft') {
			$booking_count = ClubCal_Lite_Booking::get_booking_count($post->ID);
			$spots_remaining = ClubCal_Lite_Booking::get_spots_remaining($post->ID);
			
			echo '<p style="background: #f0f0f1; padding: 10px; border-radius: 4px;">';
			echo '<strong>' . esc_html__('Current bookings:', 'clubcal-lite') . '</strong> ' . esc_html($booking_count);
			if ($spots_remaining !== null) {
				echo ' / ' . esc_html($max_spots) . ' (' . esc_html($spots_remaining) . ' ' . esc_html__('spots left', 'clubcal-lite') . ')';
			}
			echo '</p>';
		}
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

		$start = $this->utils->normalize_datetime_for_storage($start_raw);
		$end = $this->utils->normalize_datetime_for_storage($end_raw);

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

		// Save booking fields
		$booking_enabled = isset($_POST['clubcal_lite_booking_enabled']) ? '1' : '0';
		$max_spots = isset($_POST['clubcal_lite_max_spots']) ? absint($_POST['clubcal_lite_max_spots']) : 0;
		$price = isset($_POST['clubcal_lite_price']) ? sanitize_text_field(wp_unslash($_POST['clubcal_lite_price'])) : '';

		update_post_meta($post_id, '_clubcal_booking_enabled', $booking_enabled);
		update_post_meta($post_id, '_clubcal_max_spots', $max_spots);

		if ($price !== '') {
			update_post_meta($post_id, '_clubcal_price', $price);
		} else {
			delete_post_meta($post_id, '_clubcal_price');
		}
	}
}
