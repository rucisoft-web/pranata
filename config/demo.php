<?php

return [
    // Tombol "Masuk sebagai Tamu" dan halaman /demo (hanya baca data contoh). DEMO_ENABLED = nama lama.
    'enabled' => (bool) env('PRANATA_GUEST_LOGIN', env('DEMO_ENABLED', false)),
    // Muat data contoh saat `php artisan migrate --seed` / `db:seed`.
    'seed' => (bool) env('PRANATA_SEED_DEMO', false),
];
