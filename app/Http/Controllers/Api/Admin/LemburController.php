<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLemburIndexRequest;
use App\Http\Requests\Admin\BulkLockLembursRequest;
use App\Http\Resources\Admin\AdminBulkLockResultResource;
use App\Http\Resources\Admin\AdminLemburResource;
use App\Models\Lembur;
use App\Services\Admin\LemburLockService;
use App\Services\Admin\LemburPdfExporter;
use App\Services\Admin\LemburQuery;
use App\Services\Admin\PegawaiQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LemburController extends Controller
{
    public function index(
        AdminLemburIndexRequest $request,
        LemburQuery $lemburQuery,
        PegawaiQuery $pegawaiQuery,
    ): AnonymousResourceCollection {
        $filters = $lemburQuery->filters($request->validated());
        $lemburs = $lemburQuery->forAdmin($filters)
            ->paginate($lemburQuery->perPage($request->validated()))
            ->withQueryString();

        return AdminLemburResource::collection($lemburs)
            ->additional(['meta' => [
                'filters' => $filters,
                'pegawaiOptions' => $pegawaiQuery->activeOptions(),
            ]]);
    }

    public function destroy(Lembur $lembur): Response
    {
        abort_if($lembur->status === 'locked', 403, 'Data lembur yang terkunci tidak dapat dihapus.');

        Storage::disk('public')->delete(array_filter([
            $lembur->foto_kegiatan,
            $lembur->foto_pulang,
        ]));

        $lembur->delete();

        return response()->noContent();
    }

    public function show(Lembur $lembur): AdminLemburResource
    {
        $lembur->loadMissing(['user', 'lockedBy']);

        return new AdminLemburResource($lembur);
    }

    public function lock(
        Request $request,
        Lembur $lembur,
        LemburLockService $lockService,
    ): AdminLemburResource {
        $lockService->lock($request->user(), [$lembur->id]);

        $lembur->refresh()->load(['user', 'lockedBy']);

        return new AdminLemburResource($lembur);
    }

    public function bulkLock(
        BulkLockLembursRequest $request,
        LemburLockService $lockService,
    ): AdminBulkLockResultResource {
        $count = $lockService->lock($request->user(), $request->validated('ids'));

        return new AdminBulkLockResultResource([
            'request_id' => (string) Str::uuid(),
            'locked_count' => $count,
        ]);
    }

    public function export(
        AdminLemburIndexRequest $request,
        LemburQuery $lemburQuery,
        LemburPdfExporter $exporter,
    ): StreamedResponse {
        $filters = $lemburQuery->filters($request->validated());
        $lemburs = $lemburQuery->forAdmin($filters)->get();

        return response()->streamDownload(
            fn () => print ($exporter->render($lemburs, $filters)),
            'lembur-'.$filters['bulan'].'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
