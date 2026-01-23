(function () {
  function sync(from, to, formatter) {
    if (!from || !to) {
      return;
    }
    from.addEventListener('change', function () {
      var value = from.value || '';
      to.value = formatter(value);
    });
  }

  function toManual(value) {
    // datetime-local: YYYY-MM-DDTHH:MM -> YYYY-MM-DD HH:MM
    return (value || '').replace('T', ' ').slice(0, 16);
  }

  function toPicker(value) {
    // manual: YYYY-MM-DD HH:MM -> YYYY-MM-DDTHH:MM
    return (value || '').trim().replace(' ', 'T').slice(0, 16);
  }

  function init() {
    var startManual = document.getElementById('clubcal_lite_start');
    var endManual = document.getElementById('clubcal_lite_end');
    var startPicker = document.getElementById('clubcal_lite_start_picker');
    var endPicker = document.getElementById('clubcal_lite_end_picker');

    // Manual -> Picker (when typing)
    sync(startManual, startPicker, toPicker);
    sync(endManual, endPicker, toPicker);

    // Picker -> Manual (when selecting)
    sync(startPicker, startManual, toManual);
    sync(endPicker, endManual, toManual);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
