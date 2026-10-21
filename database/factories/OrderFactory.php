<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(5_000_00, 400_000_00);
        $deliveryFee = fake()->numberBetween(0, 3_000_00);

        return [
            'number' => Order::nextNumber(),
            'buyer_id' => User::factory(),
            'delivery_method' => 'delivery',
            'delivery_name' => fake()->name(),
            'delivery_phone' => '080'.fake()->numerify('########'),
            'delivery_street' => fake()->streetAddress(),
            'delivery_area' => fake()->randomElement(['Barnawa', 'Malali', 'Unguwan Rimi', 'Kawo']),
            'subtotal_kobo' => $subtotal,
            'delivery_fee_kobo' => $deliveryFee,
            'total_kobo' => $subtotal + $deliveryFee,
            'status' => 'pending_payment',
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid', 'placed_at' => now(), 'paid_at' => now()]);
    }
}
