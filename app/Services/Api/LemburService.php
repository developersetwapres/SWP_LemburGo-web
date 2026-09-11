<?php

namespace App\Services\Api;

use App\Models\Lembur;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LemburService
{
    public function hitungUpah(Lembur|Collection|array $lemburs): int
    {
        $items = $lemburs instanceof Lembur ? [$lemburs] : $lemburs;

        $total = 0;

        foreach ($items as $lembur) {
            if (! $lembur instanceof Lembur || ! $lembur->tanggal_kegiatan) {
                continue;
            }

            $total += $lembur->tanggal_kegiatan->isWeekend() ? 100000 : 50000;
        }

        return $total;
    }

    public function ringkasanUpah(Lembur|Collection|array $lemburs): array
    {
        $items = $lemburs instanceof Lembur ? [$lemburs] : $lemburs;

        $hariKerja = 0;
        $hariLibur = 0;
        $totalUpah = 0;

        foreach ($items as $lembur) {
            if (! $lembur instanceof Lembur || ! $lembur->tanggal_kegiatan) {
                continue;
            }

            if ($lembur->tanggal_kegiatan->isWeekend()) {
                $hariLibur++;
                $totalUpah += 100000;

                continue;
            }

            $hariKerja++;
            $totalUpah += 50000;
        }

        return [
            'lembur_hari_kerja' => $hariKerja,
            'lembur_hari_libur' => $hariLibur,
            'total_lembur' => $hariKerja + $hariLibur,
            'total_upah' => $totalUpah,
        ];
    }

    public function totalUpahDariRingkasan(int $hariKerja, int $hariLibur): int
    {
        return ($hariKerja * 50000) + ($hariLibur * 100000);
    }

    public function store(array $data): Lembur
    {
        return DB::transaction(function () use ($data) {
            $lembur = new Lembur;

            $lembur->user_id = Auth::id();
            $lembur->tanggal_kegiatan = $data['tanggal_kegiatan'];
            $lembur->nama_kegiatan = $data['nama_kegiatan'];
            $lembur->lokasi_kegiatan = $data['lokasi_kegiatan'];
            $lembur->status = 'draft';

            $this->handleFotoKegiatan($lembur, $data);
            $this->handleFotoPulang($lembur, $data);
            $this->updateStatus($lembur);

            $lembur->save();

            return $lembur;
        });
    }

    public function update(Lembur $lembur, array $data): ?Lembur
    {
        return DB::transaction(function () use ($lembur, $data) {
            $lembur->tanggal_kegiatan = $data['tanggal_kegiatan'];
            $lembur->nama_kegiatan = $data['nama_kegiatan'];
            $lembur->lokasi_kegiatan = $data['lokasi_kegiatan'];

            $this->handleFotoKegiatan($lembur, $data);
            $this->handleFotoPulang($lembur, $data);
            $this->updateStatus($lembur);

            $lembur->save();

            return $lembur;
        });
    }

    private function handleFotoKegiatan(Lembur $lembur, array $data): void
    {
        if (! isset($data['foto_kegiatan'])) {
            return;
        }

        $lembur->foto_kegiatan = $this->storeFoto(
            $data['foto_kegiatan'],
            'lembur/kegiatan',
            $lembur->foto_kegiatan,
        );

        $lembur->foto_kegiatan_at = $data['foto_kegiatan_at'] ?? now();
    }

    private function handleFotoPulang(Lembur $lembur, array $data): void
    {
        if (! isset($data['foto_pulang'])) {
            return;
        }

        $lembur->foto_pulang = $this->storeFoto(
            $data['foto_pulang'],
            'lembur/pulang',
            $lembur->foto_pulang,
        );

        $lembur->foto_pulang_at = $data['foto_pulang_at'] ?? now();

        // Waktu pulang mengikuti timestamp foto pulang
        $lembur->waktu_pulang = $lembur->foto_pulang_at?->format('H:i:s');
    }

    private function updateStatus(Lembur $lembur): void
    {
        $requiredAttributes = [
            'foto_kegiatan',
            'foto_kegiatan_at',
            'foto_pulang',
            'foto_pulang_at',
            'waktu_pulang',
        ];

        $lembur->status = collect($requiredAttributes)
            ->every(fn (string $attribute): bool => filled($lembur->getAttribute($attribute)))
            ? 'complete'
            : 'draft';
    }

    private function storeFoto(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        $newPath = $file->store($directory, 'public');

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $newPath;
    }
}
