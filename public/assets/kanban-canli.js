// Canlı Kanban: pano 5 saniyede bir /api/kanban/surum ucuna sorulur. Sürüm değişirse (personele yeni talep atandı,
// talebin durumu değişti) sayfa yenilenir ve yeni kartlar kısa süre vurgulanır. Kart sürüklenirken ya da onay
// penceresi açıkken yenileme yapılmaz. Yoklama oturum süresini uzatmaz.
(function () {
  'use strict';

  var board = document.querySelector('.kanban[data-version]');
  if (!board) {
    return;
  }
  var version = board.getAttribute('data-version');
  var KEY = 'kanban-onceki-kartlar';

  function cardIds() {
    return Array.prototype.map.call(document.querySelectorAll('.kanban-card[data-id]'), function (card) {
      return card.getAttribute('data-id');
    });
  }

  // Yenilemeden sonra: önceki listede olmayan kartları vurgula ve bilgi ver.
  try {
    var previous = JSON.parse(window.sessionStorage.getItem(KEY) || 'null');
    window.sessionStorage.removeItem(KEY);
    if (Array.isArray(previous)) {
      var added = 0;
      document.querySelectorAll('.kanban-card[data-id]').forEach(function (card) {
        if (previous.indexOf(card.getAttribute('data-id')) === -1) {
          card.classList.add('kanban-card-new');
          added++;
        }
      });
      if (added > 0) {
        var info = document.getElementById('kanban-live');
        info.textContent = added === 1 ? 'Yeni talep panonuza eklendi.' : added + ' yeni talep panonuza eklendi.';
        info.hidden = false;
        window.setTimeout(function () { info.hidden = true; }, 8000);
      }
    }
  } catch (e) { /* tarayıcı depolaması kapalıysa vurgu yapılmaz */ }

  function busy() {
    return document.querySelector('.kanban-card.dragging') !== null
      || document.querySelector('.modal-backdrop:not([hidden])') !== null
      || (document.activeElement && document.activeElement.classList.contains('kanban-move'));
  }

  window.setInterval(function () {
    if (busy()) {
      return;
    }
    fetch('/api/kanban/surum', { headers: { Accept: 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (!data || data.version === version || busy()) {
          return;
        }
        try {
          window.sessionStorage.setItem(KEY, JSON.stringify(cardIds()));
        } catch (e) { /* depolama yoksa yalnız yenilenir */ }
        window.location.reload();
      })
      .catch(function () { /* ağ hatası: bir sonraki yoklamada tekrar denenir */ });
  }, 5000);
})();
