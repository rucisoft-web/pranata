<?php

return [
    // MFA (aplikasi authenticator) wajib untuk semua akun internal. Bila false, MFA menjadi pilihan
    // per pengguna (menu Keamanan); akun yang sudah mengaktifkan MFA tetap diminta kodenya.
    'mfa_required' => (bool) env('PRANATA_MFA_REQUIRED', true),
];
