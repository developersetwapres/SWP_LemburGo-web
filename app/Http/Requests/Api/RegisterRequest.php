<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Contracts\Validation\Validator;

class RegisterRequest extends FormRequest
{
    use JsonResponseTrait;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],

            'email' => [
                'required',
                'string',
                // 'email:rfc,dns',
                'email:rfc',
                'max:150',
                'unique:users,email',
            ],

            'phone' => [
                'required',
                'string',
                'min:10',
                'max:20',
                'regex:/^62[0-9]{9,18}$/',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],

            'admission_path_id' => [
                'required',
                'integer',
                'exists:master_admission_paths,id',
            ],

            'class_schedule_id' => [
                'required',
                'integer',
                'exists:master_class_schedules,id',
            ],

            'study_program_id' => [
                'required',
                'integer',
                'exists:master_study_programs,id',
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama wajib diisi.',
            'full_name.min' => 'Nama minimal 3 karakter.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',

            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.regex' => 'Nomor HP hanya boleh berisi angka.',
            'phone.min' => 'Nomor HP minimal 10 digit.',

            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',

            'admission_path_id.required' => 'Jalur penerimaan wajib diisi.',
            'class_schedule_id.required' => 'Jadwal kelas wajib diisi.',
            'study_program_id.required' => 'Program studi wajib diisi.',

            'admission_path_id.exists' => 'Jalur penerimaan tidak valid.',
            'class_schedule_id.exists' => 'Jadwal kelas tidak valid.',
            'study_program_id.exists' => 'Program studi tidak valid.',
        ];
    }

    /**
     * Prepare data before validation.
     */
    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\s+/', '', (string) $this->phone);

        if (str_starts_with($phone, '+62')) {
            $phone = '62' . substr($phone, 3);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $this->merge([
            'email' => strtolower(trim((string) $this->email)),
            'phone' => $phone,
            'full_name' => trim((string) $this->full_name),
        ]);
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            self::error(
                'Validasi gagal',
                $validator->errors(),
                422
            )
        );
    }
}
