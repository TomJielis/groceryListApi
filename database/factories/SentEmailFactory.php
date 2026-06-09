<?php

namespace Database\Factories;

use App\Models\SentEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SentEmail>
 */
class SentEmailFactory extends Factory
{
    protected $model = SentEmail::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['welcome', 'reset_password', 'grocery_list_invite']),
            'recipient_email' => $this->faker->safeEmail(),
            'recipient_name' => $this->faker->name(),
            'subject' => $this->faker->sentence(),
            'body_html' => '<p>' . $this->faker->paragraph() . '</p>',
            'body_text' => $this->faker->paragraph(),
            'status' => 'sent',
            'triggered_by_user_id' => null,
            'sent_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'SMTP connection failed',
            'body_html' => null,
            'body_text' => null,
            'sent_at' => null,
        ]);
    }
}
