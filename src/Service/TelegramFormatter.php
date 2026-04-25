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
}
