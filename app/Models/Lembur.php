<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\LemburFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property Carbon $tanggal_kegiatan
 * @property string $nama_kegiatan
 * @property string $lokasi_kegiatan
 * @property string|null $foto_kegiatan
 * @property Carbon|null $foto_kegiatan_at
 * @property string|null $foto_pulang
 * @property Carbon|null $foto_pulang_at
 * @property Carbon|null $waktu_pulang
 * @property string $status
 * @property Carbon|null $locked_at
 * @property int|null $locked_by
 * @property-read User $user
 * @property-read User|null $lockedBy
 */
#[Fillable([
    'user_id',
    'tanggal_kegiatan',
    'nama_kegiatan',
    'lokasi_kegiatan',
    'foto_kegiatan',
    'foto_kegiatan_at',
    'foto_pulang',
    'foto_pulang_at',
    'waktu_pulang',
    'status',
    'locked_at',
    'locked_by',
])]

class Lembur extends Model
{
    /** @use HasFactory<LemburFactory> */
    use HasFactory, HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    protected function casts(): array
    {
        return [
            'tanggal_kegiatan' => 'date',
            'foto_kegiatan_at' => 'datetime',
            'foto_pulang_at' => 'datetime',
            'waktu_pulang' => 'datetime:H:i',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Lembur>  $query
     * @return Builder<Lembur>
     */
    public function scopeComplete(Builder $query): Builder
    {
        return $query->where('status', 'complete');
    }

    public function canBeLocked(): bool
    {
        return $this->status === 'complete' && $this->locked_at === null;
    }

    public function isHariLibur(): bool
    {
        return $this->tanggal_kegiatan->isWeekend();
    }
}
