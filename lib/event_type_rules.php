<?php

declare(strict_types=1);

/**
 * Unified ruleset for nightlife event classification.
 * Adapted from genJSON-LD-pre-classify-rearch/calendarJSONLD/lib/event_type_rules.php
 */
return [

    'karaoke' => [
        'priority' => 5,
        'schema' => 'MusicEvent',
        'keywords' => ['karaoke', 'sing-along'],
        'tags' => ['karaoke'],
    ],

    'trivia' => [
        'priority' => 5,
        'schema' => 'Event',
        'keywords' => ['trivia', 'quiz', 'pub quiz'],
        'tags' => ['trivia'],
    ],

    'drag' => [
        'priority' => 5,
        'schema' => 'TheaterEvent',
        'keywords' => ['drag', 'drag show', 'drag brunch'],
        'tags' => ['drag'],
    ],

    'bingo' => [
        'priority' => 5,
        'schema' => 'Event',
        'keywords' => ['bingo'],
        'tags' => ['bingo'],
    ],

    'game_night' => [
        'priority' => 5,
        'schema' => 'Event',
        'keywords' => ['game night', 'board games'],
        'tags' => ['game night'],
    ],

    'variety' => [
        'priority' => 5,
        'schema' => 'Event',
        'keywords' => ['variety show', 'variety open mic'],
        'tags' => ['variety'],
    ],

    'music' => [
        'priority' => 10,
        'schema' => 'MusicEvent',
        'keywords' => [
            'band', 'live', 'concert', 'tour', 'release', 'album', 'ep release',
            'record release', 'singer', 'songwriter', 'jazz', 'blues', 'punk',
            'metal', 'indie', 'folk', 'acoustic', 'orchestra', 'ensemble', 'choir',
            'set', 'tribute', 'cover band', 'bluegrass',
        ],
        'tags' => ['music'],
    ],

    'comedy' => [
        'priority' => 10,
        'schema' => 'ComedyEvent',
        'keywords' => [
            'comedy', 'comedian', 'standup', 'stand-up', 'improv',
            'open mic comedy', 'open-mic comedy', 'sketch',
        ],
        'tags' => ['comedy'],
    ],

    'spoken_word' => [
        'priority' => 15,
        'schema' => 'TheaterEvent',
        'keywords' => [
            'spoken word', 'poetry', 'poetry slam', 'reading', 'author talk',
            'book launch', 'storytelling',
        ],
        'tags' => ['spoken word'],
    ],

    'dj' => [
        'priority' => 20,
        'schema' => 'MusicEvent',
        'keywords' => ['dj', 'dj set', 'edm', 'techno', 'house', 'rave', 'club night'],
        'tags' => ['dj'],
    ],

    'visual_arts' => [
        'priority' => 30,
        'schema' => 'VisualArtsEvent',
        'keywords' => ['gallery', 'exhibit', 'exhibition', 'art show', 'opening reception'],
        'tags' => ['visual arts'],
    ],

    'festival' => [
        'priority' => 40,
        'schema' => 'Festival',
        'keywords' => ['festival', 'fest'],
        'tags' => ['festival'],
    ],
];
