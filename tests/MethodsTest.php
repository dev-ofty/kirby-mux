<?php
require_once __DIR__ . '/../vendor/autoload.php';

use KirbyMux\Methods;

$cases = [
    'video-rendition' => [
        'input' => (object) [
            'static_renditions' => (object) [
                'files' => [
                    (object) ['name' => 'highest.mp4', 'ext' => 'mp4'],
                ],
            ],
        ],
        'expected' => 'highest.mp4',
    ],
    'audio-rendition' => [
        'input' => (object) [
            'static_renditions' => (object) [
                'files' => [
                    (object) ['name' => 'audio.m4a', 'ext' => 'm4a'],
                ],
            ],
        ],
        'expected' => 'audio.m4a',
    ],
    'none-found' => [
        'input' => (object) [
            'static_renditions' => (object) [
                'files' => [
                    (object) ['name' => 'preview.jpg', 'ext' => 'jpg'],
                ],
            ],
        ],
        'expected' => null,
    ],
];

foreach ($cases as $name => $case) {
    $actual = Methods::resolveStaticRenditionName($case['input']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "Test failed for {$name}: expected " . var_export($case['expected'], true) . ", got " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

echo "All Methods tests passed" . PHP_EOL;
