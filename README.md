# ClubCal Lite - Absolutely No Bloat, No SPAM, No Phone Home
ClubCal Lite is a small WordPress calendar plugin for clubs and communities. You can do whatever you want with it.

It adds an **Events** custom post type and renders your events in a fast, modern calendar powered by **FullCalendar** (loaded only on pages where you use the shortcode).

## Highlights

- **Lightweight**: no Gutenberg blocks for Wordpress required, no jQuery dependency.
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

- `Add Event categories and use as you see fit.`
- `You can add as many calendars as you wish in your posts.`


## Quick Start for Swedish, translate to any language and contribute

1. Upload all files to your WordPress installation:
   - `clubcal-lite.php` → `/wp-content/plugins/clubcal-lite/`
   - `languages/` folder → `/wp-content/plugins/clubcal-lite/languages/`

2. Set WordPress to Swedish:
   - Go to **Settings → General**
   - Set "Site Language" to **Svenska** (Swedish)
   - Save changes

3. The plugin will automatically display in Swedish!

## File Structure

```
clubcal-lite/
├── clubcal-lite.php              (main plugin file)
├── README.md                      (this file)
├── README-SVENSKA.md              (Swedish documentation)
└── languages/
    ├── clubcal-lite.pot           (translation template)
    ├── clubcal-lite-sv_SE.po      (Swedish translation - editable)
    ├── clubcal-lite-sv_SE.mo      (Swedish translation - compiled)
    └── compile_po.py              (utility to compile .po to .mo)
```

## What's Translated

All admin interface text is translatable:
- Post type labels ("Events", "Add New Event", etc.)
- Meta box labels ("Event Details", "Start date/time", etc.)
- Taxonomy labels ("Event Categories", "Event Tags")
- Frontend strings ("Open event page")

The calendar interface (FullCalendar) is already configured for Swedish locale with:
- Swedish month/day names
- Swedish button labels ("Idag", "Månad", "Veckolista")
- Swedish week settings (Monday first)

## Adding More Languages

To create a new translation:

1. Copy `languages/clubcal-lite-sv_SE.po` and rename it:
   - Danish: `clubcal-lite-da_DK.po`
   - Norwegian: `clubcal-lite-nb_NO.po`
   - Finnish: `clubcal-lite-fi.po`
   - German: `clubcal-lite-de_DE.po`

2. Translate all `msgstr` values (leave `msgid` unchanged)

3. Compile to `.mo` format:
   ```bash
   # Using msgfmt (if installed)
   msgfmt clubcal-lite-LOCALE.po -o clubcal-lite-LOCALE.mo
   
   # Or using the included Python script
   python3 compile_po.py
   ```

4. Upload both `.po` and `.mo` files to the `languages/` folder

5. Set WordPress to use your language

## Editing Translations

### Using Poedit (Recommended)

1. Download [Poedit](https://poedit.net/)
2. Open the `.po` file
3. Edit translations
4. Save (automatically compiles to `.mo`)

### Manual Editing

1. Open `.po` file in text editor
2. Find the string to translate:
   ```
   msgid "Event"
   msgstr "Your translation"
   ```
3. Edit the `msgstr` value
4. Compile to `.mo` (see above)
5. Upload new `.mo` file
6. Clear WordPress cache

## Translation Template

Use `clubcal-lite.pot` as a template for new translations. It contains all translatable strings with empty `msgstr` values.

## Troubleshooting

### Translations not showing

1. **Check WordPress language setting** (Settings → General)
2. **Check file names** (must match locale exactly, e.g., `sv_SE` not just `sv`)
3. **Check file path** (`/wp-content/plugins/clubcal-lite/languages/`)
4. **Check file permissions** (`.mo` files should be readable: chmod 644)
5. **Clear cache** (WordPress object cache and any caching plugins)
6. **Try incognito mode** to rule out browser caching

### Some strings still in English

The calendar view uses FullCalendar's Swedish locale, configured in the plugin code. If you see English:
- Clear cache
- Check browser console for JavaScript errors
- Ensure the plugin file is the updated version with `load_textdomain()`

## Plugin Updates

When updating the plugin:

1. **Backup your translations** (`.po` and `.mo` files)
2. Update `clubcal-lite.php`
3. Restore your translation files
4. Check if new strings were added (compare with new `.pot` file)
5. Update translations if needed
6. Recompile `.mo` files

## Contributing Translations

If you create a translation for a new language, please consider sharing it:
- Open a pull request on GitHub
- Contact the plugin author (github.com/Tdude)
- Share in WordPress translation forums

## Calendar Locale Configuration

The calendar already includes Swedish locale settings in the JavaScript:
```javascript
locale: 'sv',
firstDay: 1,  // Monday
buttonText: { 
  today: 'Idag', 
  month: 'Månad', 
  week: 'Vecka', 
  day: 'Dag', 
  list: 'Lista' 
}
```

For other languages, you may need to add FullCalendar locale files or modify the plugin's JavaScript configuration.

## Support

For questions or issues:
- Check the Swedish README (`README-SVENSKA.md`) for detailed instructions
- Open an issue on GitHub
- Contact the plugin author (github.com/Tdude)

---

**Current Version:** 0.1.2  
**Text Domain:** clubcal-lite  
**Supported Languages:** Swedish (sv_SE)
