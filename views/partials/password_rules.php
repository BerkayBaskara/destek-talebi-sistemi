<?php
/* Şifre kuralının canlı listesi. Asıl kontrol sunucuda (PasswordPolicy); bu liste yalnız yardımcıdır. */
?>
<ul class="password-rules" data-password-rules aria-live="polite">
    <li data-rule="length">En az 8 karakter</li>
    <li data-rule="letter">En az bir harf</li>
    <li data-rule="digit">En az bir rakam</li>
    <li data-rule="punct">En az bir noktalama işareti</li>
    <li data-rule="match" hidden>Şifreler aynı</li>
</ul>
<script src="/assets/password.js" defer></script>
