// Ortak arayüz davranışları: dar ekranda menünün açılıp kapanması ve tıklanabilir tablo satırları.
// JavaScript kapalıysa menü her zaman açık görünür, satırlardaki bağlantılar yine çalışır.
'use strict';

(function () {
    var toggle = document.querySelector('.menu-toggle');
    var menu = document.getElementById('main-menu');
    if (toggle && menu) {
        document.documentElement.classList.add('has-menu-toggle');
        toggle.hidden = false;
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            menu.classList.toggle('open', open);
            toggle.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.classList.contains('open')) {
                toggle.click();
                toggle.focus();
            }
        });
    }

    // Satırın herhangi bir yerine tıklamak talep ayrıntısını açar (bağlantı, düğme ve form öğeleri hariç).
    document.addEventListener('click', function (event) {
        var row = event.target.closest('tr[data-href]');
        if (!row || event.target.closest('a, button, input, select, textarea, label, form')) {
            return;
        }
        window.location.href = row.getAttribute('data-href');
    });
})();
