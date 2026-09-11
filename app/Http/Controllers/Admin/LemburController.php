<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkLockLembursRequest;
use App\Models\Lembur;
use App\Services\Admin\AdminLemburPresenter;
use App\Services\Admin\LemburLockService;
use App\Services\Admin\LemburPdfExporter;
use App\Services\Admin\LemburQuery;
use App\Services\Admin\PegawaiQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LemburController extends Controller
{
    public function index(
        Request $request,
        LemburQuery $lemburQuery,
        PegawaiQuery $pegawaiQuery,
        AdminLemburPresenter $presenter,
    ): Response {

        $filters = $lemburQuery->filters($request->query());
        $lemburs = $lemburQuery->forAdmin($filters)
            ->paginate(15)
            ->withQueryString()
            ->through(fn(Lembur $lembur): array => $presenter->summary($lembur));

        return Inertia::render('admin/lemburs/index', [
            'lemburs' => $lemburs,
            'filters' => $filters,
            'pegawaiOptions' => $pegawaiQuery->activeOptions(),
        ]);
    }

    public function show(Lembur $lembur, AdminLemburPresenter $presenter): Response
    {
        $lembur->loadMissing(['user', 'lockedBy']);

        return Inertia::render('admin/lemburs/show', [
            'lembur' => $presenter->detail($lembur),
        ]);
    }

    public function destroy(Lembur $lembur): RedirectResponse
    {
        if ($lembur->status === 'locked') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Data lembur yang terkunci tidak dapat dihapus.',
            ]);

            return back();
        }

        Storage::disk('public')->delete(array_filter([
            $lembur->foto_kegiatan,
            $lembur->foto_pulang,
        ]));

        $lembur->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data lembur berhasil dihapus.',
        ]);

        return back();
    }

    public function lock(
        Request $request,
        Lembur $lembur,
        LemburLockService $lockService,
    ): RedirectResponse {
        $lockService->lock($request->user(), [$lembur->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data lembur berhasil dikunci.',
        ]);

        return back();
    }

    public function bulkLock(
        BulkLockLembursRequest $request,
        LemburLockService $lockService,
    ): RedirectResponse {
        $count = $lockService->lock($request->user(), $request->validated('ids'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count . ' data lembur berhasil dikunci.',
        ]);

        return back();
    }

    public function export(
        Request $request,
        LemburQuery $lemburQuery,
        LemburPdfExporter $exporter,
    ): StreamedResponse {
        $filters = $lemburQuery->filters($request->query());
        $lemburs = $lemburQuery->forAdmin($filters)->get();

        return response()->streamDownload(
            fn() => print($exporter->render($lemburs, $filters)),
            'lembur-' . $filters['bulan'] . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
