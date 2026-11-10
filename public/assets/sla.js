// FR-07 / US-11: sayfadaki SLA rozetlerini 60 saniyede bir sunucudan yeniler (sayfa yenilenmeden).
// Bu yoklama oturum süresini uzatmaz (NFR-10: 30 dk hareketsizlikte oturum kapanır).
(function () {
  'use strict';

  function refresh() {
    var badges = document.querySelectorAll('.sla-badge[data-ticket-id]');
    if (badges.length === 0) {
      return;
    }
    var ids = Array.prototype.map.call(badges, function (b) { return b.dataset.ticketId; });

    fetch('/api/sla?ids=' + encodeURIComponent(ids.join(',')), { headers: { Accept: 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (!data) {
          return;
        }
        badges.forEach(function (badge) {
          var info = data[badge.dataset.ticketId];
          if (info) {
            badge.className = 'sla-badge ' + info.css;
            badge.textContent = info.label;
          }
        });
      })
      .catch(function () { /* ağ hatası: bir sonraki denemede yeniden */ });
  }

  setInterval(refresh, 60000);
})();
