(function () {
  function sync(from, to, formatter) {
    if (!from || !to) {
      return;
    }

    function handler() {
      var value = from.value || '';
      var next = formatter(value);
      if (typeof next !== 'string') {
        return;
      }
      to.value = next;
    }

    from.addEventListener('input', handler);
    from.addEventListener('change', handler);
  }

  function toManual(value) {
    // datetime-local: YYYY-MM-DDTHH:MM -> YYYY-MM-DD HH:MM
    value = (value || '').replace('T', ' ').trim();
    // Keep only up to minutes if seconds exist.
    if (value.length >= 16) {
      value = value.slice(0, 16);
    }
    return value;
  }

  function toPicker(value) {
    // manual: YYYY-MM-DD HH:MM -> YYYY-MM-DDTHH:MM
    value = (value || '').trim();

    // Allow date-only input and default to midnight for the picker.
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      return value + 'T00:00';
    }

    value = value.replace(' ', 'T');
    if (value.length >= 16) {
      value = value.slice(0, 16);
    }
    return value;
  }

  function init() {
    var startManual = document.getElementById('clubcal_lite_start');
    var endManual = document.getElementById('clubcal_lite_end');
    var startPicker = document.getElementById('clubcal_lite_start_picker');
    var endPicker = document.getElementById('clubcal_lite_end_picker');
    var allDay = document.getElementById('clubcal_lite_all_day');

    // Manual -> Picker (when typing)
    sync(startManual, startPicker, toPicker);
    sync(endManual, endPicker, toPicker);

    // Picker -> Manual (when selecting)
    sync(startPicker, startManual, toManual);
    sync(endPicker, endManual, toManual);

    function isDateOnly(v) {
      return /^\d{4}-\d{2}-\d{2}$/.test((v || '').trim());
    }

    function isDateTime(v) {
      return /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test((v || '').trim());
    }

    if (startManual && allDay) {
      startManual.addEventListener('input', function () {
        if (isDateOnly(startManual.value)) {
          allDay.checked = true;
        }
      });
    }

    if (startPicker && allDay) {
      startPicker.addEventListener('change', function () {
        if (isDateTime(startPicker.value)) {
          allDay.checked = false;
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
