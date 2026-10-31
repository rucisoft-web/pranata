<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditEvent extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'actor_id', 'subject_id'];
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    public const LABELS = [
        'risk.created' => 'Risiko dicatat sebagai draf', 'risk.update' => 'Draf risiko diubah',
        'risk.submit' => 'Diajukan untuk tinjauan', 'risk.approve' => 'Disetujui peninjau', 'risk.return' => 'Dikembalikan untuk revisi',
        'compliance.source_created' => 'Versi sumber dicatat', 'compliance.obligation_created' => 'Kewajiban dicatat',
        'compliance.obligation_updated' => 'Kewajiban diubah', 'compliance.period_created' => 'Periode pemenuhan dibuat',
        'compliance.submit' => 'Pemenuhan diajukan', 'compliance.verify' => 'Pemenuhan diverifikasi', 'compliance.return' => 'Pemenuhan dikembalikan',
        'audit.planned' => 'Penugasan audit dibuat', 'audit.start' => 'Pekerjaan lapangan dimulai', 'audit.submit' => 'Laporan diajukan',
        'audit.return' => 'Laporan dikembalikan', 'audit.finalize' => 'Laporan difinalkan', 'audit.close' => 'Penugasan ditutup',
        'audit.procedure_recorded' => 'Kertas kerja dicatat', 'audit.finding_created' => 'Draf temuan dicatat',
        'audit.finding_issued' => 'Temuan diterbitkan', 'audit.finding_submit' => 'Tindak lanjut diajukan',
        'audit.finding_close' => 'Temuan ditutup', 'audit.finding_return' => 'Tindak lanjut dikembalikan',
        'evidence.uploaded' => 'Bukti diunggah', 'evidence.downloaded' => 'Bukti diunduh',
        'user.added' => 'Pengguna ditambahkan', 'user.role_changed' => 'Peran pengguna diubah', 'user.removed' => 'Akses pengguna dicabut',
        'user.password_reset' => 'Kata sandi direset admin', 'user.mfa_reset' => 'MFA direset admin',
    ];

    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit event tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Audit event tidak dapat dihapus.'));
    }
}
