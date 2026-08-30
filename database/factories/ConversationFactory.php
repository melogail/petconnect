<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * `last_message_at` is deliberately left null: App\Observers\MessageObserver
 * maintains it whenever a message is created, deleted or restored.
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Conversation>
     */
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array{type: ConversationType, last_message_at: null}
     */
    public function definition(): array
    {
        return [
            'type' => ConversationType::Direct,
            'last_message_at' => null,
        ];
    }

    /**
     * A one to one thread, the only type the application creates today.
     */
    public function direct(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ConversationType::Direct,
        ]);
    }

    /**
     * Attach the given users to conversation_user, which is unique per
     * (conversation_id, user_id), so pass each participant once.
     */
    public function withParticipants(User ...$participants): static
    {
        return $this->hasAttached(collect($participants), [], 'users');
    }
}
