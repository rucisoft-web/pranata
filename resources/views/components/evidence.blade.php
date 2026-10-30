<h2>Bukti pendukung</h2><ul class="evidence-list">@forelse($evidence as $file)<li><a href="{{ workspace_route('evidence.download', $file) }}">{{ $file->original_name }}</a> <small>{{ number_format($file->size / 1024, 1) }} KB</small><br><small class="muted hash">SHA-256: {{ $file->sha256 }}</small></li>
@empty<li class="muted">Belum ada bukti.</li>
@endforelse</ul>
@if($canUpload)<form method="post" action="{{ route('evidence.store', [$subjectType, $subjectId]) }}" enctype="multipart/form-data">@csrf<label for="file_{{ $subjectType }}_{{ $subjectId }}">PDF, JPG, atau PNG · maksimum 10 MB</label><input id="file_{{ $subjectType }}_{{ $subjectId }}" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required><button class="button secondary">Unggah bukti</button></form>
@endif
