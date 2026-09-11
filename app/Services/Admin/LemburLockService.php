<?php

namespace App\Services\Admin;

use App\Models\Lembur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LemburLockService
{
    /**
     * @param  array<int, int>  $ids
     */
    public function lock(User $admin, array $ids): int
    {
        $ids = array_values(array_unique($ids));

        return DB::transaction(function () use ($admin, $ids): int {
            $lemburs = Lembur::query()
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($lemburs->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => 'Satu atau lebih data lembur tidak ditemukan.',
                ]);
            }

            if ($lemburs->contains(fn (Lembur $lembur): bool => ! $lembur->canBeLocked())) {
                throw ValidationException::withMessages([
                    'ids' => 'Hanya data lembur berstatus complete yang belum dikunci dapat dikunci.',
                ]);
            }

            $lockedAt = now();

            $lemburs->each(function (Lembur $lembur) use ($admin, $lockedAt): void {
                $lembur->forceFill([
                    'status' => 'locked',
                    'locked_at' => $lockedAt,
                    'locked_by' => $admin->id,
                ])->save();
            });

            return $lemburs->count();
        });
    }
}
