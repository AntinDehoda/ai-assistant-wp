<?php

namespace App\Service;

use App\Agent\AgentProfileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiEngine
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AgentProfileInterface $agentProfile,
        private readonly SessionManager $sessionManager,
        #[Autowire(env: 'GEMINI_API_KEY')] private readonly string $apiKey,
    ) {
    }

    public function process(string $userMessage, ?string $chatId = null): string
    {
        $messages = [];

        if ($chatId !== null) {
            $history = $this->sessionManager->getRecentHistory($chatId, 10);
            foreach ($history as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }

        $messages[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $finalResponse = $this->chatLoop($messages, $chatId);

        if ($chatId !== null && !str_starts_with($finalResponse, 'Error:')) {
            $this->sessionManager->saveMessage($chatId, 'user', $userMessage);
            $this->sessionManager->saveMessage($chatId, 'model', $finalResponse);
        }

        return $finalResponse;
    }

    private function chatLoop(array &$messages, ?string $chatId = null): string
    {
        $objective = null;
        $summary = null;
        if ($chatId !== null) {
            $objective = $this->sessionManager->getObjective($chatId);
            $summary = $this->sessionManager->getSummary($chatId);
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->agentProfile->getSystemInstruction($objective, $summary)],
                ],
            ],
            'contents' => $messages,
            'tools' => [
                ['functionDeclarations' => $this->agentProfile->getFunctionDeclarations()],
            ],
        ];

        $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key='.$this->apiKey, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            $data = $response->toArray();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return 'Error: No response from Gemini.';
            }
        } catch (\Exception $e) {
            $errorBody = '';
            if (method_exists($e, 'getResponse')) {
                try {
                    $errorBody = ' | Details: '.$e->getResponse()->getContent(false);
                } catch (\Exception) {
                    // Ignore
                }
            }

            return 'Error from Gemini API: '.$this->maskSensitiveData($e->getMessage().$errorBody);
        }

        $textOutput = '';
        $functionCall = null;
        $functionName = '';
        $args = [];
        $callId = null;

        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        if (empty($parts) && isset($candidate['finishReason']) && $candidate['finishReason'] !== 'STOP') {
            return 'Error: Gemini API stopped with reason: '.$candidate['finishReason'];
        }

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textOutput .= $part['text']."\n";
            }
            if (isset($part['functionCall'])) {
                $functionCall = $part['functionCall'];
                $functionName = $functionCall['name'];
                $args = $functionCall['args'] ?? [];
                $callId = $functionCall['id'] ?? null;
            }
        }

        if ($functionCall) {
            $result = $this->agentProfile->executeFunction($functionName, $args, $chatId);
            
            // Mask any sensitive data that might have leaked into the tool execution output
            if (is_string($result)) {
                $result = $this->maskSensitiveData($result);
            }

            // Fix empty args array becoming a list in JSON instead of an object
            $modelContent = $candidate['content'];
            foreach ($modelContent['parts'] as &$partRef) {
                if (isset($partRef['functionCall']['args']) && empty($partRef['functionCall']['args'])) {
                    $partRef['functionCall']['args'] = new \stdClass();
                }
            }

            // Append assistant's function call part
            $messages[] = $modelContent;

            // Append function response
            $functionResponseData = [
                'name' => $functionName,
                'response' => ['result' => $result],
            ];

            if ($callId !== null) {
                $functionResponseData['id'] = $callId;
            }

            $messages[] = [
                'role' => 'user',
                'parts' => [
                    [
                        'functionResponse' => $functionResponseData,
                    ],
                ],
            ];

            // Recursive loop to process the tool result
            return trim($textOutput."\n".$this->chatLoop($messages, $chatId));
        }

        if ($textOutput !== '') {
            return trim($textOutput);
        }

        return 'Error: Unhandled response format.';
    }

    private function maskSensitiveData(string $text): string
    {
        if (!empty($this->apiKey)) {
            // Replace the actual key with a masked version (e.g. AIza...[HIDDEN])
            $masked = mb_substr($this->apiKey, 0, 6).'…[HIDDEN]';
            $text = str_replace($this->apiKey, $masked, $text);
        }

        return $text;
    }
}
