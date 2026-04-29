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
            $text = str_replace($char, '\\'.$char, $text);
        }

        return $text;
    }

    /**
     * Formats text as bold. Note: the text inside should be escaped first if it contains special chars!
     */
    public function bold(string $text): string
    {
        return \sprintf('*%s*', $text);
    }

    /**
     * Formats text as italic. Note: the text inside should be escaped first if it contains special chars!
     */
    public function italic(string $text): string
    {
        return \sprintf('_%s_', $text);
    }

    /**
     * Formats text as monospace (inline code).
     */
    public function monospace(string $text): string
    {
        return \sprintf('`%s`', $text);
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

        return \sprintf("```%s\n%s\n```", $language, $code);
    }

    /**
     * Creates a MarkdownV2 link.
     */
    public function link(string $text, string $url): string
    {
        return \sprintf('[%s](%s)', $text, $url);
    }

    /**
     * Converts generic Markdown (like LLM output) to Telegram-supported HTML.
     */
    public function markdownToHtml(string $markdown): string
    {
        $placeholders = [];
        $counter = 0;

        // 1. Extract Code Blocks
        $markdown = preg_replace_callback('/```([\w\-]+)?\n*(.*?)\n*```/s', static function ($matches) use (&$placeholders, &$counter) {
            $id = "@@CODEBLOCK{$counter}@@";
            $lang = !empty($matches[1]) ? ' class="language-'.htmlspecialchars($matches[1], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"' : '';
            $code = htmlspecialchars($matches[2], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $placeholders[$id] = "<pre><code{$lang}>".$code.'</code></pre>';
            ++$counter;

            return $id;
        }, $markdown);

        // 2. Extract Inline Code
        $markdown = preg_replace_callback('/`([^`]+)`/', static function ($matches) use (&$placeholders, &$counter) {
            $id = "@@INLINECODE{$counter}@@";
            $code = htmlspecialchars($matches[1], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $placeholders[$id] = '<code>'.$code.'</code>';
            ++$counter;

            return $id;
        }, (string) $markdown);

        // 3. Protect URLs in Links
        $markdown = preg_replace_callback('/\[(.*?)\]\((.*?)\)/s', static function ($matches) use (&$placeholders, &$counter) {
            $id = "@@URL{$counter}@@";
            $url = htmlspecialchars($matches[2], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $placeholders[$id] = $url;

            return '['.$matches[1].']('.$id.')';
        }, (string) $markdown);

        // 4. Escape HTML entities on the rest of the text
        $html = htmlspecialchars((string) $markdown, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        // 5. Headers (# Header) -> Convert to bold
        $html = preg_replace('/^#{1,6}\s+(.*?)$/m', '<b>$1</b>', $html);

        // 6. Bold (**text**)
        $html = preg_replace('/\*\*(?!\s)(.*?)(?<!\s)\*\*/s', '<b>$1</b>', (string) $html);

        // 7. Italic (*text* or _text_)
        $html = preg_replace('/(?<!\*)\*(?!\s|\*)(.*?)(?<!\s|\*)\*(?!\*)/s', '<i>$1</i>', (string) $html);
        $html = preg_replace('/(?<!_)_(?!\s|_)(.*?)(?<!\s|_)_(?!_)/s', '<i>$1</i>', (string) $html);

        // 8. Links ([text](url))
        $html = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2">$1</a>', (string) $html);

        // 9. Restore Placeholders
        return strtr($html, $placeholders);
    }
}
