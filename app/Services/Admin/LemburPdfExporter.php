<?php

namespace App\Services\Admin;

use App\Models\Lembur;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LemburPdfExporter
{
    /**
     * @param  Enumerable<int, Lembur>  $lemburs
     * @param  array{bulan: string, pegawai: string|null, status: string, jenis_hari: string, search: string}  $filters
     */
    public function render(Enumerable $lemburs, array $filters): string
    {
        $month = CarbonImmutable::make($filters['bulan'].'-01');

        if (! $month instanceof CarbonImmutable) {
            throw new \LogicException('Bulan ekspor lembur tidak valid.');
        }

        $month->locale('id');

        $pdf = Pdf::loadView('pdf.lembur-rekap', [
            'bulan' => strtoupper($month->translatedFormat('F')),
            'tahun' => $month->year,
            'lemburs' => $lemburs->map(fn (Lembur $lembur): array => $this->viewData($lembur))->all(),
        ])
            ->setPaper('a4', 'landscape')
            ->setWarnings(false);

        $photoStorageRoot = $this->photoStorageRoot();

        if ($photoStorageRoot !== null) {
            $pdf->setOption('chroot', [base_path(), $photoStorageRoot]);
        }

        $pdf->render();

        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getCanvas()->page_text(734, 572, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 7, [0, 0, 0]);

        return $pdf->output(['compress' => 0]);
    }

    /**
     * @return array{tanggal: string, nama_lengkap: string, kegiatan: string, lokasi: string, foto_eviden: string|null, foto_presensi_pulang: string|null, waktu_kepulangan: string}
     */
    private function viewData(Lembur $lembur): array
    {
        return [
            'tanggal' => $lembur->tanggal_kegiatan->format('d-m-Y'),
            'nama_lengkap' => $lembur->user->name,
            'kegiatan' => $lembur->nama_kegiatan,
            'lokasi' => $lembur->lokasi_kegiatan,
            'foto_eviden' => $this->photoFileUri($lembur->foto_kegiatan),
            'foto_presensi_pulang' => $this->photoFileUri($lembur->foto_pulang),
            'waktu_kepulangan' => $lembur->waktu_pulang?->format('H:i') ?? '-',
        ];
    }

    private function photoFileUri(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($path)) {
                return null;
            }

            $photoPath = realpath($disk->path($path));
            $storageRoot = $this->photoStorageRoot();
        } catch (Throwable) {
            return null;
        }

        if ($photoPath === false || $storageRoot === null || ! str_starts_with($photoPath, $storageRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return 'file://'.$photoPath;
    }

    private function photoStorageRoot(): ?string
    {
        try {
            $root = realpath(Storage::disk('public')->path(''));
        } catch (Throwable) {
            return null;
        }

        return $root === false ? null : $root;
    }
}
