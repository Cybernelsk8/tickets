<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\MailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => MailMessage::class,
            'attachable_id' => MailMessage::factory(),
            'disk' => 'local',
            'path' => fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(100, 1048576),
            'checksum' => str_repeat('a', 64),
            'source' => 'upload',
            'uploaded_by' => null,
        ];
    }

    /**
     * Indicate that the attachment came from an email message.
     */
    public function fromEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'email',
        ]);
    }
}
