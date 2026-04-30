<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class WikiManager
{
    public function __construct(
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/knowledge')] private readonly string $knowledgeDir
    ) {
        $this->ensureDirectoriesExist();
    }

    private function ensureDirectoriesExist(): void
    {
        $this->filesystem->mkdir([
            $this->knowledgeDir.'/wiki',
            $this->knowledgeDir.'/raw',
        ]);
    }

    public function listKnowledge(): string
    {
        $indexPath = $this->knowledgeDir.'/index.md';
        if ($this->filesystem->exists($indexPath)) {
            return file_get_contents($indexPath);
        }

        // Fallback: Generate a list if index.md is missing
        $finder = new Finder();
        $finder->files()->in($this->knowledgeDir.'/wiki')->name('*.md');

        $list = "Knowledge Base Index:\n";
        foreach ($finder as $file) {
            $list .= '- '.$file->getFilename()."\n";
        }

        return $list;
    }

    public function readPage(string $filename): string
    {
        $path = $this->knowledgeDir.'/wiki/'.ltrim($filename, '/');

        if (!$this->filesystem->exists($path)) {
            throw new \Exception(\sprintf('Knowledge page "%s" not found.', $filename));
        }

        return file_get_contents($path);
    }

    public function writePage(string $filename, string $content): void
    {
        $path = $this->knowledgeDir.'/wiki/'.ltrim($filename, '/');
        $this->filesystem->dumpFile($path, $content);
    }

    public function searchSources(string $query): array
    {
        $finder = new Finder();
        $finder->files()->in($this->knowledgeDir.'/raw');

        $results = [];
        foreach ($finder as $file) {
            $content = $file->getContents();
            if (mb_stripos($content, $query) !== false) {
                $results[] = $file->getFilename();
            }
        }

        return $results;
    }

    public function appendLog(string $entry): void
    {
        $logPath = $this->knowledgeDir.'/log.md';
        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');
        $formattedEntry = \sprintf("[%s] %s\n", $timestamp, $entry);

        $this->filesystem->appendToFile($logPath, $formattedEntry);
    }
}
