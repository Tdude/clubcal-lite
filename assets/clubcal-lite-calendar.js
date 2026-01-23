(function () {
  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function getModal() {
    return qs('.clubcal-lite-modal');
  }

  var hoverCardEl = null;

  function removeHoverCard(immediate) {
    if (!hoverCardEl) {
      return;
    }
    var el = hoverCardEl;
    hoverCardEl = null;

    if (immediate) {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
      return;
    }

    el.classList.remove('is-visible');
    el.classList.add('is-leaving');
    window.setTimeout(function () {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }, 180);
  }

  function showHoverCard(sourceEl) {
    removeHoverCard(true);

    if (!sourceEl || !sourceEl.getBoundingClientRect) {
      return;
    }

    var rect = sourceEl.getBoundingClientRect();
    hoverCardEl = sourceEl.cloneNode(true);
    hoverCardEl.classList.add('clubcal-lite-hovercard');
    hoverCardEl.style.position = 'fixed';
    hoverCardEl.style.left = rect.left + 'px';
    hoverCardEl.style.top = rect.top + 'px';
    hoverCardEl.style.width = rect.width + 'px';
    hoverCardEl.style.zIndex = '100000';
    hoverCardEl.style.display = 'flex';

    var origDot = sourceEl.querySelector('.fc-list-event-dot');
    var cloneDot = hoverCardEl.querySelector('.fc-list-event-dot');
    if (origDot && cloneDot) {
      var dotColor = origDot.style.borderColor || window.getComputedStyle(origDot).borderColor;
      if (dotColor) {
        cloneDot.style.borderColor = dotColor;
      }
    }

    document.body.appendChild(hoverCardEl);
    window.requestAnimationFrame(function () {
      if (hoverCardEl) {
        hoverCardEl.classList.add('is-visible');
      }
    });
  }

  function closeModal() {
    var modal = getModal();
    if (!modal) {
      return;
    }

    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');

    var content = qs('[data-clubcal-lite-modal-content]', modal);
    if (content) {
      content.innerHTML = '';
    }
  }

  function openModal(html) {
    var modal = getModal();
    if (!modal) {
      return;
    }

    var content = qs('[data-clubcal-lite-modal-content]', modal);
    if (content) {
      content.innerHTML = html;
    }

    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
  }

  function initModal() {
    var modal = getModal();
    if (!modal) {
      return;
    }

    qsa('[data-clubcal-lite-modal-close]', modal).forEach(function (btn) {
      btn.addEventListener('click', function () {
        closeModal();
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeModal();
      }
    });
  }

  function ensureSvLocale() {
    if (!window.FullCalendar) {
      return;
    }

    window.FullCalendar.globalLocales = window.FullCalendar.globalLocales || [];
    window.FullCalendar.globalLocales.push({
      code: 'sv',
      week: { dow: 1, doy: 4 },
      buttonText: {
        prev: 'Frege5ende',
        next: 'Ne4sta',
        today: 'Idag',
        month: 'Me5nad',
        week: 'Vecka',
        day: 'Dag',
        list: 'Lista'
      },
      weekText: 'V',
      allDayText: 'Hela dagen',
      moreLinkText: function (n) {
        return '+' + n + ' till';
      },
      noEventsText: 'Inga he4ndelser att visa'
    });
  }

  function initOne(el) {
    if (!window.FullCalendar || !window.ClubCalLite) {
      return;
    }

    var category = el.getAttribute('data-category') || '';
    var initialView = el.getAttribute('data-view') || 'dayGridMonth';
    var initialDate = el.getAttribute('data-initial-date') || '';
    var listMonths = parseInt(el.getAttribute('data-list-months') || '3', 10);
    if (isNaN(listMonths) || listMonths < 1) {
      listMonths = 3;
    }
    if (listMonths > 12) {
      listMonths = 12;
    }

    var listDuration = { months: listMonths };
    var listButtonText = listMonths === 1 ? '1 me5nad' : listMonths + ' me5nader';

    var calendar = new FullCalendar.Calendar(el, {
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listRange' },
      locale: 'sv',
      firstDay: 1,
      views: {
        listRange: {
          type: 'list',
          duration: listDuration,
          buttonText: listButtonText
        }
      },
      buttonText: { today: 'Idag', month: 'Me5nad', week: 'Vecka', day: 'Dag', list: 'Lista', dayGridMonth: 'Me5nad' },
      height: 'auto',
      expandRows: true,
      initialView: initialView === 'listWeek' ? 'listRange' : initialView,
      initialDate: initialDate || undefined,
      eventMouseEnter: function (info) {
        try {
          if (!info || !info.el) {
            return;
          }
          showHoverCard(info.el);
        } catch (e) {}
      },
      eventMouseLeave: function () {
        removeHoverCard(false);
      },
      eventClick: function (info) {
        if (info && info.jsEvent) {
          info.jsEvent.preventDefault();
        }

        var eventId = info && info.event ? info.event.id : null;
        if (!eventId) {
          return;
        }

        openModal('<p>Loading...</p>');

        var url = new URL(window.ClubCalLite.ajaxUrl);
        url.searchParams.set('action', window.ClubCalLite.actionDetails);
        url.searchParams.set('_ajax_nonce', window.ClubCalLite.nonceDetails);
        url.searchParams.set('event_id', eventId);

        fetch(url.toString(), { credentials: 'same-origin' })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            if (data && data.success && data.data && data.data.html) {
              openModal(data.data.html);
            } else {
              openModal('<p>Could not load event.</p>');
            }
          })
          .catch(function () {
            openModal('<p>Could not load event.</p>');
          });
      },
      events: function (info, success, failure) {
        var url = new URL(window.ClubCalLite.ajaxUrl);
        url.searchParams.set('action', window.ClubCalLite.actionEvents);
        url.searchParams.set('_ajax_nonce', window.ClubCalLite.nonceEvents);
        url.searchParams.set('start', info.startStr);
        url.searchParams.set('end', info.endStr);
        if (category) {
          url.searchParams.set('category', category);
        }

        fetch(url.toString(), { credentials: 'same-origin' })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            if (data && data.success && Array.isArray(data.data)) {
              success(data.data);
            } else {
              failure(data && data.data ? data.data : 'Invalid response');
            }
          })
          .catch(function (err) {
            failure(err);
          });
      }
    });

    calendar.render();
  }

  function init() {
    ensureSvLocale();
    initModal();
    qsa('.clubcal-lite-calendar').forEach(initOne);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
