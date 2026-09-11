<?php

namespace App\Services\Admin;

use App\Models\Lembur;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class LemburQuery
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{bulan: string, pegawai: string|null, status: string, jenis_hari: string, search: string}
     */
    public function filters(array $input): array
    {
        $bulan = is_string($input['bulan'] ?? null) ? $input['bulan'] : '';

        return [
            'bulan' => $this->normalizeBulan($bulan),
            'pegawai' => filled($input['pegawai'] ?? null) ? (string) $input['pegawai'] : null,
            'status' => in_array($input['status'] ?? null, ['draft', 'complete', 'locked'], true)
                ? $input['status']
                : 'complete',
            'jenis_hari' => in_array($input['jenis_hari'] ?? null, ['kerja', 'libur'], true)
                ? $input['jenis_hari']
                : 'semua',
            'search' => trim((string) ($input['search'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $input */
    public function perPage(array $input, int $default = 15): int
    {
        $perPage = filter_var($input['per_page'] ?? null, FILTER_VALIDATE_INT);

        return $perPage === false ? $default : min(max($perPage, 1), 100);
    }

    /**
     * @param  Builder<Lembur>  $query
     * @param  array{bulan: string, pegawai: string|null, status: string, jenis_hari: string, search: string}  $filters
     * @return Builder<Lembur>
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $month = CarbonImmutable::parse($filters['bulan'].'-01');

        $query
            ->whereBetween('tanggal_kegiatan', [$month->startOfMonth(), $month->endOfMonth()])
            ->where('status', $filters['status']);

        if ($filters['pegawai']) {
            $query->whereHas('user', function (Builder $userQuery) use ($filters): void {
                $userQuery->where('uuid', $filters['pegawai']);

                if (ctype_digit($filters['pegawai'])) {
                    $userQuery->orWhereKey((int) $filters['pegawai']);
                }
            });
        }

        if ($filters['search'] !== '') {
            $query->where(function (Builder $searchQuery) use ($filters): void {
                $searchQuery
                    ->where('nama_kegiatan', 'like', '%'.$filters['search'].'%')
                    ->orWhere('lokasi_kegiatan', 'like', '%'.$filters['search'].'%');
            });
        }

        return $this->applyJenisHari($query, $filters['jenis_hari']);
    }

    /**
     * @param  Builder<Lembur>  $query
     * @return Builder<Lembur>
     */
    public function applyJenisHari(Builder $query, string $jenisHari): Builder
    {
        if ($jenisHari === 'semua') {
            return $query;
        }

        return match ($jenisHari) {
            'libur' => match ($this->databaseDriver()) {
                'sqlite' => $query->whereRaw("strftime('%w', tanggal_kegiatan) IN ('0', '6')"),
                'pgsql' => $query->whereRaw('EXTRACT(DOW FROM tanggal_kegiatan) IN (0, 6)'),
                'sqlsrv' => $query->whereRaw('DATEPART(WEEKDAY, tanggal_kegiatan) IN (1, 7)'),
                default => $query->whereRaw('DAYOFWEEK(tanggal_kegiatan) IN (1, 7)'),
            },
            default => match ($this->databaseDriver()) {
                'sqlite' => $query->whereRaw("NOT (strftime('%w', tanggal_kegiatan) IN ('0', '6'))"),
                'pgsql' => $query->whereRaw('NOT (EXTRACT(DOW FROM tanggal_kegiatan) IN (0, 6))'),
                'sqlsrv' => $query->whereRaw('NOT (DATEPART(WEEKDAY, tanggal_kegiatan) IN (1, 7))'),
                default => $query->whereRaw('NOT (DAYOFWEEK(tanggal_kegiatan) IN (1, 7))'),
            },
        };
    }

    /**
     * @param  array{bulan: string, pegawai: string|null, status: string, jenis_hari: string, search: string}  $filters
     * @return Builder<Lembur>
     */
    public function forAdmin(array $filters): Builder
    {
        return $this->apply(
            Lembur::query()->with('user:id,uuid,name,nip,jabatan,kode_biro,image'),
            $filters,
        )->orderByDesc('tanggal_kegiatan')->orderByDesc('id');
    }

    private function normalizeBulan(string $bulan): string
    {
        if (preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $bulan) === 1) {
            return $bulan;
        }

        if (preg_match('/^(0?[1-9]|1[0-2])$/', $bulan) === 1) {
            return now()->format('Y-').str_pad($bulan, 2, '0', STR_PAD_LEFT);
        }

        return now()->format('Y-m');
    }

    public function databaseDriver(): string
    {
        $connection = Lembur::query()->getModel()->getConnectionName() ?? config('database.default');

        return (string) config('database.connections.'.$connection.'.driver');
    }
}
