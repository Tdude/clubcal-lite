<?php
/**
 * Plugin Name: ClubCal Lite
 * Description: Lightweight club calendar using a custom post type. Xtremely lightweight, 200kb including FullCalendar with AJAX events loading, modal event details and minimal styling.
 * Version: 1.0
 * Author: Tibor Berki <https://github.com/Tdude>
 * Text Domain: clubcal-lite
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/includes/class-clubcal-lite-utils.php';
require_once __DIR__ . '/includes/class-clubcal-lite-assets.php';
require_once __DIR__ . '/includes/class-clubcal-lite-cpt.php';
require_once __DIR__ . '/includes/class-clubcal-lite-admin.php';
require_once __DIR__ . '/includes/class-clubcal-lite-shortcodes.php';
require_once __DIR__ . '/includes/class-clubcal-lite-ajax.php';

final class ClubCal_Lite {
	public const VERSION = '1.0';
	public const POST_TYPE = 'club_event';
	public const TAX_CATEGORY = 'event_category';
	public const TAX_TAG = 'event_tag';
	public const AJAX_ACTION_EVENTS = 'clubcal_lite_events';
	public const AJAX_ACTION_EVENT_DETAILS = 'clubcal_lite_event_details';

	private string $plugin_file;
	private ClubCal_Lite_Cpt $cpt;
	private ClubCal_Lite_Assets $assets;
	private ClubCal_Lite_Admin $admin;
	private ClubCal_Lite_Shortcodes $shortcodes;
	private ClubCal_Lite_Ajax $ajax;

	public function __construct(string $plugin_file) {
		$this->plugin_file = $plugin_file;

		$utils = new ClubCal_Lite_Utils();
		$this->assets = new ClubCal_Lite_Assets($this);
		$this->cpt = new ClubCal_Lite_Cpt();
		$this->admin = new ClubCal_Lite_Admin($utils);
		$this->shortcodes = new ClubCal_Lite_Shortcodes($this->assets, $utils);
		$this->ajax = new ClubCal_Lite_Ajax($utils);
	}

	public function plugin_file(): string {
		return $this->plugin_file;
	}

	public function init(): void {
		add_action('plugins_loaded', [$this, 'load_textdomain']);
		$this->cpt->register();
		$this->admin->register();
		$this->shortcodes->register();
		$this->ajax->register();
		$this->assets->register();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain('clubcal-lite', false, dirname(plugin_basename($this->plugin_file)) . '/languages');
	}

	public function activate(): void {
		$this->cpt->register_cpt_and_taxonomies();
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}
}

$clubcal_lite = new ClubCal_Lite(__FILE__);
$clubcal_lite->init();

register_activation_hook(__FILE__, [$clubcal_lite, 'activate']);
register_deactivation_hook(__FILE__, [$clubcal_lite, 'deactivate']);
