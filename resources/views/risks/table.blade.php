<div class="table-wrap"><table>
<thead><tr><th>Kode / risiko</th><th>Kategori</th><th>Skor inheren</th><th>Status</th><th>Review</th></tr></thead>
<tbody>
@forelse($risks as $risk)
<tr><td><a href="{{ workspace_route('risks.show', $risk) }}"><strong>{{ $risk->code }}</strong><span class="table-title">{{ $risk->title }}</span></a></td><td>{{ $risk->category }}</td><td><span class="badge {{ $risk->score >= 12 ? 'high' : 'neutral' }}">{{ $risk->score }} · {{ $risk->level }}</span></td><td><span class="badge {{ $risk->status }}">{{ config('grc.statuses')[$risk->status] }}</span></td><td>{{ $risk->review_date->format('d/m/Y') }}</td></tr>
@empty
<tr><td colspan="5" class="empty">Belum ada risiko. Mulai dengan mencatat risiko terhadap sasaran perusahaan.</td></tr>
@endforelse
</tbody></table></div>
