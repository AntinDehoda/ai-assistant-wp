<?php

namespace App\Dto;

class TelegramUpdate
{
    public readonly ?int $chatId;
    public readonly ?string $text;

    public function __construct(array $data)
    {
        // Parses standard Telegram webhook payload structures
        $this->chatId = $data['message']['chat']['id'] ?? null;
        $this->text = $data['message']['text'] ?? null;
    }
}
