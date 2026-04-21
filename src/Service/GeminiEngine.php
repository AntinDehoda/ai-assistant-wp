<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GeminiEngine
{
    private HttpClientInterface $httpClient;
    private WikiManager $wikiManager;
    private string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        WikiManager $wikiManager,
        #[Autowire(env: 'GEMINI_API_KEY')] string $apiKey
    ) {
        $this->httpClient = $httpClient;
        $this->wikiManager = $wikiManager;
        $this->apiKey = $apiKey;
    }

    public function process(string $userMessage): string
    {
        $messages = [
            ['role' => 'user', 'parts' => [['text' => $userMessage]]]
        ];

        return $this->chatLoop($messages);
    }

    private function chatLoop(array &$messages): string
    {
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->getSystemInstruction()]
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

        $data = $response->toArray();
        $candidate = $data['candidates'][0] ?? null;

        if (!$candidate) {
            return "Error: No response from Gemini.";
        }

        $part = $candidate['content']['parts'][0] ?? null;

        if (isset($part['functionCall'])) {
            $functionCall = $part['functionCall'];
            $functionName = $functionCall['name'];
            $args = $functionCall['args'] ?? [];

            $result = $this->executeFunction($functionName, $args);

            // Append assistant's function call part
            $messages[] = $candidate['content'];

            // Append function response
            $messages[] = [
                'role' => 'function',
                'parts' => [
                    [
                        'functionResponse' => [
                            'name' => $functionName,
                            'response' => ['result' => $result]
                        ]
                    ]
                ]
            ];

            // Recursive loop to process the tool result
            return $this->chatLoop($messages);
        }

        if (isset($part['text'])) {
            return $part['text'];
        }

        return "Error: Unhandled response format.";
    }

    private function executeFunction(string $name, array $args): mixed
    {
        try {
            switch ($name) {
                case 'list_knowledge':
                    $this->wikiManager->appendLog("Agent executed tool: list_knowledge");
                    return $this->wikiManager->listKnowledge();

                case 'read_page':
                    $this->wikiManager->appendLog("Agent executed tool: read_page => " . $args['filename']);
                    return $this->wikiManager->readPage($args['filename']);

                case 'write_page':
                    $this->wikiManager->appendLog("Agent executed tool: write_page => " . $args['filename']);
                    $this->wikiManager->writePage($args['filename'], $args['content']);
                    return "Page successfully written.";

                case 'search_sources':
                    $this->wikiManager->appendLog("Agent executed tool: search_sources => '" . $args['query'] . "'");
                    return json_encode($this->wikiManager->searchSources($args['query']));

                default:
                    return "Unknown function name: $name";
            }
        } catch (\Exception $e) {
            return "Function execution failed: " . $e->getMessage();
        }
    }

    private function getSystemInstruction(): string
    {
        return <<<EOF
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
            ]
        ];
    }
}
