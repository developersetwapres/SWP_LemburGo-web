<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PegawaiQuery
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{search: string, status: string|null}
     */
    public function filters(array $input): array
    {
        $status = trim((string) ($input['status'] ?? ''));

        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'status' => $status === '' ? null : $status,
        ];
    }

    /** @param array<string, mixed> $input */
    public function perPage(array $input, int $default = 15): int
    {
        $perPage = filter_var($input['per_page'] ?? null, FILTER_VALIDATE_INT);

        return $perPage === false ? $default : min(max($perPage, 1), 100);
    }

    /**
     * @param  array{search: string, status: string|null}  $filters
     * @return Builder<User>
     */
    public function forAdmin(array $filters): Builder
    {
        return User::query()
            ->outsourcing()
            ->withCount('lemburs')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('nip', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('jabatan', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('kode_biro', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== null, function (Builder $query) use ($filters): void {
                $status = $filters['status'];

                if ($status === 'active') {
                    $query->where('is_active', true);
                }

                if ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->orderBy('name');
    }

    /** @return array<int, array{uuid: string, name: string, nip: string|null}> */
    public function activeOptions(): array
    {
        return User::query()
            ->outsourcing()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['uuid', 'name', 'nip'])
            ->toBase()
            ->map(fn(User $user): array => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'nip' => $user->nip,
            ])
            ->values()
            ->all();
    }
}
