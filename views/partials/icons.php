<?php
/*
 * Durum simgeleri (satır içi SVG, dış dosya ya da kütüphane yok). Simge yalnız görseldir (aria-hidden);
 * durumu ekran okuyucuya yanındaki etiket metni söyler.
 */
$svg = static fn (string $color, string $paths): string => '<svg class="status-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="'
    . $color . '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';

return [
    // gri kum saati
    'pending' => $svg('#6b7280', '<path d="M6 2h12M6 22h12M7 2c0 5 10 7 10 10s-10 5-10 10M17 2c0 5-10 7-10 10s10 5 10 10"/>'),
    // mavi gelen kutusu
    'taken' => $svg('#1d5fbf', '<path d="M3 13l3-8h12l3 8v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M3 13h5l1 3h6l1-3h5"/>'),
    // sarı büyüteç
    'in_review' => $svg('#b7791f', '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/>'),
    // tek yeşil tik
    'resolved' => $svg('#1e7b34', '<path d="M4 12.5l5 5L20 6.5"/>'),
    // çift yeşil tik
    'closed' => $svg('#1e7b34', '<path d="M2 12.5l5 5L18 6.5"/><path d="M12 16.5l1 1L23 6.5"/>'),
    // kırmızı çarpı
    'cancelled' => $svg('#b3261e', '<path d="M6 6l12 12M18 6L6 18"/>'),
];
