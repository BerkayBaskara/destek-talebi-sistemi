// FR-09: Kanban sürükle-bırak. Kart başka sütuna bırakılınca önce onay istenir, sonra sunucuya JSON istek gider;
// sunucu State deseniyle kontrol eder. Reddederse (ya da kullanıcı vazgeçerse) kart eski yerine döner.
(function () {
  'use strict';

  var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var errorBox = document.getElementById('kanban-error');
  var dragged = null;

  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
  }

  function updateCounts() {
    document.querySelectorAll('.kanban-col').forEach(function (col) {
      col.querySelector('.count').textContent = col.querySelectorAll('.kanban-card').length;
    });
  }

  function revert(card, previousColumn) {
    previousColumn.appendChild(card);
    card.querySelector('.kanban-move').value = previousColumn.parentElement.dataset.status;
    updateCounts();
  }

  // Taşımadan önce onay penceresi (ui.js). Pencere yoksa doğrudan taşınır.
  function askAndMove(card, targetStatus, previousColumn) {
    var column = document.querySelector('.kanban-col[data-status="' + targetStatus + '"]');
    var label = column ? column.dataset.label : targetStatus;
    if (typeof window.confirmAction !== 'function') {
      move(card, targetStatus, previousColumn);
      return;
    }
    window.confirmAction('Bu talebin durumunu «' + label + '» olarak değiştirmek istiyor musunuz?', function (ok) {
      if (ok) {
        move(card, targetStatus, previousColumn);
      } else {
        revert(card, previousColumn);
      }
    });
  }

  function move(card, targetStatus, previousColumn) {
    errorBox.hidden = true;
    return fetch('/api/talepler/' + card.dataset.id + '/durum', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({ status: targetStatus })
    })
      .then(function (response) {
        if (response.status === 401) {
          window.location.href = '/giris'; // oturum süresi doldu
          throw new Error('Oturumunuz sona erdi. Giriş sayfasına yönlendiriliyorsunuz.');
        }
        return response.json().catch(function () {
          throw new Error('Sunucudan beklenmeyen yanıt geldi. Sayfayı yenileyip tekrar deneyin.');
        });
      })
      .then(function (data) {
        if (!data.ok) {
          throw new Error(data.error || 'İşlem yapılamadı.');
        }
        var badge = card.querySelector('.sla-badge');
        if (badge && data.sla) {
          badge.className = 'sla-badge ' + data.sla.css;
          badge.textContent = data.sla.label;
        }
        card.querySelector('.kanban-move').value = data.status;
        updateCounts();
      })
      .catch(function (error) {
        revert(card, previousColumn); // geri al
        showError(error.message);
      });
  }

  document.querySelectorAll('.kanban-card').forEach(function (card) {
    card.addEventListener('dragstart', function (event) {
      dragged = card;
      event.dataTransfer.effectAllowed = 'move';
      card.classList.add('dragging');
    });
    card.addEventListener('dragend', function () {
      card.classList.remove('dragging');
      dragged = null; // sonraki ilgisiz bir bırakma eski kartı taşımasın
    });

    // Mobil / klavye yedeği: açılır menüden durum seçimi
    card.querySelector('.kanban-move').addEventListener('change', function (event) {
      var previous = card.parentElement;
      var target = document.querySelector('.kanban-col[data-status="' + event.target.value + '"] .kanban-cards');
      target.appendChild(card);
      updateCounts();
      askAndMove(card, event.target.value, previous);
    });
  });

  document.querySelectorAll('.kanban-col').forEach(function (column) {
    column.addEventListener('dragover', function (event) {
      event.preventDefault();
      column.classList.add('drop-target');
    });
    column.addEventListener('dragleave', function () {
      column.classList.remove('drop-target');
    });
    column.addEventListener('drop', function (event) {
      event.preventDefault();
      column.classList.remove('drop-target');
      if (!dragged || dragged.parentElement.parentElement === column) {
        return;
      }
      var previous = dragged.parentElement;
      column.querySelector('.kanban-cards').appendChild(dragged);
      updateCounts();
      askAndMove(dragged, column.dataset.status, previous);
      dragged = null;
    });
  });
})();
