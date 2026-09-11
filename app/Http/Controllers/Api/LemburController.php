<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLemburRequest;
use App\Http\Requests\Api\UpdateLemburRequest;
use App\Http\Resources\Api\LemburResource;
use App\Models\Lembur;
use App\Services\Admin\LemburPdfExporter;
use App\Services\Api\LemburService;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LemburController extends Controller
{
    use JsonResponseTrait;

    public function __construct(
        private LemburService $lemburService
    ) {}

    public function index(Request $request)
    {
        $bulan = $request->query('bulan', now()->month);

        $lemburs = Lembur::query()
            ->where('user_id', Auth::id())
            ->where(function ($query) {
                $query->whereNotNull('foto_kegiatan')
                    ->whereNotNull('foto_pulang');
            })
            ->whereMonth('tanggal_kegiatan', $bulan)
            ->orderByDesc('tanggal_kegiatan')
            ->get();

        $ringkasan = $this->lemburService->ringkasanUpah($lemburs);

        return LemburResource::collection($lemburs)
            ->additional([
                'meta' => [
                    'bulan' => (int) $bulan,
                    'lembur_hari_kerja' => $ringkasan['lembur_hari_kerja'],
                    'lembur_hari_libur' => $ringkasan['lembur_hari_libur'],
                    'total_lembur' => $ringkasan['total_lembur'],
                    'total_upah' => $ringkasan['total_upah'],
                ],
            ]);
    }

    public function export(Request $request, LemburPdfExporter $exporter): StreamedResponse
    {
        $bulan = $request->query('bulan', now()->format('Y-m'));
        $lemburs = Lembur::query()
            ->with('user')
            ->where('user_id', Auth::id())
            ->where('status', 'complete')
            ->whereNotNull('foto_kegiatan')
            ->whereNotNull('foto_pulang')
            ->whereYear('tanggal_kegiatan', substr($bulan, 0, 4))
            ->whereMonth('tanggal_kegiatan', substr($bulan, 5, 2))
            ->orderByDesc('tanggal_kegiatan')
            ->get();

        return response()->streamDownload(
            fn () => print ($exporter->render($lemburs, [
                'bulan' => $bulan,
                'pegawai' => null,
                'status' => 'complete',
                'jenis_hari' => 'semua',
                'search' => '',
            ])),
            'lembur-'.$bulan.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function show(Lembur $lembur)
    {
        if ($lembur->user_id !== Auth::id()) {
            return $this->error('Lembur tidak ditemukan.', null, 404);
        }

        return new LemburResource($lembur);
    }

    public function store(StoreLemburRequest $request)
    {

        $lembur = $this->lemburService->store(
            $request->validated()
        );

        if (! $lembur) {
            return $this->error('Lembur gagal disimpan.', null, 500);
        }

        return $this->success('Lembur berhasil disimpan.', [
            'uuid' => $lembur->uuid,
        ]);
    }

    public function update(UpdateLemburRequest $request, Lembur $lembur)
    {
        $lembur = $this->lemburService->update(
            $lembur,
            $request->validated()
        );

        if (! $lembur) {
            return $this->error('Lembur tidak ditemukan atau tidak dapat diperbarui.', null, 404);
        }

        return $this->success('Lembur berhasil diperbarui.', [
            'uuid' => $lembur->uuid,
        ]);
    }

    public function draft(): AnonymousResourceCollection
    {
        $lemburs = Lembur::where('user_id', Auth::id())
            ->where(function (Builder $query): void {
                $query->whereNull('foto_kegiatan')->orWhereNull('foto_pulang');
            })
            ->orderByDesc('tanggal_kegiatan')
            ->get();

        return LemburResource::collection($lemburs);
    }

    public function hitungUpah(Lembur $lembur): JsonResponse
    {
        abort_unless($lembur->user_id === Auth::id(), 404);

        return response()->json(['data' => ['upah' => $this->lemburService->hitungUpah($lembur)]]);
    }

    public function kalender()
    {
        $lemburs = Lembur::where('user_id', Auth::id())
            ->whereNotNull('foto_kegiatan')
            ->whereNotNull('foto_pulang')
            ->orderByDesc('tanggal_kegiatan')
            ->get()
            ->map(fn ($lembur) => [
                'tanggal' => $lembur->tanggal_kegiatan->format('Y-m-d'),
                'uuid' => $lembur->uuid,
                'lembur_id' => $lembur->id,
            ]);

        return response()->json([
            'data' => $lemburs,
        ]);
    }

    public function totalUpahLembur()
    {
        $lemburs = Lembur::where('user_id', Auth::id())
            ->whereNotNull('foto_kegiatan')
            ->whereNotNull('foto_pulang')
            ->whereYear('tanggal_kegiatan', now()->year)
            ->get();

        $totalUpah = $this->lemburService->ringkasanUpah($lemburs);

        return response()->json([
            'data' => $totalUpah,
        ]);
    }

    public function destroy(Lembur $lembur)
    {
        if ($lembur->user_id !== Auth::id()) {
            return $this->error('Lembur tidak ditemukan.', null, 404);
        }

        if ($lembur->status === 'locked') {
            return $this->error('Lembur terkunci dan tidak dapat dihapus.', null, 403);
        }

        Storage::disk('public')->delete(array_filter([
            $lembur->foto_kegiatan,
            $lembur->foto_pulang,
        ]));

        $lembur->delete();

        return $this->success('Lembur berhasil dihapus.');
    }
}
