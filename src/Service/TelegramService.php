<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TelegramService
{
    private HttpClientInterface $httpClient;
    private string $apiUrl;
    private string $token;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire(env: 'TELEGRAM_TOKEN')] string $token
    ) {
        $this->httpClient = $httpClient;
        $this->token = $token;
        $this->apiUrl = sprintf('https://api.telegram.org/bot%s/', $token);
    }

    public function sendMessage(int $chatId, string $text, string $parseMode = 'MarkdownV2'): void
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => $parseMode,
                ]
            ]);
            
            // Force the request to complete to catch exceptions here instead of on destruct
            $response->getStatusCode();
        } catch (\Exception $e) {
            $maskedMessage = $e->getMessage();
            if (!empty($this->token)) {
                $maskedToken = substr($this->token, 0, 9) . '...[HIDDEN]';
                $maskedMessage = str_replace($this->token, $maskedToken, $maskedMessage);
            }
            error_log("Telegram API Error: " . $maskedMessage);
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
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }
}
