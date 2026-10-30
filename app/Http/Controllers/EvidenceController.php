<?php
namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Services\ChangeLog;
use App\Services\EvidenceAccess;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EvidenceController extends Controller
{
    public function store(Request $request, string $type, int $subject, EntityContext $context, EvidenceAccess $access, ChangeLog $log)
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240']]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $type, $subject, $context, $access, $log, $data, &$path) {
                $access->subject($type, $subject, $request->user(), $context, true);
                $file = $data['file'];
                $path = $file->store('evidence/'.$context->entity->id, 'local');
                if (! $path) { throw new \RuntimeException('Bukti tidak dapat disimpan.'); }
                $record = EvidenceFile::create([
                    'entity_id' => $context->entity->id, 'subject_type' => $type, 'subject_id' => $subject,
                    'path' => $path, 'original_name' => substr(Str::ascii(basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 180),
                    'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $request->user()->id,
                ]);
                $log->record('evidence.uploaded', $record, $request->user());
            });
        } catch (Throwable $e) {
            if ($path) { Storage::disk('local')->delete($path); }
            throw $e;
        }

        $route = match ($type) { 'compliance' => ['compliance.show', $subject], 'finding' => ['findings.show', $subject], default => ['audits.show', \App\Models\AuditProcedure::findOrFail($subject)->audit_engagement_id] };

        return redirect()->route(...$route)->with('success', 'Bukti disimpan secara privat.');
    }

    public function download(Request $request, int $evidence, EntityContext $context, EvidenceAccess $access, ChangeLog $log)
    {
        $record = EvidenceFile::where('entity_id', $context->entity->id)->findOrFail($evidence);
        $access->subject($record->subject_type, $record->subject_id, $request->user(), $context);
        abort_unless(Storage::disk('local')->exists($record->path), 404);
        abort_unless(hash_equals($record->sha256, hash_file('sha256', Storage::disk('local')->path($record->path))), 409, 'Integritas berkas tidak sesuai. Hubungi administrator.');
        if (! $request->attributes->get('demo_mode')) { $log->record('evidence.downloaded', $record, $request->user()); }

        return Storage::disk('local')->download($record->path, $record->original_name, ['Content-Type' => $record->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
