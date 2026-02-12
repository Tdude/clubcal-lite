<?php
/**
 * Payment handling for ClubCal Lite
 * Supports Swish (primary) and Stripe (prepared)
 *
 * @package ClubCal_Lite
 */

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite_Payment {
	public const OPTION_KEY = 'clubcal_lite_payment_settings';
	public const LOG_POST_TYPE = 'club_payment_log';

	private array $settings;

	public function __construct() {
		$this->settings = $this->get_settings();
	}

	public function register(): void {
		add_action('init', [$this, 'register_payment_log_cpt']);
		add_action('admin_menu', [$this, 'register_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);
		
		// Payment log admin columns
		add_filter('manage_' . self::LOG_POST_TYPE . '_posts_columns', [$this, 'payment_log_columns']);
		add_action('manage_' . self::LOG_POST_TYPE . '_posts_custom_column', [$this, 'render_payment_log_column'], 10, 2);
	}

	/**
	 * Get payment settings with defaults
	 */
	public function get_settings(): array {
		$defaults = [
			'enabled'              => false,
			'payment_method'       => 'swish', // swish, stripe, manual
			'swish_number'         => '',      // Merchant Swish number
			'swish_payee_alias'    => '',      // For API integration
			'swish_cert_path'      => '',      // Path to Swish certificate
			'stripe_publishable'   => '',
			'stripe_secret'        => '',
			'currency'             => 'SEK',
			'require_payment'      => false,   // Require payment before confirming
			'mailchimp_api_key'    => '',
			'mailchimp_list_id'    => '',
			'mailchimp_enabled'    => false,
		];

		$saved = get_option(self::OPTION_KEY, []);
		return wp_parse_args($saved, $defaults);
	}

	/**
	 * Register payment log CPT
	 */
	public function register_payment_log_cpt(): void {
		$labels = [
			'name'               => __('Payment Log', 'clubcal-lite'),
			'singular_name'      => __('Payment', 'clubcal-lite'),
			'menu_name'          => __('Payment Log', 'clubcal-lite'),
			'all_items'          => __('Payment Log', 'clubcal-lite'),
			'view_item'          => __('View Payment', 'clubcal-lite'),
			'search_items'       => __('Search Payments', 'clubcal-lite'),
			'not_found'          => __('No payments found.', 'clubcal-lite'),
		];

		$args = [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . ClubCal_Lite::POST_TYPE,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'capabilities'        => [
				'create_posts' => 'do_not_allow', // Disable "Add New"
			],
			'map_meta_cap'        => true,
			'supports'            => ['title'],
			'menu_icon'           => 'dashicons-money-alt',
		];

		register_post_type(self::LOG_POST_TYPE, $args);
	}

	/**
	 * Register settings page under Calendar menu
	 */
	public function register_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . ClubCal_Lite::POST_TYPE,
			__('Payment Settings', 'clubcal-lite'),
			__('Payment Settings', 'clubcal-lite'),
			'manage_options',
			'clubcal-lite-payment',
			[$this, 'render_settings_page']
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings(): void {
		register_setting(
			'clubcal_lite_payment',
			self::OPTION_KEY,
			[
				'sanitize_callback' => [$this, 'sanitize_settings'],
			]
		);
	}

	/**
	 * Sanitize settings
	 */
	public function sanitize_settings(array $input): array {
		$sanitized = [];
		
		$sanitized['enabled'] = !empty($input['enabled']);
		$sanitized['payment_method'] = sanitize_text_field($input['payment_method'] ?? 'swish');
		$sanitized['swish_number'] = sanitize_text_field($input['swish_number'] ?? '');
		$sanitized['swish_payee_alias'] = sanitize_text_field($input['swish_payee_alias'] ?? '');
		$sanitized['swish_cert_path'] = sanitize_text_field($input['swish_cert_path'] ?? '');
		$sanitized['stripe_publishable'] = sanitize_text_field($input['stripe_publishable'] ?? '');
		$sanitized['stripe_secret'] = sanitize_text_field($input['stripe_secret'] ?? '');
		$sanitized['currency'] = sanitize_text_field($input['currency'] ?? 'SEK');
		$sanitized['require_payment'] = !empty($input['require_payment']);
		$sanitized['mailchimp_api_key'] = sanitize_text_field($input['mailchimp_api_key'] ?? '');
		$sanitized['mailchimp_list_id'] = sanitize_text_field($input['mailchimp_list_id'] ?? '');
		$sanitized['mailchimp_enabled'] = !empty($input['mailchimp_enabled']);

		return $sanitized;
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Payment Settings', 'clubcal-lite'); ?></h1>
			
			<form method="post" action="options.php">
				<?php settings_fields('clubcal_lite_payment'); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Enable Payments', 'clubcal-lite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked($settings['enabled']); ?> />
								<?php esc_html_e('Enable payment collection for bookings', 'clubcal-lite'); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e('Payment Method', 'clubcal-lite'); ?></th>
						<td>
							<select name="<?php echo esc_attr(self::OPTION_KEY); ?>[payment_method]">
								<option value="swish" <?php selected($settings['payment_method'], 'swish'); ?>>Swish</option>
								<option value="stripe" <?php selected($settings['payment_method'], 'stripe'); ?>>Stripe</option>
								<option value="manual" <?php selected($settings['payment_method'], 'manual'); ?>><?php esc_html_e('Manual (pay at venue)', 'clubcal-lite'); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e('Require Payment', 'clubcal-lite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[require_payment]" value="1" <?php checked($settings['require_payment']); ?> />
								<?php esc_html_e('Require payment confirmation before booking is confirmed', 'clubcal-lite'); ?>
							</label>
							<p class="description"><?php esc_html_e('If unchecked, bookings are confirmed immediately and payment is tracked separately.', 'clubcal-lite'); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e('Swish Settings', 'clubcal-lite'); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Swish Number', 'clubcal-lite'); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[swish_number]" value="<?php echo esc_attr($settings['swish_number']); ?>" class="regular-text" placeholder="123 456 78 90" />
							<p class="description"><?php esc_html_e('Your Swish number for receiving payments. Displayed to customers.', 'clubcal-lite'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Swish Payee Alias', 'clubcal-lite'); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[swish_payee_alias]" value="<?php echo esc_attr($settings['swish_payee_alias']); ?>" class="regular-text" placeholder="1234567890" />
							<p class="description"><?php esc_html_e('For Swish Commerce API integration (optional, leave blank for QR/manual flow).', 'clubcal-lite'); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e('Stripe Settings', 'clubcal-lite'); ?> <span style="color: #999; font-weight: normal; font-size: 14px;">(<?php esc_html_e('prepared, not active', 'clubcal-lite'); ?>)</span></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Publishable Key', 'clubcal-lite'); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[stripe_publishable]" value="<?php echo esc_attr($settings['stripe_publishable']); ?>" class="regular-text" placeholder="pk_..." />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Secret Key', 'clubcal-lite'); ?></th>
						<td>
							<input type="password" name="<?php echo esc_attr(self::OPTION_KEY); ?>[stripe_secret]" value="<?php echo esc_attr($settings['stripe_secret']); ?>" class="regular-text" placeholder="sk_..." />
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e('Mailchimp Settings', 'clubcal-lite'); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Enable Mailchimp', 'clubcal-lite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[mailchimp_enabled]" value="1" <?php checked($settings['mailchimp_enabled']); ?> />
								<?php esc_html_e('Send booking confirmations via Mailchimp', 'clubcal-lite'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('API Key', 'clubcal-lite'); ?></th>
						<td>
							<input type="password" name="<?php echo esc_attr(self::OPTION_KEY); ?>[mailchimp_api_key]" value="<?php echo esc_attr($settings['mailchimp_api_key']); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e('Find this in Mailchimp → Account → Extras → API keys', 'clubcal-lite'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Audience/List ID', 'clubcal-lite'); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[mailchimp_list_id]" value="<?php echo esc_attr($settings['mailchimp_list_id']); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e('The audience to add subscribers to (optional).', 'clubcal-lite'); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Log a payment
	 */
	public static function log_payment(array $data): int {
		$title = sprintf(
			'%s - %s - %s',
			$data['booking_id'] ?? 'N/A',
			$data['amount'] ?? '0',
			$data['method'] ?? 'unknown'
		);

		$log_id = wp_insert_post([
			'post_type'   => self::LOG_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $title,
		]);

		if (is_wp_error($log_id)) {
			return 0;
		}

		// Save all data as meta
		update_post_meta($log_id, '_payment_booking_id', absint($data['booking_id'] ?? 0));
		update_post_meta($log_id, '_payment_event_id', absint($data['event_id'] ?? 0));
		update_post_meta($log_id, '_payment_amount', sanitize_text_field($data['amount'] ?? ''));
		update_post_meta($log_id, '_payment_currency', sanitize_text_field($data['currency'] ?? 'SEK'));
		update_post_meta($log_id, '_payment_method', sanitize_text_field($data['method'] ?? ''));
		update_post_meta($log_id, '_payment_status', sanitize_text_field($data['status'] ?? 'pending'));
		update_post_meta($log_id, '_payment_reference', sanitize_text_field($data['reference'] ?? ''));
		update_post_meta($log_id, '_payment_customer_email', sanitize_email($data['email'] ?? ''));
		update_post_meta($log_id, '_payment_customer_name', sanitize_text_field($data['name'] ?? ''));
		update_post_meta($log_id, '_payment_timestamp', current_time('mysql'));
		update_post_meta($log_id, '_payment_notes', sanitize_textarea_field($data['notes'] ?? ''));

		return $log_id;
	}

	/**
	 * Update payment status
	 */
	public static function update_payment_status(int $log_id, string $status, string $notes = ''): bool {
		if (get_post_type($log_id) !== self::LOG_POST_TYPE) {
			return false;
		}

		update_post_meta($log_id, '_payment_status', sanitize_text_field($status));
		
		if ($notes) {
			$existing_notes = get_post_meta($log_id, '_payment_notes', true);
			$new_notes = $existing_notes ? $existing_notes . "\n" . $notes : $notes;
			update_post_meta($log_id, '_payment_notes', sanitize_textarea_field($new_notes));
		}

		update_post_meta($log_id, '_payment_updated', current_time('mysql'));

		return true;
	}

	/**
	 * Get payment for a booking
	 */
	public static function get_booking_payment(int $booking_id): ?array {
		$args = [
			'post_type'      => self::LOG_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => '_payment_booking_id',
					'value' => $booking_id,
					'type'  => 'NUMERIC',
				],
			],
		];

		$query = new WP_Query($args);
		if (!$query->have_posts()) {
			return null;
		}

		$post = $query->posts[0];
		return [
			'id'        => $post->ID,
			'booking_id'=> (int) get_post_meta($post->ID, '_payment_booking_id', true),
			'event_id'  => (int) get_post_meta($post->ID, '_payment_event_id', true),
			'amount'    => get_post_meta($post->ID, '_payment_amount', true),
			'currency'  => get_post_meta($post->ID, '_payment_currency', true),
			'method'    => get_post_meta($post->ID, '_payment_method', true),
			'status'    => get_post_meta($post->ID, '_payment_status', true),
			'reference' => get_post_meta($post->ID, '_payment_reference', true),
			'email'     => get_post_meta($post->ID, '_payment_customer_email', true),
			'name'      => get_post_meta($post->ID, '_payment_customer_name', true),
			'timestamp' => get_post_meta($post->ID, '_payment_timestamp', true),
			'notes'     => get_post_meta($post->ID, '_payment_notes', true),
		];
	}

	/**
	 * Payment log admin columns
	 */
	public function payment_log_columns(array $columns): array {
		return [
			'cb'        => $columns['cb'],
			'title'     => __('Payment', 'clubcal-lite'),
			'amount'    => __('Amount', 'clubcal-lite'),
			'method'    => __('Method', 'clubcal-lite'),
			'status'    => __('Status', 'clubcal-lite'),
			'customer'  => __('Customer', 'clubcal-lite'),
			'event'     => __('Event', 'clubcal-lite'),
			'date'      => __('Date', 'clubcal-lite'),
		];
	}

	/**
	 * Render payment log column
	 */
	public function render_payment_log_column(string $column, int $post_id): void {
		switch ($column) {
			case 'amount':
				$amount = get_post_meta($post_id, '_payment_amount', true);
				$currency = get_post_meta($post_id, '_payment_currency', true) ?: 'SEK';
				echo esc_html($amount ? $amount . ' ' . $currency : '—');
				break;

			case 'method':
				$method = get_post_meta($post_id, '_payment_method', true);
				$methods = [
					'swish'  => 'Swish',
					'stripe' => 'Stripe',
					'manual' => __('Manual', 'clubcal-lite'),
				];
				echo esc_html($methods[$method] ?? ucfirst($method));
				break;

			case 'status':
				$status = get_post_meta($post_id, '_payment_status', true);
				$colors = [
					'pending'   => '#ff9800',
					'completed' => '#2e7d32',
					'failed'    => '#c62828',
					'refunded'  => '#7b1fa2',
				];
				$color = $colors[$status] ?? '#666';
				echo '<span style="color: ' . esc_attr($color) . '; font-weight: 500;">' . esc_html(ucfirst($status)) . '</span>';
				break;

			case 'customer':
				$name = get_post_meta($post_id, '_payment_customer_name', true);
				$email = get_post_meta($post_id, '_payment_customer_email', true);
				echo esc_html($name);
				if ($email) {
					echo '<br><small><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></small>';
				}
				break;

			case 'event':
				$event_id = (int) get_post_meta($post_id, '_payment_event_id', true);
				if ($event_id > 0) {
					$event = get_post($event_id);
					if ($event) {
						echo '<a href="' . esc_url(get_edit_post_link($event_id)) . '">' . esc_html(get_the_title($event_id)) . '</a>';
					} else {
						echo '<span style="color: #999;">' . esc_html__('Deleted', 'clubcal-lite') . '</span>';
					}
				}
				break;
		}
	}

	/**
	 * Generate Swish payment data for QR/deep link
	 */
	public static function generate_swish_payment(int $booking_id, string $amount, string $message = ''): array {
		$settings = (new self())->get_settings();
		$swish_number = preg_replace('/[^0-9]/', '', $settings['swish_number']);

		if (empty($swish_number)) {
			return ['error' => __('Swish number not configured.', 'clubcal-lite')];
		}

		// Generate reference from booking ID
		$reference = 'UPAIF-' . $booking_id;

		// Swish deep link format
		$swish_data = [
			'payee'   => $swish_number,
			'amount'  => $amount,
			'message' => $message ?: $reference,
		];

		// Swish app deep link
		$deep_link = 'swish://payment?' . http_build_query([
			'data' => json_encode([
				'version' => 1,
				'payee'   => ['value' => $swish_number],
				'amount'  => ['value' => (float) $amount],
				'message' => ['value' => $message ?: $reference],
			]),
		]);

		return [
			'swish_number' => $swish_number,
			'amount'       => $amount,
			'reference'    => $reference,
			'message'      => $message ?: $reference,
			'deep_link'    => $deep_link,
			'qr_data'      => 'C' . $swish_number . ';' . $amount . ';' . ($message ?: $reference) . ';0',
		];
	}
}
