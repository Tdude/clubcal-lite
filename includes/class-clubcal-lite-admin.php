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
	}
}
