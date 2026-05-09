<?php

namespace App\Agent;

use App\Service\SessionManager;
use App\Service\WikiManager;

class WpAiArchitectProfile implements AgentProfileInterface
{
    public function __construct(
        private readonly WikiManager $wikiManager,
        private readonly SessionManager $sessionManager
    ) {
    }

    public function getSystemInstruction(?string $objective = null, ?string $summary = null): string
    {
        $instruction = '';

        if ($objective) {
            $instruction .= 'CURRENT_MISSION: ' . $objective . "\n\n";
        }

        if ($summary) {
            $instruction .= "PROGRESS_SO_FAR:\n" . $summary . "\n\n";
        }

        $instruction .= <<<EOF
Role: Senior WP-AI Architect & Knowledge Custodian.
Core Mission: Maintain a persistent LLM-Wiki about WordPress CRM integrations while assisting the user.
Note: You are reading from and writing to your own Short-term Session Wiki (Markdown files) instead of a database.

Tone & Behavior Guidelines:
- DO NOT re-introduce yourself, repeat your role, or send a welcome message after executing a tool.
- Keep responses concise and direct.
- After successfully executing a tool (like `write_page` or `finalize_subtask_and_summarize`), simply acknowledge the success very briefly (e.g., "Knowledge base updated.") rather than generating a long concluding message.

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

    public function getFunctionDeclarations(): array
    {
        return [
            [
                'name' => 'list_knowledge',
                'description' => 'Scans the /wiki directory and returns the index.md content.',
            ],
            [
                'name' => 'read_page',
                'description' => 'Retrieves the content of a specific knowledge page.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'filename' => ['type' => 'STRING'],
                    ],
                    'required' => ['filename'],
                ],
            ],
            [
                'name' => 'write_page',
                'description' => 'Creates or updates a synthesized knowledge page. Use this to prospectively record new solutions, bugfixes, or patterns.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'filename' => ['type' => 'STRING'],
                        'content' => ['type' => 'STRING'],
                    ],
                    'required' => ['filename', 'content'],
                ],
            ],
            [
                'name' => 'search_sources',
                'description' => 'Performs a keyword search across the raw/ directory for plugin API specifications, logs, or plugin docs.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'update_session_objective',
                'description' => 'Updates the active session objective. Use this if the user shifts their primary intent to ensure you stay focused on the new goal.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'new_objective' => ['type' => 'STRING'],
                    ],
                    'required' => ['new_objective'],
                ],
            ],
            [
                'name' => 'finalize_subtask_and_summarize',
                'description' => 'Generates a 2-sentence summary of "What was achieved" and "What is the immediate next step". Use this every 5 messages or when transitioning between different architectural topics.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'summary' => ['type' => 'STRING'],
                    ],
                    'required' => ['summary'],
                ],
            ],
        ];
    }

    public function executeFunction(string $name, array|object $args, ?string $chatId = null): mixed
    {
        $argsArray = (array) $args;
        try {
            switch ($name) {
                case 'list_knowledge':
                    $this->wikiManager->appendLog('Agent executed tool: list_knowledge');

                    return $this->wikiManager->listKnowledge();

                case 'read_page':
                    $this->wikiManager->appendLog('Agent executed tool: read_page => ' . $argsArray['filename']);

                    return $this->wikiManager->readPage($argsArray['filename']);

                case 'write_page':
                    $this->wikiManager->appendLog('Agent executed tool: write_page => ' . $argsArray['filename']);
                    $this->wikiManager->writePage($argsArray['filename'], $argsArray['content']);

                    return 'Page successfully written.';

                case 'search_sources':
                    $this->wikiManager->appendLog("Agent executed tool: search_sources => '" . $argsArray['query'] . "'");

                    return json_encode($this->wikiManager->searchSources($argsArray['query']));

                case 'update_session_objective':
                    if ($chatId === null) {
                        return 'Error: Cannot update objective because chat_id is unknown.';
                    }
                    $newObjective = $argsArray['new_objective'] ?? '';
                    $this->sessionManager->updateObjective($chatId, $newObjective);
                    $hashedId = hash('sha256', $chatId);
                    $this->wikiManager->appendLog("[$hashedId] Objective set to: $newObjective");

                    return 'Session objective successfully updated.';

                case 'finalize_subtask_and_summarize':
                    if ($chatId === null) {
                        return 'Error: Cannot update summary because chat_id is unknown.';
                    }
                    $summaryText = $argsArray['summary'] ?? '';
                    $this->sessionManager->updateSummary($chatId, $summaryText);
                    $hashedId = hash('sha256', $chatId);
                    $this->wikiManager->appendLog("[$hashedId] Subtask Finalized. Summary: $summaryText");

                    return 'Subtask successfully summarized and saved to context memory.';

                default:
                    return "Unknown function name: $name";
            }
        } catch (\Exception $e) {
            return 'Function execution failed: ' . $e->getMessage();
        }
    }
}
