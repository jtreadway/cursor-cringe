<?php

declare(strict_types=1);

/**
 * Parses optional [tag] / [+tag] bracket metadata from venue names and event lines.
 */
class TagSyntax
{
    /**
     * Strip a trailing [tag] block from a venue name.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function stripVenueDefaults(string $text): array
    {
        $text = trim($text);

        if (!preg_match('/^(.*)\s+\[([^\]]+)\]\s*$/', $text, $matches)) {
            return [$text, []];
        }

        $parsed = self::parseTagContent($matches[2], true);

        return [trim($matches[1]), $parsed['tags']];
    }

    /**
     * Strip a leading [tag] / [+tag] block from an event line body.
     *
     * @return array{0: string, 1: array{mode: string, tags: list<string>}|null}
     */
    public static function stripEventTags(string $text): array
    {
        $text = trim($text);

        if (!preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $text, $matches)) {
            return [$text, null];
        }

        $parsed = self::parseTagContent($matches[1], false);

        return [trim($matches[2]), $parsed];
    }

    /**
     * @return array{mode: string, tags: list<string>}
     */
    private static function parseTagContent(string $content, bool $isVenue): array
    {
        $content = trim($content);
        $mode = $isVenue ? 'default' : 'replace';

        if (!$isVenue && $content !== '' && $content[0] === '+') {
            $mode = 'add';
            $content = ltrim(substr($content, 1));
        }

        $tags = [];
        foreach (explode(',', $content) as $part) {
            $tag = strtolower(trim($part));
            if ($tag === '' || $tag === '?') {
                $tag = 'unknown';
            }
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        $tags = array_values(array_unique($tags));

        if (in_array('unknown', $tags, true)) {
            return ['mode' => 'replace', 'tags' => ['unknown']];
        }

        return ['mode' => $mode, 'tags' => $tags];
    }
}
