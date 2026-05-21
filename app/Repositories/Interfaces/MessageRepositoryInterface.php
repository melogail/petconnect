<?php

namespace App\Repositories\Interfaces;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface
{
    public function paginateForConversation(Conversation $conversation, int $perPage = 50): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForConversation(Conversation $conversation, array $attributes): Message;

    public function updateContent(Message $message, string $content): Message;

    public function delete(Message $message): void;
}
