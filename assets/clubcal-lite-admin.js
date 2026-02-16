(function () {
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
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      return value + 'T00:00';
    }
    if (value.length >= 16) {
      return value.slice(0, 16);
    }
    return value;
  }

  function show(el, visible) {
    if (!el) {
      return;
    }
    el.style.display = visible ? '' : 'none';
  }

  function setName(el, name) {
    if (!el) {
      return;
    }
    if (name) {
      el.setAttribute('name', name);
    } else {
      el.removeAttribute('name');
    }
  }

  function init() {
    var allDay = document.getElementById('clubcal_lite_all_day');
    var startDate = document.getElementById('clubcal_lite_start_date');
    var startDt = document.getElementById('clubcal_lite_start_dt');
    var endDate = document.getElementById('clubcal_lite_end_date');
    var endDt = document.getElementById('clubcal_lite_end_dt');

    if (!allDay || !startDate || !startDt || !endDate || !endDt) {
      return;
    }

    function applyType() {
      if (allDay.checked) {
        startDate.value = toDateOnly(startDt.value || startDate.value);
        endDate.value = toDateOnly(endDt.value || endDate.value);
        show(startDate, true);
        show(endDate, true);
        show(startDt, false);
        show(endDt, false);
        setName(startDate, 'clubcal_lite_start');
        setName(endDate, 'clubcal_lite_end');
        setName(startDt, '');
        setName(endDt, '');
      } else {
        startDt.value = toDateTimeLocal(startDt.value || startDate.value);
        endDt.value = toDateTimeLocal(endDt.value || endDate.value);
        show(startDate, false);
        show(endDate, false);
        show(startDt, true);
        show(endDt, true);
        setName(startDate, '');
        setName(endDate, '');
        setName(startDt, 'clubcal_lite_start');
        setName(endDt, 'clubcal_lite_end');
      }
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
