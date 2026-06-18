<?php

declare(strict_types=1);

require_once __DIR__ . '/EventClassifier.php';
require_once __DIR__ . '/TagSyntax.php';

class TagResolver
{
    /**
     * @param list<string> $venueDefaults
     * @param array{mode: string, tags: list<string>}|null $explicit
     * @param list<string|null> $notes
     * @return array{tags: list<string>, schemaType: string}
     */
    public static function resolve(
        array $venueDefaults,
        ?array $explicit,
        string $display,
        array $notes = [],
        string $venueName = ''
    ): array {
        if ($explicit !== null) {
            if ($explicit['mode'] === 'add') {
                $tags = array_values(array_unique(array_merge($venueDefaults, $explicit['tags'])));
            } else {
                $tags = $explicit['tags'];
            }

            return [
                'tags' => $tags,
                'schemaType' => EventClassifier::schemaForTags($tags),
            ];
        }

        $classification = EventClassifier::classify(
            $display,
            array_values(array_filter($notes, static fn ($note) => $note !== null && $note !== '')),
            $venueName
        );

        $tags = array_values(array_unique(array_merge($venueDefaults, $classification['tags'])));

        if ($tags === []) {
            return [
                'tags' => ['unknown'],
                'schemaType' => 'Event',
            ];
        }

        return [
            'tags' => $tags,
            'schemaType' => EventClassifier::schemaForTags($tags),
        ];
    }
}
