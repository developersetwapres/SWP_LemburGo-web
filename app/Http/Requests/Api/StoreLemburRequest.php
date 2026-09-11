<?php

namespace App\Http\Requests\Api;

use App\Models\Lembur;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreLemburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'tanggal_kegiatan' => [
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $alreadySubmitted = Lembur::query()
                        ->where('user_id', $this->user()->id)
                        ->whereDate('tanggal_kegiatan', $value)
                        ->exists();

                    if ($alreadySubmitted) {
                        $fail('Data lembur untuk tanggal tersebut sudah tersedia.');
                    }
                },
            ],
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'lokasi_kegiatan' => ['required', 'string', 'max:255'],

            'foto_kegiatan' => ['nullable', 'image', 'max:10240'],
            'foto_kegiatan_at' => ['nullable', 'date'],

            'foto_pulang' => ['nullable', 'image', 'max:10240'],
            'foto_pulang_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_kegiatan.required' => 'Tanggal kegiatan wajib diisi.',
            'tanggal_kegiatan.date' => 'Tanggal kegiatan harus berupa tanggal yang valid.',
            'tanggal_kegiatan.unique' => 'Data lembur untuk tanggal tersebut sudah tersedia.',

            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
            'nama_kegiatan.string' => 'Nama kegiatan harus berupa teks.',
            'nama_kegiatan.max' => 'Nama kegiatan maksimal 255 karakter.',

            'lokasi_kegiatan.required' => 'Lokasi kegiatan wajib diisi.',
            'lokasi_kegiatan.string' => 'Lokasi kegiatan harus berupa teks.',
            'lokasi_kegiatan.max' => 'Lokasi kegiatan maksimal 255 karakter.',

            'foto_kegiatan.image' => 'Foto kegiatan harus berupa gambar.',
            'foto_kegiatan.max' => 'Ukuran foto kegiatan maksimal 10 MB.',
            'foto_kegiatan_at.date' => 'Waktu foto kegiatan harus berupa tanggal dan waktu yang valid.',

            'foto_pulang.image' => 'Foto pulang harus berupa gambar.',
            'foto_pulang.max' => 'Ukuran foto pulang maksimal 10 MB.',
            'foto_pulang_at.date' => 'Waktu foto pulang harus berupa tanggal dan waktu yang valid.',
        ];
    }
}
