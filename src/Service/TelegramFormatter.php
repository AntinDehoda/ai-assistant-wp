<?php

namespace App\Service;

/**
 * Utility service for formatting messages using Telegram's strict MarkdownV2 syntax.
 */
readonly class TelegramFormatter
{
    /**
     * Escapes special characters required by Telegram's MarkdownV2 format.
     */
    public function escape(string $text): string
    {
        // Characters to escape in normal text: _ * [ ] ( ) ~ ` > # + - = | { } . !
        $characters = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        foreach ($characters as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        
        return $text;
    }

    /**
     * Formats text as bold. Note: the text inside should be escaped first if it contains special chars!
     */
    public function bold(string $text): string
    {
        return sprintf('*%s*', $text);
    }

    /**
     * Formats text as italic. Note: the text inside should be escaped first if it contains special chars!
     */
    public function italic(string $text): string
    {
        return sprintf('_%s_', $text);
    }

    /**
     * Formats text as monospace (inline code).
     */
    public function monospace(string $text): string
    {
        return sprintf('`%s`', $text);
    }

    /**
     * Formats a code block with an optional language.
     * The code inside should NOT be escaped, Telegram expects raw code inside the block,
     * but we must escape backticks and backslashes if they appear inside the code block.
     */
    public function codeBlock(string $code, string $language = ''): string
    {
        // Inside a code block, only ` and \ need escaping
        $code = str_replace(['\\', '`'], ['\\\\', '\\`'], $code);
        return sprintf("```%s\n%s\n```", $language, $code);
    }

    /**
     * Creates a MarkdownV2 link.
     */
    public function link(string $text, string $url): string
    {
        return sprintf('[%s](%s)', $text, $url);
    }

    /**
     * Converts generic Markdown (like LLM output) to Telegram-supported HTML.
     */
    public function markdownToHtml(string $markdown): string
    {
        $placeholders = [];
        $counter = 0;

        // 1. Extract Code Blocks
        $markdown = preg_replace_callback('/```([\w\-]+)?\n*(.*?)\n*```/s', function ($matches) use (&$placeholders, &$counter) {
            $id = "@@CODEBLOCK_{$counter}@@";
            $lang = !empty($matches[1]) ? ' class="language-' . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
            $code = htmlspecialchars($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $placeholders[$id] = "<pre><code{$lang}>" . $code . "</code></pre>";
            $counter++;
            return $id;
        }, $markdown);

        // 2. Extract Inline Code
        $markdown = preg_replace_callback('/`([^`]+)`/', function ($matches) use (&$placeholders, &$counter) {
            $id = "@@INLINECODE_{$counter}@@";
            $code = htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $placeholders[$id] = "<code>" . $code . "</code>";
            $counter++;
            return $id;
        }, $markdown);

        // 3. Escape HTML entities on the rest of the text
        $html = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // 4. Headers (# Header) -> Convert to bold
        $html = preg_replace('/^#{1,6}\s+(.*?)$/m', '<b>$1</b>', $html);

        // 5. Bold (**text**)
        $html = preg_replace('/\*\*(?!\s)(.*?)(?<!\s)\*\*/s', '<b>$1</b>', $html);

        // 6. Italic (*text* or _text_)
        $html = preg_replace('/(?<!\*)\*(?!\s|\*)(.*?)(?<!\s|\*)\*(?!\*)/s', '<i>$1</i>', $html);
        $html = preg_replace('/(?<!_)_(?!\s|_)(.*?)(?<!\s|_)_(?!_)/s', '<i>$1</i>', $html);

        // 7. Links ([text](url))
        $html = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2">$1</a>', $html);

        // 8. Restore Code Blocks and Inline Code
        return strtr($html, $placeholders);
    }
}
