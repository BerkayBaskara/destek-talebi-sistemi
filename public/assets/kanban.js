// FR-09: Kanban sürükle-bırak. Kart başka sütuna bırakılınca sunucuya JSON istek gider;
// sunucu State deseniyle kontrol eder. Reddederse kart eski yerine döner ve neden gösterilir.
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

  function move(card, targetStatus, previousColumn) {
    errorBox.hidden = true;
    return fetch('/api/talepler/' + card.dataset.id + '/durum', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({ status: targetStatus })
    })
      .then(function (response) { return response.json(); })
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
        previousColumn.appendChild(card); // geri al
        card.querySelector('.kanban-move').value = previousColumn.parentElement.dataset.status;
        updateCounts();
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
    });

    // Mobil / klavye yedeği: açılır menüden durum seçimi
    card.querySelector('.kanban-move').addEventListener('change', function (event) {
      var previous = card.parentElement;
      var target = document.querySelector('.kanban-col[data-status="' + event.target.value + '"] .kanban-cards');
      target.appendChild(card);
      move(card, event.target.value, previous);
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
      move(dragged, column.dataset.status, previous);
      dragged = null;
    });
  });
})();
