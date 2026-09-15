<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => 'Bole',
            'phone' => '+251911000001',
            'address' => 'Bole Road, Addis Ababa',
            'tax_identification_number' => 'TIN-000123',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'tax_rate' => '15.00',
            'active' => true,
        ];
    }
}
