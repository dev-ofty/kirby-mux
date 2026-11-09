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

function assertSame($name, $expected, $actual) {
    if ($actual !== $expected) {
        fwrite(STDERR, "Test failed for {$name}: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

// --- static_renditions shapes (legacy object, per-file status, bare array) ---

$decode = fn($json) => json_decode($json);

$renditionCases = [
    'legacy-object-ready' => ['{"static_renditions":{"status":"ready","files":[{"name":"highest.mp4","ext":"mp4"}]}}', 'ready', 'highest.mp4'],
    'legacy-object-files-no-status' => ['{"static_renditions":{"files":[{"name":"highest.mp4","ext":"mp4"}]}}', 'ready', 'highest.mp4'],
    'legacy-object-preparing' => ['{"static_renditions":{"status":"preparing"}}', 'preparing', null],
    'legacy-object-disabled' => ['{"static_renditions":{"status":"disabled"}}', 'disabled', null],
    'per-file-preparing' => ['{"static_renditions":{"files":[{"name":"highest.mp4","ext":"mp4","status":"preparing"}]}}', 'preparing', null],
    'per-file-ready' => ['{"static_renditions":{"files":[{"name":"highest.mp4","ext":"mp4","status":"ready"}]}}', 'ready', 'highest.mp4'],
    'per-file-skipped-and-ready' => ['{"static_renditions":{"files":[{"name":"1080p.mp4","ext":"mp4","status":"skipped"},{"name":"720p.mp4","ext":"mp4","status":"ready"}]}}', 'ready', '720p.mp4'],
    'per-file-errored' => ['{"static_renditions":{"files":[{"name":"highest.mp4","ext":"mp4","status":"errored"}]}}', 'errored', null],
    'array-ready' => ['{"static_renditions":[{"name":"audio.m4a","ext":"m4a","status":"ready"}]}', 'ready', 'audio.m4a'],
    'array-empty' => ['{"static_renditions":[]}', 'disabled', null],
    'none-requested' => ['{"id":"abc"}', null, null],
    'garbage-renditions' => ['{"static_renditions":"oops"}', 'disabled', null],
];

foreach ($renditionCases as $name => [$json, $status, $rendition]) {
    assertSame("renditionsStatus:{$name}", $status, Methods::renditionsStatus($decode($json)));
    assertSame("resolveStaticRenditionName:{$name}", $rendition, Methods::resolveStaticRenditionName($decode($json)));
}
assertSame('renditionsStatus:null', null, Methods::renditionsStatus(null));
assertSame('resolveStaticRenditionName:null', null, Methods::resolveStaticRenditionName(null));

// --- verifySignature ---

$secret = 'whsec_test';
$body = '{"type":"video.asset.ready","data":{"id":"asset-1"}}';
$now = 1_800_000_000;
$sign = fn($t, $b, $s = 'whsec_test') => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $b, $s);

assertSame('sig:valid', true, Methods::verifySignature($body, $sign($now, $body), $secret, 300, $now));
assertSame('sig:tampered-body', false, Methods::verifySignature($body . ' ', $sign($now, $body), $secret, 300, $now));
assertSame('sig:wrong-secret', false, Methods::verifySignature($body, $sign($now, $body, 'other'), $secret, 300, $now));
assertSame('sig:expired', false, Methods::verifySignature($body, $sign($now - 301, $body), $secret, 300, $now));
assertSame('sig:empty-header', false, Methods::verifySignature($body, '', $secret, 300, $now));
assertSame('sig:no-v1', false, Methods::verifySignature($body, 't=' . $now, $secret, 300, $now));
assertSame('sig:non-numeric-t', false, Methods::verifySignature($body, 't=abc,v1=xyz', $secret, 300, $now));
assertSame('sig:empty-secret', false, Methods::verifySignature($body, $sign($now, $body), '', 300, $now));
assertSame('sig:second-v1-matches', true, Methods::verifySignature($body, $sign($now, $body) . ',v1=deadbeef', $secret, 300, $now));

// --- webhookAssetId ---

$webhookCases = [
    'asset-ready' => ['{"type":"video.asset.ready","data":{"id":"asset-1"}}', 'asset-1'],
    'rendition-ready-asset_id' => ['{"type":"video.asset.static_rendition.ready","data":{"id":"rend-1","asset_id":"asset-2"}}', 'asset-2'],
    'rendition-ready-object' => ['{"type":"video.asset.static_rendition.ready","object":{"type":"asset","id":"asset-3"},"data":{"id":"rend-1"}}', 'asset-3'],
    'rendition-no-asset-ref' => ['{"type":"video.asset.static_rendition.ready","data":{"id":"rend-1"}}', null],
    'irrelevant-event' => ['{"type":"video.asset.created","data":{"id":"asset-1"}}', null],
    'deleted-event' => ['{"type":"video.asset.deleted","data":{"id":"asset-1"}}', null],
    'live-stream-event' => ['{"type":"video.live_stream.active","data":{"id":"ls-1"}}', null],
    'malformed-json' => ['not json', null],
    'missing-data' => ['{"type":"video.asset.ready"}', null],
    'empty-id' => ['{"type":"video.asset.ready","data":{"id":""}}', null],
];

foreach ($webhookCases as $name => [$payload, $expected]) {
    assertSame("webhookAssetId:{$name}", $expected, Methods::webhookAssetId($payload));
}

// --- fileReference ---

class StubUuid { public function toString() { return 'file://abc123'; } }
class StubFileWithUuid { public function uuid() { return new StubUuid(); } public function id() { return 'page/video.mp4'; } }
class StubFileNoUuid { public function uuid() { return null; } public function id() { return 'page/video.mp4'; } }

assertSame('fileReference:uuid', 'file://abc123', Methods::fileReference(new StubFileWithUuid()));
assertSame('fileReference:id-fallback', 'page/video.mp4', Methods::fileReference(new StubFileNoUuid()));
assertSame('fileReference:null', null, Methods::fileReference(null));

// --- webhooksEnabled / syncedByWebhook (env-driven, no Kirby option()) ---

unset($_ENV['MUX_WEBHOOK_SECRET']);
assertSame('webhooksEnabled:off', false, Methods::webhooksEnabled());
assertSame('syncedByWebhook:off', false, Methods::syncedByWebhook((object) ['passthrough' => 'file://abc']));

$_ENV['MUX_WEBHOOK_SECRET'] = 'whsec_test';
assertSame('webhooksEnabled:on', true, Methods::webhooksEnabled());
assertSame('syncedByWebhook:with-passthrough', true, Methods::syncedByWebhook((object) ['passthrough' => 'file://abc']));
assertSame('syncedByWebhook:legacy-asset', false, Methods::syncedByWebhook((object) ['id' => 'asset-1']));
unset($_ENV['MUX_WEBHOOK_SECRET']);

echo "All Methods tests passed" . PHP_EOL;
