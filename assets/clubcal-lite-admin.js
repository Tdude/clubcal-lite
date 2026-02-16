(function () {
  function isDateOnly(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test((value || '').trim());
  }

  function toDateOnly(value) {
    value = (value || '').trim();
    if (value.length >= 10) {
      return value.slice(0, 10);
    }
    return value;
  }

  function toDateTimeLocal(value) {
    value = (value || '').trim();
    if (value === '') {
      return '';
    }

    if (isDateOnly(value)) {
      return value + 'T00:00';
    }

    // Some browsers may already provide YYYY-MM-DDTHH:MM
    value = value.replace(' ', 'T');
    if (value.length >= 16) {
      value = value.slice(0, 16);
    }
    return value;
  }

  function setTypePreserveValue(input, nextType) {
    if (!input) {
      return;
    }

    var currentValue = input.value || '';

    if (nextType === 'date') {
      input.type = 'date';
      input.value = toDateOnly(currentValue);
      return;
    }

    if (nextType === 'datetime-local') {
      input.type = 'datetime-local';
      input.value = toDateTimeLocal(currentValue);
    }
  }

  function init() {
    var allDay = document.getElementById('clubcal_lite_all_day');
    var start = document.getElementById('clubcal_lite_start');
    var end = document.getElementById('clubcal_lite_end');

    if (!allDay || !start || !end) {
      return;
    }

    function applyType() {
      var nextType = allDay.checked ? 'date' : 'datetime-local';
      setTypePreserveValue(start, nextType);
      setTypePreserveValue(end, nextType);
    }

    allDay.addEventListener('change', applyType);

    // Ensure correct type/value if the browser restores cached form state.
    window.setTimeout(applyType, 0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
