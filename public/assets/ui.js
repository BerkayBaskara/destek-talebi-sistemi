// Ortak arayüz parçaları: onay penceresi (modal), öncelik yıldızının bilgi kutusu ve geri sayım.
// JavaScript kapalıysa formlar doğrudan gönderilir; asıl kontrol her durumda sunucudadır.
'use strict';

(function () {
    var lastFocus = null;
    var modal = null;

    function backgroundParts() {
        return [document.querySelector('header.topbar'), document.getElementById('main')].filter(Boolean);
    }

    function buildModal() {
        var wrap = document.createElement('div');
        wrap.className = 'modal-backdrop';
        wrap.hidden = true;
        wrap.innerHTML =
            '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-text">' +
            '<h2 id="modal-title">Onay</h2>' +
            '<p id="modal-text"></p>' +
            '<div class="modal-actions">' +
            '<button type="button" class="btn btn-light" data-modal-cancel>Vazgeç</button>' +
            '<button type="button" class="btn" data-modal-confirm>Onayla</button>' +
            '</div></div>';
        document.body.appendChild(wrap);
        return wrap;
    }

    // Onay penceresini açar; seçim sonucu callback'e true/false olarak iletilir.
    function confirmAction(message, done) {
        modal = modal || buildModal();
        var confirmButton = modal.querySelector('[data-modal-confirm]');
        var cancelButton = modal.querySelector('[data-modal-cancel]');
        modal.querySelector('#modal-text').textContent = message;
        lastFocus = document.activeElement;

        backgroundParts().forEach(function (part) {
            part.setAttribute('inert', '');
            part.classList.add('blurred');
        });
        modal.hidden = false;
        confirmButton.focus();

        function close(result) {
            modal.hidden = true;
            backgroundParts().forEach(function (part) {
                part.removeAttribute('inert');
                part.classList.remove('blurred');
            });
            confirmButton.removeEventListener('click', onConfirm);
            cancelButton.removeEventListener('click', onCancel);
            modal.removeEventListener('keydown', onKey);
            modal.removeEventListener('click', onBackdrop);
            if (lastFocus && typeof lastFocus.focus === 'function') {
                lastFocus.focus();
            }
            done(result);
        }
        function onConfirm() { close(true); }
        function onCancel() { close(false); }
        function onBackdrop(event) {
            if (event.target === modal) {
                close(false);
            }
        }
        function onKey(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(false);
            } else if (event.key === 'Tab') {
                // Odak pencerede kalır: iki düğme arasında döner.
                event.preventDefault();
                (document.activeElement === confirmButton ? cancelButton : confirmButton).focus();
            }
        }

        confirmButton.addEventListener('click', onConfirm);
        cancelButton.addEventListener('click', onCancel);
        modal.addEventListener('keydown', onKey);
        modal.addEventListener('click', onBackdrop);
    }

    window.confirmAction = confirmAction;

    // data-confirm taşıyan formlar önce onay ister.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
            return;
        }
        event.preventDefault();
        confirmAction(form.getAttribute('data-confirm'), function (ok) {
            if (ok) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });

    // Öncelik yıldızı: üzerine gelme ve odakta CSS gösterir; tıklama/dokunma aç-kapa yapar.
    document.addEventListener('click', function (event) {
        var tip = event.target.closest('.tip');
        document.querySelectorAll('.tip.show').forEach(function (open) {
            if (open !== tip) {
                open.classList.remove('show');
            }
        });
        if (tip) {
            event.preventDefault();
            tip.classList.toggle('show');
        }
    });

    // Bildirim sayacı: 60 saniyede bir yenilenir (oturum süresini uzatmaz).
    var bell = document.querySelector('.bell');
    if (bell) {
        window.setInterval(function () {
            fetch('/api/bildirimler/sayi', { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data) {
                        return;
                    }
                    var badge = bell.querySelector('.bell-count');
                    if (data.count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'bell-count';
                            bell.appendChild(badge);
                        }
                        badge.textContent = String(data.count);
                        bell.setAttribute('aria-label', 'Bildirimler (' + data.count + ' okunmamış)');
                    } else if (badge) {
                        badge.remove();
                        bell.setAttribute('aria-label', 'Bildirimler');
                    }
                })
                .catch(function () { /* ağ hatası: bir sonraki yoklamada tekrar denenir */ });
        }, 60000);
    }

    // Talep formu: yalnız seçilen kategorinin ek alanları gösterilir (JavaScript yoksa hepsi görünür).
    var categorySelect = document.getElementById('category_id');
    var extraFields = document.querySelectorAll('.extra-field[data-category]');
    if (categorySelect && extraFields.length > 0) {
        var syncFields = function () {
            extraFields.forEach(function (field) {
                field.hidden = field.getAttribute('data-category') !== categorySelect.value;
            });
        };
        categorySelect.addEventListener('change', syncFields);
        syncFields();
    }

    // Geri sayım (talep oluşturuldu ekranı). Sayfa başındaki meta yenileme JavaScript kapalıyken de yönlendirir.
    var countdown = document.querySelector('[data-countdown]');
    if (countdown) {
        var left = parseInt(countdown.getAttribute('data-countdown'), 10);
        var number = countdown.querySelector('[data-countdown-number]');
        var timer = window.setInterval(function () {
            left -= 1;
            if (left <= 0) {
                window.clearInterval(timer);
                window.location.href = countdown.getAttribute('data-countdown-url');
                return;
            }
            number.textContent = String(left);
        }, 1000);
    }
})();
