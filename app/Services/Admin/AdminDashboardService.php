<?php

namespace App\Services\Admin;

use App\Models\Lembur;
use App\Services\Api\LemburService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function __construct(
        private LemburService $lemburService,
        private LemburQuery $lemburQuery,
    ) {}

    /** @return array<string, mixed> */
    public function forMonth(string $bulan): array
    {
        $month = CarbonImmutable::parse($bulan.'-01');
        $query = Lembur::complete()->whereBetween('tanggal_kegiatan', [
            $month->startOfMonth(),
            $month->endOfMonth(),
        ]);

        $hariLibur = $this->lemburQuery->applyJenisHari(clone $query, 'libur')->count();
        $hariKerja = $this->lemburQuery->applyJenisHari(clone $query, 'kerja')->count();

        return [
            'total_lembur' => $hariKerja + $hariLibur,
            'total_upah' => $this->lemburService->totalUpahDariRingkasan($hariKerja, $hariLibur),
            'hari_kerja' => $hariKerja,
            'hari_libur' => $hariLibur,
            'bulan' => $bulan,
            'chart' => $this->chartForYear($month),
        ];
    }

    /** @return array<int, array{month: int, total: int}> */
    private function chartForYear(CarbonImmutable $month): array
    {
        $start = $month->startOfYear();
        $end = $month->endOfYear();
        $expression = match ($this->lemburQuery->databaseDriver()) {
            'sqlite' => "strftime('%m', tanggal_kegiatan)",
            'pgsql' => 'EXTRACT(MONTH FROM tanggal_kegiatan)',
            'sqlsrv' => 'MONTH(tanggal_kegiatan)',
            default => 'MONTH(tanggal_kegiatan)',
        };

        /** @var Collection<int, int> $totals */
        $totals = Lembur::complete()
            ->whereBetween('tanggal_kegiatan', [$start, $end])
            ->selectRaw($expression.' as month, count(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        return collect(range(1, 12))
            ->map(fn (int $index): array => [
                'month' => $index,
                'total' => (int) ($totals->get($index) ?? 0),
            ])
            ->all();
    }
}
