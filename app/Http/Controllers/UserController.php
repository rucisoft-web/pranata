<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Entity;
use App\Models\User;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(EntityContext $context)
    {
        abort_unless($context->canManageUsers(), 403);
        $members = User::with('entities')->whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id))
            ->where('is_demo_guest', false)->orderBy('name')->get()
            ->each(fn (User $u) => $u->setAttribute('role_here', $u->entities->firstWhere('id', $context->entity->id)?->pivot->role));

        return view('users.index', compact('context', 'members'));
    }

    public function store(Request $request, EntityContext $context)
    {
        abort_unless($context->canManageUsers() && ! $context->entity->is_demo, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', Rule::in(array_keys(config('grc.roles')))],
            'password' => ['nullable', 'string'],
        ]);
        $email = Str::lower(trim($data['email']));
        $existing = User::where('email', $email)->first();
        if ($existing?->is_demo_guest) { $this->fail('Akun tamu tidak dapat diberi akses.'); }
        if (! $existing) {
            $request->validate(['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]]);
        }
        if ($existing && $existing->entities()->where('entities.id', $context->entity->id)->exists()) { $this->fail('Pengguna sudah memiliki akses ke perusahaan ini. Ubah perannya di daftar.'); }
        DB::transaction(function () use ($existing, $data, $email, $context, $request) {
            $user = $existing ?? User::create(['name' => $data['name'], 'email' => $email, 'password' => $data['password']]);
            $user->entities()->attach($context->entity->id, ['role' => $data['role']]);
            $this->log($context, $request->user(), $user, 'user.added', ['role' => $data['role'], 'new_account' => ! $existing]);
        });

        return redirect()->route('users.index')->with('success', $existing
            ? 'Akun yang sudah ada diberi akses ke perusahaan ini. Kata sandinya tidak berubah.'
            : 'Pengguna dibuat. Sampaikan kata sandi sementara secara langsung dan minta pengguna menggantinya di menu Keamanan.');
    }

    public function update(Request $request, int $user, EntityContext $context)
    {
        abort_unless($context->canManageUsers(), 403);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(config('grc.roles')))]]);
        $target = $this->member($user, $context);
        if ($target->id === $request->user()->id && $data['role'] !== 'admin') { $this->fail('Anda tidak dapat menurunkan peran akun sendiri.'); }
        DB::transaction(function () use ($target, $data, $context, $request) {
            $target->entities()->updateExistingPivot($context->entity->id, ['role' => $data['role']]);
            $this->log($context, $request->user(), $target, 'user.role_changed', ['role' => $data['role']]);
        });

        return redirect()->route('users.index')->with('success', 'Peran '.$target->name.' diperbarui.');
    }

    public function destroy(Request $request, int $user, EntityContext $context)
    {
        abort_unless($context->canManageUsers(), 403);
        $target = $this->member($user, $context);
        if ($target->id === $request->user()->id) { $this->fail('Anda tidak dapat mencabut akses akun sendiri.'); }
        DB::transaction(function () use ($target, $context, $request) {
            $target->entities()->detach($context->entity->id);
            $this->log($context, $request->user(), $target, 'user.removed');
        });

        return redirect()->route('users.index')->with('success', 'Akses '.$target->name.' ke perusahaan ini dicabut. Riwayat tetap tersimpan.');
    }

    public function password(Request $request, int $user, EntityContext $context)
    {
        $target = $this->resettable($request, $user, $context);
        $data = $request->validate(['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]]);
        DB::transaction(function () use ($target, $data, $context, $request) {
            $target->forceFill(['password' => $data['password'], 'mfa_epoch' => (string) Str::uuid()])->save();
            $this->log($context, $request->user(), $target, 'user.password_reset');
        });

        return redirect()->route('users.index')->with('success', 'Kata sandi '.$target->name.' direset. Sampaikan secara langsung dan minta pengguna segera menggantinya.');
    }

    public function mfa(Request $request, int $user, EntityContext $context)
    {
        $target = $this->resettable($request, $user, $context);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']], [], ['reason' => 'alasan reset']);
        DB::transaction(function () use ($target, $data, $context, $request) {
            $target->forceFill(['mfa_secret' => null, 'mfa_recovery_hashes' => null, 'mfa_confirmed_at' => null, 'mfa_last_counter' => null, 'mfa_epoch' => (string) Str::uuid()])->save();
            \App\Models\SecurityEvent::create(['user_id' => $target->id, 'action' => 'mfa.reset_by_admin', 'metadata' => ['reason' => $data['reason'], 'by' => $request->user()->id], 'created_at' => now()]);
            $this->log($context, $request->user(), $target, 'user.mfa_reset', ['reason' => $data['reason']]);
        });

        return redirect()->route('users.index')->with('success', 'MFA '.$target->name.' direset. Pengguna mendaftarkan authenticator kembali saat login berikutnya.');
    }

    public function entity(Request $request, EntityContext $context)
    {
        abort_unless($context->canManageUsers(), 403);
        $data = $request->validate([
            'entity_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('entities', 'code')],
            'entity_name' => ['required', 'string', 'max:255'],
            'is_bumn' => ['nullable', 'boolean'], 'is_listed' => ['nullable', 'boolean'],
        ], [], ['entity_code' => 'kode perusahaan', 'entity_name' => 'nama perusahaan']);
        $entity = DB::transaction(function () use ($data, $request) {
            $entity = Entity::create(['code' => $data['entity_code'], 'name' => $data['entity_name'], 'is_bumn' => (bool) ($data['is_bumn'] ?? false),
                'is_public_company' => (bool) ($data['is_listed'] ?? false), 'is_listed' => (bool) ($data['is_listed'] ?? false)]);
            $request->user()->entities()->attach($entity->id, ['role' => 'admin']);

            return $entity;
        });
        $request->session()->put('entity_id', $entity->id);

        return redirect()->route('users.index')->with('success', 'Perusahaan '.$entity->name.' dibuat dan sekarang aktif. Anda menjadi administratornya.');
    }

    private function fail(string $message): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages(['user' => $message]);
    }

    private function member(int $id, EntityContext $context): User
    {
        return User::where('is_demo_guest', false)->whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id))->findOrFail($id);
    }

    /** Reset kata sandi/MFA hanya bila admin juga administrator di semua perusahaan milik pengguna itu. */
    private function resettable(Request $request, int $id, EntityContext $context): User
    {
        abort_unless($context->canManageUsers(), 403);
        $target = $this->member($id, $context);
        if ($target->id === $request->user()->id) { $this->fail('Gunakan menu Keamanan untuk akun sendiri.'); }
        $adminOf = $request->user()->entities()->wherePivot('role', 'admin')->pluck('entities.id');
        abort_unless($target->entities()->pluck('entities.id')->diff($adminOf)->isEmpty(), 403, 'Pengguna ini juga terdaftar di perusahaan lain yang bukan wewenang Anda. Minta administrator perusahaan tersebut atau operator server.');

        return $target;
    }

    private function log(EntityContext $context, User $actor, User $target, string $action, array $meta = []): void
    {
        AuditEvent::create(['entity_id' => $context->entity->id, 'actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'users',
            'subject_id' => $target->id, 'before' => null, 'after' => ['name' => $target->name, 'email' => $target->email] + $meta, 'created_at' => now()]);
    }
}
