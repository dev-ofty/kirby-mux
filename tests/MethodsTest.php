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

// --- waitUntilReady ---

class StubAssetData {
    private $status;
    public function __construct($status) { $this->status = $status; }
    public function getStatus() { return $this->status; }
}

class StubAsset {
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function getData() { return $this->data; }
}

class StubAssetsApi {
    private $responses;
    private $i = 0;
    public function __construct(array $responses) { $this->responses = $responses; }
    public function getAsset($assetId) {
        $response = $this->responses[min($this->i++, count($this->responses) - 1)];
        if ($response instanceof Exception) {
            throw $response;
        }
        return $response;
    }
}

$readyData = new StubAssetData('ready');

$waitCases = [
    'ready-immediately' => [
        'api' => new StubAssetsApi([new StubAsset($readyData)]),
        'maxAttempts' => 5,
        'expected' => $readyData,
    ],
    'ready-on-second-attempt' => [
        'api' => new StubAssetsApi([new StubAsset(new StubAssetData('preparing')), new StubAsset($readyData)]),
        'maxAttempts' => 5,
        'expected' => $readyData,
    ],
    'api-exception' => [
        'api' => new StubAssetsApi([new Exception('boom')]),
        'maxAttempts' => 5,
        'expected' => null,
    ],
    'missing-data' => [
        'api' => new StubAssetsApi([new StubAsset(null)]),
        'maxAttempts' => 5,
        'expected' => null,
    ],
    'never-ready-times-out' => [
        'api' => new StubAssetsApi([new StubAsset(new StubAssetData('preparing'))]),
        'maxAttempts' => 1,
        'expected' => null,
    ],
];

foreach ($waitCases as $name => $case) {
    $actual = Methods::waitUntilReady($case['api'], 'asset-id', $case['maxAttempts']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "waitUntilReady test failed for {$name}: expected " . var_export($case['expected'], true) . ", got " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

echo "All Methods tests passed" . PHP_EOL;
