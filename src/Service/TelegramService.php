<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    private readonly string $apiUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'TELEGRAM_TOKEN')] private readonly string $token,
    ) {
        $this->apiUrl = \sprintf('https://api.telegram.org/bot%s/', $this->token);
    }

    public function sendMessage(int $chatId, string $text, string $parseMode = 'MarkdownV2'): void
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl.'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => $parseMode,
                ],
            ]);

            // Force the request to complete to catch exceptions here instead of on destruct
            $response->getStatusCode();
        } catch (\Exception $e) {
            $maskedMessage = $e->getMessage();
            if (!empty($this->token)) {
                $maskedToken = mb_substr($this->token, 0, 9).'...[HIDDEN]';
                $maskedMessage = str_replace($this->token, $maskedToken, $maskedMessage);
            }
            error_log('Telegram API Error: '.$maskedMessage);
        }
    }

    /**
     * Optional utility to strictly escape MarkdownV2 characters if needed.
     * In most LLM interactions, we'll want to pass the raw text assuming it formats correctly.
     */
    public function escapeMarkdownV2(string $text): string
    {
        $charactersToEscape = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($charactersToEscape as $char) {
            $text = str_replace($char, '\\'.$char, $text);
        }

        return $text;
    }
}
