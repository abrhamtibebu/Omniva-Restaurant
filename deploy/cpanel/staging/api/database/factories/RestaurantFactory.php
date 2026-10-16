<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Betedesta',
            'phone' => '+251911000000',
            'address' => 'Bole, Addis Ababa',
            'tax_identification_number' => 'TIN-000123',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'active' => true,
        ];
    }
}
