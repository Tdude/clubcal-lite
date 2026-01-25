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

  function renderLegend(calEl, events) {
    try {
      if (!calEl) {
        return;
      }
      var table = qs('.fc-list-table', calEl);
      if (!table) {
        return;
      }

      var existing = qs('[data-clubcal-legend]', calEl);
      if (existing && existing.parentNode) {
        existing.parentNode.removeChild(existing);
      }

      var map = {};
      var isUncatMap = {};
      (events || []).forEach(function (ev) {
        var name = ev && ev.extendedProps ? ev.extendedProps.categoryName : '';
        var color = ev && ev.extendedProps ? ev.extendedProps.dotColor : '';
        var isUncat = !!(ev && ev.extendedProps && ev.extendedProps.isUncategorized);
        if (!name || !color) {
          return;
        }
        name = String(name).trim();
        color = String(color).trim();
        if (!name || !color) {
          return;
        }
        if (!map[name]) {
          map[name] = color;
          isUncatMap[name] = isUncat;
        }
      });

      var names = Object.keys(map);
      if (!names.length) {
        return;
      }

      names.sort(function (a, b) {
        var au = !!isUncatMap[a];
        var bu = !!isUncatMap[b];
        if (au !== bu) {
          return au ? 1 : -1;
        }
        return a.localeCompare(b);
      });

      var wrap = document.createElement('div');
      wrap.setAttribute('data-clubcal-legend', '1');
      wrap.className = 'clubcal-lite-legend';

      names.forEach(function (name) {
        var item = document.createElement('span');
        item.className = 'clubcal-lite-legend__item';
        var dot = document.createElement('span');
        dot.className = 'clubcal-lite-legend__dot';
        dot.style.borderColor = map[name];
        dot.title = name;
        var label = document.createElement('span');
        label.className = 'clubcal-lite-legend__label';
        label.textContent = name;
        item.appendChild(dot);
        item.appendChild(label);
        wrap.appendChild(item);
      });

      table.parentNode.insertBefore(wrap, table);
    } catch (e) {}
  }

  function normalizeListView(calEl) {
    try {
      if (!calEl) {
        return;
      }
      qsa('tr.fc-list-day', calEl).forEach(function (dayRow) {
        dayRow.style.display = 'none';
      });
    } catch (e) {}
  }

  function decorateListEventRow(info) {
    try {
      if (!info || !info.el || !info.event) {
        return;
      }

      var tr = info.el.closest('tr');
      if (!tr || !tr.classList.contains('fc-list-event')) {
        return;
      }
      if (!tr.closest('.fc-list')) {
        return;
      }

      qsa('[data-clubcal-list-excerpt]', tr).forEach(function (el) {
        if (el.parentNode) {
          el.parentNode.removeChild(el);
        }
      });

      var timeCell = qs('td.fc-list-event-time', tr);
      if (timeCell) {
        var startStr = info.event.startStr || '';
        var dateStr = startStr.split('T')[0];
        var restText = (info.timeText || '').trim();
        timeCell.textContent = '';
        var strong = document.createElement('span');
        strong.style.fontWeight = '600';
        strong.textContent = dateStr;
        timeCell.appendChild(strong);
        if (restText) {
          timeCell.appendChild(document.createTextNode(' ' + restText));
        }
      }

      var titleCell = qs('td.fc-list-event-title', tr) || qs('.fc-list-event-title', tr);
      if (titleCell) {
        var titleLink = qs('a', titleCell);
        if (titleLink) {
          titleLink.classList.add('clubcal-lite-fc-title');
        }

        var catName = info.event.extendedProps && info.event.extendedProps.categoryName ? String(info.event.extendedProps.categoryName) : '';
        var dotColor = info.event.extendedProps && info.event.extendedProps.dotColor ? String(info.event.extendedProps.dotColor) : '';
        catName = catName.trim();
        dotColor = dotColor.trim();

        var dotEl = qs('.fc-list-event-dot', tr);
        var graphicCell = qs('td.fc-list-event-graphic', tr);
        if (dotEl && dotColor) {
          dotEl.style.display = '';
          dotEl.style.borderColor = dotColor;
          dotEl.style.borderTopColor = dotColor;
          dotEl.style.borderRightColor = dotColor;
          dotEl.style.borderBottomColor = dotColor;
          dotEl.style.borderLeftColor = dotColor;
          if (graphicCell) {
            graphicCell.style.padding = '';
          }
        } else if (dotEl) {
          dotEl.style.display = 'none';
        }
        if (dotEl && catName) {
          dotEl.title = catName;
        }

        var excerpt = info.event.extendedProps && info.event.extendedProps.excerpt ? String(info.event.extendedProps.excerpt) : '';
        excerpt = excerpt.trim();
        if (excerpt) {
          var ex = document.createElement('div');
          ex.setAttribute('data-clubcal-list-excerpt', '1');
          ex.className = 'clubcal-lite-fc-excerpt';
          ex.style.opacity = '0.85';
          ex.style.marginTop = '2px';
          ex.textContent = excerpt;
          titleCell.appendChild(ex);
        }
      }
    } catch (e) {}
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
        prev: 'Föregående',
        next: 'Nästa',
        today: 'Idag',
        month: 'Månad',
        week: 'Vecka',
        day: 'Dag',
        list: 'Lista'
      },
      weekText: 'V',
      allDayText: 'Hela dagen',
      moreLinkText: function (n) {
        return '+' + n + ' till';
      },
      noEventsText: 'Inga händelser att visa'
    });
  }

  function initOne(el) {
    if (!window.FullCalendar || !window.ClubCalLite) {
      return;
    }

    function toDateOnly(d) {
      var y = d.getFullYear();
      var m = String(d.getMonth() + 1).padStart(2, '0');
      var day = String(d.getDate()).padStart(2, '0');
      return y + '-' + m + '-' + day;
    }

    function parseIsoDate(iso) {
      try {
        return iso ? new Date(iso) : null;
      } catch (e) {
        return null;
      }
    }

    function expandMultiDayEventsForList(events) {
      var out = [];
      (events || []).forEach(function (ev) {
        if (!ev || !ev.start) {
          return;
        }

        var start = parseIsoDate(ev.start);
        var end = ev.end ? parseIsoDate(ev.end) : null;
        if (!start || isNaN(start.getTime())) {
          return;
        }

        var startDay = toDateOnly(start);
        var endDay = end && !isNaN(end.getTime()) ? toDateOnly(end) : '';

        // Single-day or no end => keep as-is
        if (!end || !endDay || endDay === startDay) {
          out.push(ev);
          return;
        }

        // FullCalendar allDay end is typically exclusive (end at 00:00 of next day).
        // We aim to show one list row per calendar day.
        var dayCursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
        var lastDay = new Date(end.getFullYear(), end.getMonth(), end.getDate());

        if (ev.allDay && end.getHours() === 0 && end.getMinutes() === 0 && end.getSeconds() === 0) {
          lastDay.setDate(lastDay.getDate() - 1);
        }

        var originalId = ev.id;

        while (dayCursor <= lastDay) {
          var clone = {};
          for (var k in ev) {
            clone[k] = ev[k];
          }

          var dayIso = toDateOnly(dayCursor) + 'T00:00:00';
          clone.start = dayIso;
          delete clone.end;

          clone.id = String(originalId) + '__' + toDateOnly(dayCursor);
          clone.allDay = true;
          clone.extendedProps = clone.extendedProps || {};
          clone.extendedProps.originalId = originalId;

          out.push(clone);
          dayCursor.setDate(dayCursor.getDate() + 1);
        }
      });

      return out;
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
    var listButtonText = listMonths === 1 ? '1 månad' : listMonths + ' månader';

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
      buttonText: { today: 'Idag', month: 'Månad', week: 'Vecka', day: 'Dag', list: 'Lista', dayGridMonth: 'Månad' },
      height: 'auto',
      expandRows: true,
      initialView: initialView === 'listWeek' ? 'listRange' : initialView,
      initialDate: initialDate || undefined,
      datesSet: function () {
        window.setTimeout(function () {
          normalizeListView(el);
        }, 0);
      },
      eventsSet: function (events) {
        window.setTimeout(function () {
          normalizeListView(el);
        }, 0);
        window.setTimeout(function () {
          renderLegend(el, events);
        }, 0);
      },
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
      eventDidMount: function (info) {
        window.setTimeout(function () {
          normalizeListView(el);
        }, 0);
        window.setTimeout(function () {
          decorateListEventRow(info);
        }, 0);
      },
      eventClick: function (info) {
        if (info && info.jsEvent) {
          info.jsEvent.preventDefault();
        }

        var eventId = info && info.event ? info.event.id : null;
        if (info && info.event && info.event.extendedProps && info.event.extendedProps.originalId) {
          eventId = info.event.extendedProps.originalId;
        }
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
              var events = data.data;
              // Only expand in list views (month grid should remain unchanged)
              try {
                var viewType = calendar && calendar.view && calendar.view.type ? String(calendar.view.type) : '';
                if (viewType.indexOf('list') === 0) {
                  events = expandMultiDayEventsForList(events);
                }
              } catch (e) {}

              success(events);
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
