<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GeminiEngine
{
    private HttpClientInterface $httpClient;
    private WikiManager $wikiManager;
    private SessionManager $sessionManager;
    private string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        WikiManager $wikiManager,
        SessionManager $sessionManager,
        #[Autowire(env: 'GEMINI_API_KEY')] string $apiKey
    ) {
        $this->httpClient = $httpClient;
        $this->wikiManager = $wikiManager;
        $this->sessionManager = $sessionManager;
        $this->apiKey = $apiKey;
    }

    public function process(string $userMessage, ?string $chatId = null): string
    {
        $messages = [];
        
        if ($chatId !== null) {
            $history = $this->sessionManager->getRecentHistory($chatId, 10);
            foreach ($history as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'parts' => [['text' => $msg['content']]]
                ];
            }
        }

        $messages[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $finalResponse = $this->chatLoop($messages, $chatId);

        if ($chatId !== null && strpos($finalResponse, 'Error:') !== 0) {
            $this->sessionManager->saveMessage($chatId, 'user', $userMessage);
            $this->sessionManager->saveMessage($chatId, 'model', $finalResponse);
        }

        return $finalResponse;
    }

    private function chatLoop(array &$messages, ?string $chatId = null): string
    {
        $objective = null;
        if ($chatId !== null) {
            $objective = $this->sessionManager->getObjective($chatId);
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->getSystemInstruction($objective)]
                ]
            ],
            'contents' => $messages,
            'tools' => [
                ['functionDeclarations' => $this->getFunctionDeclarations()]
            ]
        ];

        $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=' . $this->apiKey, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        try {
            $data = $response->toArray();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return "Error: No response from Gemini.";
            }
        } catch (\Exception $e) {
            $errorBody = '';
            if (method_exists($e, 'getResponse')) {
                try {
                    $errorBody = " | Details: " . $e->getResponse()->getContent(false);
                } catch (\Exception $e2) {
                    // Ignore
                }
            }
            return "Error from Gemini API: " . $this->maskSensitiveData($e->getMessage() . $errorBody);
        }

        $textOutput = '';
        $functionCall = null;
        $functionName = '';
        $args = [];
        $callId = null;

        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $textOutput .= $part['text'] . "\n";
            }
            if (isset($part['functionCall'])) {
                $functionCall = $part['functionCall'];
                $functionName = $functionCall['name'];
                $args = $functionCall['args'] ?? [];
                $callId = $functionCall['id'] ?? null;
            }
        }

        if ($functionCall) {
            $result = $this->executeFunction($functionName, $args, $chatId);

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
                'response' => ['result' => $result]
            ];
            
            if ($callId !== null) {
                $functionResponseData['id'] = $callId;
            }

            $messages[] = [
                'role' => 'user',
                'parts' => [
                    [
                        'functionResponse' => $functionResponseData
                    ]
                ]
            ];

            // Recursive loop to process the tool result
            return trim($textOutput . "\n" . $this->chatLoop($messages, $chatId));
        }

        if ($textOutput !== '') {
            return trim($textOutput);
        }

        return "Error: Unhandled response format.";
    }

    private function executeFunction(string $name, array|object $args, ?string $chatId = null): mixed
    {
        $argsArray = (array) $args;
        try {
            switch ($name) {
                case 'list_knowledge':
                    $this->wikiManager->appendLog("Agent executed tool: list_knowledge");
                    return $this->wikiManager->listKnowledge();

                case 'read_page':
                    $this->wikiManager->appendLog("Agent executed tool: read_page => " . $argsArray['filename']);
                    return $this->wikiManager->readPage($argsArray['filename']);

                case 'write_page':
                    $this->wikiManager->appendLog("Agent executed tool: write_page => " . $argsArray['filename']);
                    $this->wikiManager->writePage($argsArray['filename'], $argsArray['content']);
                    return "Page successfully written.";

                case 'search_sources':
                    $this->wikiManager->appendLog("Agent executed tool: search_sources => '" . $argsArray['query'] . "'");
                    return json_encode($this->wikiManager->searchSources($argsArray['query']));
                    
                case 'update_session_objective':
                    if ($chatId === null) {
                        return "Error: Cannot update objective because chat_id is unknown.";
                    }
                    $newObjective = $argsArray['new_objective'] ?? '';
                    $this->sessionManager->updateObjective($chatId, $newObjective);
                    $this->wikiManager->appendLog("[$chatId] Objective set to: $newObjective");
                    return "Session objective successfully updated.";

                default:
                    return "Unknown function name: $name";
            }
        } catch (\Exception $e) {
            return "Function execution failed: " . $this->maskSensitiveData($e->getMessage());
        }
    }

    private function maskSensitiveData(string $text): string
    {
        if (!empty($this->apiKey)) {
            // Replace the actual key with a masked version (e.g. AIza...[HIDDEN])
            $masked = substr($this->apiKey, 0, 6) . '...[HIDDEN]';
            $text = str_replace($this->apiKey, $masked, $text);
        }
        return $text;
    }

    private function getSystemInstruction(?string $objective = null): string
    {
        $instruction = "";
        
        if ($objective) {
            $instruction .= "CURRENT_MISSION: " . $objective . "\n\n";
        }
        
        $instruction .= <<<EOF
Role: Senior WP-AI Architect & Knowledge Custodian.
Core Mission: Maintain a persistent LLM-Wiki about WordPress CRM integrations while assisting the user.

Knowledge Management Rules:
1. Never Re-derive: Before answering, use `list_knowledge` and `read_page` to check if we have an existing pattern.
2. Incremental Updates: Proactively use `write_page` to document new solutions in the `/wiki/` directory.
3. Link Everything: Use standard Markdown links `[[page-name]]` to connect related concepts.
4. Consistency: Ensure new entries do not contradict previous entries in `index.md`.

Technical Guardrails:
* Focus on PHP 8.2+, WordPress Hooks, and Secure AI integration.
* Always check `log.md` for the history of previous architectural decisions.
EOF;

        return $instruction;
    }

    private function getFunctionDeclarations(): array
    {
        return [
            [
                'name' => 'list_knowledge',
                'description' => 'Scans the /wiki directory and returns the index.md content.'
            ],
            [
                'name' => 'read_page',
                'description' => 'Retrieves the content of a specific knowledge page.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'filename' => ['type' => 'STRING']
                    ],
                    'required' => ['filename']
                ]
            ],
            [
                'name' => 'write_page',
                'description' => 'Creates or updates a synthesized knowledge page. Use this to prospectively record new solutions, bugfixes, or patterns.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'filename' => ['type' => 'STRING'],
                        'content' => ['type' => 'STRING']
                    ],
                    'required' => ['filename', 'content']
                ]
            ],
            [
                'name' => 'search_sources',
                'description' => 'Performs a keyword search across the raw/ directory for plugin API specifications, logs, or plugin docs.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING']
                    ],
                    'required' => ['query']
                ]
            ],
            [
                'name' => 'update_session_objective',
                'description' => 'Updates the active session objective. Use this if the user shifts their primary intent to ensure you stay focused on the new goal.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'new_objective' => ['type' => 'STRING']
                    ],
                    'required' => ['new_objective']
                ]
            ]
        ];
    }
}
