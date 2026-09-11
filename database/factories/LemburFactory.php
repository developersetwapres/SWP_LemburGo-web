<?php

namespace Database\Factories;

use App\Models\Lembur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lembur>
 */
class LemburFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tanggal_kegiatan' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'nama_kegiatan' => fake()->sentence(4),
            'lokasi_kegiatan' => fake()->city(),
            'status' => 'complete',
            'foto_kegiatan' => 'lembur/kegiatan/contoh.jpg',
            'foto_kegiatan_at' => now()->subHours(3),
            'foto_pulang' => 'lembur/pulang/contoh.jpg',
            'foto_pulang_at' => now(),
            'waktu_pulang' => '20:00:00',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => 'draft',
            'foto_pulang' => null,
            'foto_pulang_at' => null,
            'waktu_pulang' => null,
        ]);
    }
}
