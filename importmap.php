<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'playableboard' => [
        'path' => './assets/playableboard.js',
        'entrypoint' => true,
    ],
    'variationboard' => [
        'path' => './assets/variationboard.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'bootstrap' => [
        'version' => '5.3.3',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'cm-chessboard' => [
        'version' => '8.7.4',
    ],
    'cm-chessboard/src/extensions/markers/Markers.js' => [
        'version' => '8.7.4',
    ],
    'cm-chessboard/assets/chessboard.css' => [
        'version' => '8.7.4',
        'type' => 'css',
    ],
    'cm-chessboard/assets/extensions/markers/markers.css' => [
        'version' => '8.7.4',
        'type' => 'css',
    ],
    'chess.js' => [
        'version' => '1.0.0-beta.8',
    ],
];
