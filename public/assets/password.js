// Şifre kuralının canlı kontrolü: karşılanan kural yeşil olur; hepsi sağlanana kadar gönder düğmesi pasif kalır.
// Asıl kontrol sunucuda yapılır; JavaScript kapalıysa form yine gönderilir ve sunucu hatayı gösterir.
'use strict';

(function () {
    var list = document.querySelector('[data-password-rules]');
    if (!list) {
        return;
    }
    var form = list.closest('form');
    var password = form.querySelector('input[name="password"]');
    var confirm = form.querySelector('input[name="password_confirm"]');
    var submit = form.querySelector('button[type="submit"]');
    var matchRule = list.querySelector('[data-rule="match"]');
    if (confirm) {
        matchRule.hidden = false;
    }

    var rules = {
        length: function (v) { return v.length >= 8; },
        letter: function (v) { return /\p{L}/u.test(v); },
        digit: function (v) { return /\p{N}/u.test(v); },
        punct: function (v) { return /[^\p{L}\p{N}\s]/u.test(v); },
        match: function (v) { return !confirm || (v !== '' && v === confirm.value); }
    };

    function check() {
        var all = true;
        Object.keys(rules).forEach(function (name) {
            var item = list.querySelector('[data-rule="' + name + '"]');
            var ok = rules[name](password.value);
            item.classList.toggle('ok', ok);
            all = all && ok;
        });
        submit.disabled = !all;
    }

    password.addEventListener('input', check);
    if (confirm) {
        confirm.addEventListener('input', check);
    }
    check();
})();
