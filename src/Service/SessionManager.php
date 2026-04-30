<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

class SessionManager
{
    public function __construct(
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/knowledge')] private readonly string $knowledgeDir
    ) {
    }

    private function getSessionDir(string $chatId): string
    {
        $hashedId = hash('sha256', $chatId);
        $sessionDir = $this->knowledgeDir . '/sessions/' . $hashedId;
        
        if (!$this->filesystem->exists($sessionDir)) {
            $this->filesystem->mkdir($sessionDir, 0777);
        }
        
        return $sessionDir;
    }

    public function getObjective(string $chatId): ?string
    {
        $file = $this->getSessionDir($chatId) . '/objective.md';
        if ($this->filesystem->exists($file)) {
            return trim(file_get_contents($file));
        }
        return null;
    }

    public function updateObjective(string $chatId, string $objective): void
    {
        $file = $this->getSessionDir($chatId) . '/objective.md';
        $this->filesystem->dumpFile($file, $objective);
    }

    public function clearObjective(string $chatId): void
    {
        $file = $this->getSessionDir($chatId) . '/objective.md';
        if ($this->filesystem->exists($file)) {
            $this->filesystem->remove($file);
        }
    }

    public function getSummary(string $chatId): ?string
    {
        $file = $this->getSessionDir($chatId) . '/summary.md';
        if ($this->filesystem->exists($file)) {
            return trim(file_get_contents($file));
        }
        return null;
    }

    public function updateSummary(string $chatId, string $summaryText): void
    {
        $file = $this->getSessionDir($chatId) . '/summary.md';
        $this->filesystem->dumpFile($file, $summaryText);
    }

    public function saveMessage(string $chatId, string $role, string $content): void
    {
        $file = $this->getSessionDir($chatId) . '/history.md';
        $formattedMessage = sprintf("### Role: %s\n%s\n---\n", $role, $content);
        $this->filesystem->appendToFile($file, $formattedMessage);
    }

    public function getRecentHistory(string $chatId, int $limit = 10): array
    {
        $file = $this->getSessionDir($chatId) . '/history.md';
        if (!$this->filesystem->exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $messages = [];
        
        if (preg_match_all('/### Role: (user|model)\s*\n(.*?)(?=(### Role: |\z))/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $msgContent = preg_replace('/---\s*$/', '', trim($match[2]));
                $messages[] = [
                    'role' => $match[1],
                    'content' => trim($msgContent)
                ];
            }
        }

        return array_slice($messages, -$limit);
    }
}
