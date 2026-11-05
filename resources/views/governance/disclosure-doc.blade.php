<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" lang="id"><head><meta charset="utf-8"><title>Pengungkapan tata kelola {{ $assessment->year }}</title>
<style>body{font-family:Calibri,Arial,sans-serif;font-size:11pt;color:#15262d}h1{font-size:16pt;margin:0 0 6pt}p{margin:0 0 8pt;line-height:1.4}table{border-collapse:collapse;width:100%}th,td{border:1px solid #9fb0b8;padding:5pt;vertical-align:top;font-size:10pt}th{background:#dfe9ec}.gv-aspect td{background:#20353d;color:#fff;font-weight:bold}.gv-group td{background:#eef3f5;font-weight:bold}.draft{color:#9a4834;font-weight:bold}</style></head><body>
<p><strong>{{ $context->entity->name }}</strong></p>
<h1>Penerapan {{ config('governance.framework') }} Tahun Buku {{ $assessment->year }}</h1>
@unless($assessment->isLocked())<p class="draft">DRAF: penilaian belum disetujui ({{ $summary['verified'] }}/25 terverifikasi).</p>@endunless
<p>Sesuai {{ config('governance.reference') }}, Perseroan mengungkapkan penerapan 25 rekomendasi dengan pendekatan terapkan atau jelaskan. Pada tahun buku {{ $assessment->year }}, Perseroan telah memenuhi {{ $summary['applied'] }} rekomendasi, memenuhi sebagian {{ $summary['partial'] }} rekomendasi, dan belum memenuhi {{ $summary['not_applied'] }} rekomendasi.</p>
@include('governance._disclosure-table')
</body></html>
