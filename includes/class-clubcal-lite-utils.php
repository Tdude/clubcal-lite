<?php

if (!defined('ABSPATH')) {
	exit;
}

final class ClubCal_Lite_Utils {
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

	public function normalize_datetime_for_storage(string $value): string {
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				$timestamp = strtotime($value . ' 00:00:00');
			}
		}

		if ($timestamp === false) {
			return '';
		}

		return wp_date('Y-m-d H:i:s', $timestamp);
	}

	public function format_datetime_for_input(string $stored): string {
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

	public function format_datetime_for_iso(string $stored): string {
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

	public function safe_truncate_html(string $html, int $length = 100, string $suffix = '...'): string {
		$plain_text = wp_strip_all_tags($html);
		$plain_text = html_entity_decode($plain_text, ENT_QUOTES, 'UTF-8');
		$plain_text = trim($plain_text);

		if (mb_strlen($plain_text) <= $length) {
			return wp_kses_post($html);
		}

		$truncated = mb_substr($plain_text, 0, $length);

		$last_space = mb_strrpos($truncated, ' ');
		if ($last_space !== false && $last_space > $length * 0.7) {
			$truncated = mb_substr($truncated, 0, $last_space);
		}

		return esc_html($truncated) . $suffix;
	}

	public function get_category_display_data(int $post_id): array {
		$categories = wp_get_post_terms($post_id, ClubCal_Lite::TAX_CATEGORY);
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
}
