<?php

return [
    'methodology_version' => 'PRN-INITIAL-1',
    'categories' => ['Strategis', 'Keuangan', 'Operasional', 'Hukum dan Kepatuhan', 'Teknologi dan Siber', 'Fraud', 'Reputasi', 'ESG'],
    'responses' => ['reduce' => 'Kurangi', 'avoid' => 'Hindari', 'share' => 'Bagi / alihkan', 'accept' => 'Terima'],
    'statuses' => ['draft' => 'Draf', 'submitted' => 'Menunggu tinjauan', 'approved' => 'Disetujui'],
    'roles' => [
        'admin' => 'Administrator', 'risk_manager' => 'Manajer risiko', 'risk_owner' => 'Pemilik risiko',
        'compliance_officer' => 'Petugas kepatuhan', 'compliance_owner' => 'PIC kepatuhan',
        'audit_manager' => 'Manajer audit', 'auditor' => 'Auditor', 'viewer' => 'Pembaca',
    ],
];
