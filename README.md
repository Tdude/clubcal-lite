### Club Cal Lite - Recommended Architecture: **Custom Post Type (CPT) with Custom Meta Fields**


#### Core Features to Implement
1. **CPT Registration** (`club_event`)
   - Supports title, editor, thumbnail, excerpt, custom fields.
   - Optional taxonomies: `event_category` (for filtering, e.g., "Meetings", "Socials") and `event_tag`.

2. **Custom Meta Fields** (via meta boxes or ACF if you bundle it lightly—ACF is ~200KB, optional)
   - Start date/time (required, datetime picker).
   - End date/time (optional, for multi-hour/day events).
   - All-day toggle.
   - Location (text or link to venue CPT later).
   - Recurring? Keep simple initially (none, daily/weekly/monthly)—use a repeater if needed, but avoid for v1 to stay lightweight.
   - Use carbon fields or native meta boxes for lightness.

3. **Frontend Display**
   - Use **FullCalendar.io** (v6+)—it's the gold standard: beautiful monthly grid, tooltips, clickable dates, list view option, fully AJAX events loading (no page reloads).
     - Lightweight (~100KB minified), no jQuery dependency anymore.
     - Enqueue only on pages with your shortcode.
   - Views: Default month grid + event list below (or modal/tooltip on day click).
   - Click event → link to single event page (with details).

4. **Shortcode(s)**
   - Main: `[club_calendar category="socials" year="2026" view="month"]`
     - Attributes: post_type (default your CPT), tax_query (category/slug), initial date, etc.
     - Multiple shortcodes per page supported (different IDs/filters).
   - Optional separate: `[club_event_list]` for just the list.

5. **AJAX Handling**
   - FullCalendar fetches events via `events` callback → your custom WP AJAX endpoint.
   - Query: `WP_Query` with `meta_query` for date range (start/end between fetched month), ordered by start date.
   - Return JSON: title, start/end (ISO), url, description excerpt, thumbnail, color by category.
   - Admin AJAX for prev/next month—no full reload.

6. **Single Event Template**
   - Auto-create `single-club_event.php` (or fallback to theme).
   - Display full details, map if location added, back to calendar link.

#### Plugin Structure Outline
```
club-calendar/
├── club-calendar.php (main file: activation, includes)
├── includes/
│   ├── cpt.php (register_post_type, taxonomies)
│   ├── meta-boxes.php (add_meta_box, save_post)
│   ├── shortcode.php (add_shortcode, output container div + enqueue FC)
│   ├── ajax.php (wp_ajax/nopriv handler for events JSON)
│   └── templates/ (optional: calendar.php, single-event.php)
├── assets/
│   ├── js/fullcalendar.min.js (or CDN)
│   ├── css/fullcalendar.min.css
│   └── js/custom.js (init FC with AJAX url, options)
└── readme.txt
```

- Enqueue scripts/styles conditionally (has_shortcode check).
- Use `wp_localize_script` to pass AJAX URL/nonce/settings.
- Settings page (optional v1): default view, colors, date format.

#### Why This is Lightweight & Performant
- No forced bloat (no Gutenberg blocks unless wanted, no extra views).
- FullCalendar is efficient; queries only load visible month events (~20-50 typically).
- Cache queries if needed (transients for JSON).
- Total size <500KB easily.

#### Development Tips
- Start with FullCalendar docs: feed events via function URL.
- Test queries: `meta_key=_event_start`, `meta_compare BETWEEN`, etc.
- For multi-day: FullCalendar handles spanning natively.
- Styling: Minimal CSS overrides to match theme.
- If recurring: Generate "occurrence" meta on save, or query smarter (advanced).

This setup gives a professional, smooth calendar (like Google Calendar feel) without the heaviness of big plugins. (e.g., old WP FullCalendar plugin as reference).
