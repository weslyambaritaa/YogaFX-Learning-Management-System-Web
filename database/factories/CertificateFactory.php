<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'certificate_type' => Certificate::TYPE_BIKRAM,
            'file_path' => 'certificates/sample-certificate.html',
            'file_name' => 'sample-certificate.html',
            'version' => 1,
            'generated_by_user_id' => User::factory()->admin(),
            'generated_at' => now(),
        ];
    }
}
