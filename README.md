# ClubCal Lite

ClubCal Lite is a small WordPress calendar plugin for clubs and communities.

It adds an **Events** custom post type and renders your events in a fast, modern calendar powered by **FullCalendar** (loaded only on pages where you use the shortcode).

## Highlights

- **Lightweight**: no Gutenberg blocks required, no jQuery dependency.
- **AJAX events loading**: the calendar fetches only the events it needs.
- **Month + week list views**: switch between a classic grid and a weekly list.
- **Modal event details**: click an event to view details without leaving the page.
- **Theme-friendly styling**: minimal CSS, with dark-mode support.

## Quick start

1. Activate the plugin.
2. Create your first event under **Events** in WP Admin.
3. Add the calendar to any page with:

`[club_calendar]`

## Shortcode options

- `category`: filter by `event_category` slug
- `view`: initial view (default: `dayGridMonth`, alternative: `listWeek`)
- `initial_date`: open the calendar on a specific date (e.g. `2025-12-19`)

Example:

`[club_calendar category="socials" view="listWeek" initial_date="2025-12-19"]`
- If recurring: Generate "occurrence" meta on save, or query smarter (advanced).

This setup gives a professional, smooth calendar (like Google Calendar feel) without the heaviness of big plugins. (e.g., old WP FullCalendar plugin as reference).
