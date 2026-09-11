<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPegawaiUpdateRequest;
use App\Models\Lembur;
use App\Models\User;
use App\Services\Admin\AdminLemburPresenter;
use App\Services\Admin\LemburQuery;
use App\Services\Admin\PegawaiQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PegawaiController extends Controller
{
    public function index(Request $request, PegawaiQuery $pegawaiQuery): Response
    {
        $filters = $pegawaiQuery->filters($request->query());

        return Inertia::render('admin/pegawais/index', [
            'filters' => $filters,
            'pegawai' => $pegawaiQuery->forAdmin($filters)
                ->paginate(15)
                ->withQueryString()
                ->through(fn(User $user): array => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'jabatan' => $user->jabatan,
                    'nip' => $user->nip,
                    'kode_biro' => $user->kode_biro,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'lemburs_count' => $user->lemburs_count,
                ]),
        ]);
    }

    public function update(AdminPegawaiUpdateRequest $request, User $pegawai): RedirectResponse
    {
        abort_unless($pegawai->hasRole('outsourcing'), 404);

        $payload = $request->validated();

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('status', $payload)) {
            $payload['is_active'] = $payload['status'] === 'active';
            unset($payload['status']);
        }

        if (! empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        }

        $pegawai->fill(array_intersect_key($payload, array_flip([
            'name',
            'jabatan',
            'nip',
            'is_active',
            'password',
        ])));

        $pegawai->save();

        return redirect()->route('admin.pegawai.index')->with('success', 'Data pegawai berhasil diperbarui.');
    }

    public function show(
        Request $request,
        User $pegawai,
        LemburQuery $lemburQuery,
        AdminLemburPresenter $presenter,
    ): Response {
        abort_unless($pegawai->hasRole('outsourcing'), 404);

        $filters = $lemburQuery->filters([
            ...$request->query(),
            'pegawai' => $pegawai->uuid,
        ]);
        $history = $lemburQuery->forAdmin($filters)
            ->paginate(10)
            ->withQueryString()
            ->through(fn(Lembur $lembur): array => $presenter->summary($lembur));

        return Inertia::render('admin/pegawais/show', [
            'pegawai' => [
                'uuid' => $pegawai->uuid,
                'name' => $pegawai->name,
                'jabatan' => $pegawai->jabatan,
                'nip' => $pegawai->nip,
                'kode_biro' => $pegawai->kode_biro,
                'is_active' => $pegawai->is_active,
                'image' => $pegawai->image,
            ],
            'history' => $history,
            'filters' => $filters,
        ]);
    }
}
