<?php
/**
 * Kirby Mux Plugin
 *
 * Autoloader: Attempts to load from multiple common Kirby installation patterns
 * - Standard: site/plugins/kirby-mux/vendor/autoload.php
 * - Public folder: site/plugins/kirby-mux/vendor/autoload.php (relative from public)
 * - Composer managed: vendor/autoload.php (root level)
 */

// Try to load autoloader from various common locations
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',           // Plugin's own vendor (standard)
    __DIR__ . '/../../../vendor/autoload.php',  // Root vendor (standard Kirby)
    __DIR__ . '/../../../../vendor/autoload.php', // Root vendor (with public folder)
];

$autoloaderLoaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaderLoaded = true;
        break;
    }
}

// Guard clause: Exit if autoloader couldn't be loaded
if (!$autoloaderLoaded) {
    throw new Exception('Kirby Mux: Composer autoloader not found. Please run "composer install".');
}

/**
 * Load environment variables early
 * This attempts to find and load .env from common locations
 * Can be overridden later via the envPath option
 */
KirbyMux\Env::load();

Kirby::plugin('robinscholz/kirby-mux', [
    'options' => [
        'envPath' => null, // Custom path to .env file or directory (optional)
        'videoQuality' => 'plus', // 'basic' | 'plus' | 'premium' | callable($file) => string
        'maxResolutionTier' => '1080p', // '1080p' | '1440p' | '2160p'
        'webhookSecret' => null, // Mux webhook signing secret; enables async processing (or MUX_WEBHOOK_SECRET in .env)
    ],
    'routes' => [
        [
            'pattern' => 'mux/webhook',
            'method' => 'POST',
            'action' => function () {
                $secret = KirbyMux\Methods::webhookSecret();
                if (!$secret) {
                    return new Kirby\Http\Response('Webhook secret not configured', 'text/plain', 500);
                }

                $payload = file_get_contents('php://input');
                $header = $_SERVER['HTTP_MUX_SIGNATURE'] ?? '';
                if (!KirbyMux\Methods::verifySignature($payload, $header, $secret)) {
                    return new Kirby\Http\Response('Invalid signature', 'text/plain', 403);
                }

                $assetId = KirbyMux\Methods::webhookAssetId($payload);
                if (!$assetId) {
                    return new Kirby\Http\Response('Ignored', 'text/plain', 200);
                }

                $assetsApi = KirbyMux\Auth::assetsApi();
                if (!$assetsApi) {
                    return new Kirby\Http\Response('Mux credentials missing', 'text/plain', 500);
                }

                try {
                    $message = KirbyMux\Methods::syncAsset($assetsApi, $assetId);
                } catch (Throwable $e) {
                    // 500 lets Mux retry transient failures (e.g. locked content file)
                    return new Kirby\Http\Response('Sync failed', 'text/plain', 500);
                }

                // 200 even for unmappable events (no passthrough, stale asset…) so Mux stops retrying
                return new Kirby\Http\Response($message, 'text/plain', 200);
            }
        ]
    ],
    'translations' => [
        'en' => [
            'field.blocks.mux-video.thumbnail' => 'Generate thumbnail from frame',
            'field.blocks.mux-video.thumbnail.help' => 'In seconds',
        ],
        'de' => [
            'field.blocks.mux-video.thumbnail' => 'Thumbnail aus Frame generieren',
            'field.blocks.mux-video.thumbnail.help' => 'In Sekunden',
        ],
    ],
    'blueprints' => [
        'files/mux-video' => __DIR__ . '/blueprints/files/mux-video.yml',
        'blocks/mux-video' => __DIR__ . '/blueprints/blocks/mux-video.yml',
        'files/mux-audio' => __DIR__ . '/blueprints/files/mux-audio.yml',
        'blocks/mux-audio' => __DIR__ . '/blueprints/blocks/mux-audio.yml'
	],
    'fileMethods' => [
        'muxPlaybackId' => function () {
            if (!$this->mux()) {
                return null;
            }
            $muxData = json_decode($this->mux());

            // Self-heal: if the stored snapshot predates asset readiness, refresh it once
            // Skipped when webhooks keep this asset in sync (no API call per render)
            if ($muxData && isset($muxData->id) && (!isset($muxData->status) || $muxData->status !== 'ready') && !KirbyMux\Methods::syncedByWebhook($muxData)) {
                $assetsApi = KirbyMux\Auth::assetsApi();
                if ($assetsApi) {
                    try {
                        $asset = $assetsApi->getAsset($muxData->id);
                        $data  = $asset ? $asset->getData() : null;
                        if ($data && $data->getStatus() === 'ready') {
                            $file = $this;
                            // Frontend renders have no authenticated user; impersonate
                            // so the file update is permitted outside the Panel.
                            kirby()->impersonate('kirby', function () use ($file, $data) {
                                $file->update(['mux' => $data]);
                            });
                            $muxData = json_decode((string)$data);
                        }
                    } catch (Exception $e) {
                        // Keep the stored snapshot; playback still works via playback_ids
                    }
                }
            }

            if (!$muxData ||
                !isset($muxData->playback_ids) ||
                !is_array($muxData->playback_ids) ||
                !isset($muxData->playback_ids[0]) ||
                !isset($muxData->playback_ids[0]->id)) {
                return null;
            }
            return $muxData->playback_ids[0]->id;
        },
        'muxUrlLow' => function () {
            return KirbyMux\Methods::renditionUrl($this);
        },
        'muxUrlHigh' => function () {
            return KirbyMux\Methods::renditionUrl($this);
        },
        'muxUrlStream' => function () {
            $playbackId = $this->muxPlaybackId();
            if (!$playbackId) {
                return null;
            }
            return "https://stream.mux.com/" . $playbackId . ".m3u8";
        },
        'muxThumbnail' => function ($width = null, $height = null, $time = null, String $extension = 'jpg') {
            $playbackId = $this->muxPlaybackId();
            if (!$playbackId) {
                return null;
            }

            $url = "https://image.mux.com/" . $playbackId . "/thumbnail." . $extension;

            $params = [];
            if ($width !== null) {
                $params['width'] = $width;
            }
            if ($height !== null) {
                $params['height'] = $height;
                $params['fit_mode'] = 'smartcrop';
            }
            if ($time !== null) {
                $params['time'] = $time;
            }
            if (count($params)) {
                $url .= '?' . http_build_query($params);
            }
            return $url;
        },
        'muxThumbnailAnimated' => function ($width = null, $height = null, $start = null, $end = null, $fps = null, String $extension = 'gif') {
            $playbackId = $this->muxPlaybackId();
            if (!$playbackId) {
                return null;
            }

            $url = "https://image.mux.com/" . $playbackId . "/animated." . $extension;

            $params = [];
            if ($width !== null) {
                $params['width'] = $width;
            }
            if ($height !== null) {
                $params['height'] = $height;
            }
            if ($start !== null) {
                $params['start'] = $start;
            }
            if ($end !== null) {
                $params['end'] = $end;
            }
            if ($fps !== null) {
                $params['fps'] = $fps;
            }
            if (count($params)) {
                $url .= '?' . http_build_query($params);
            }
            return $url;
        },
        'muxKirbyThumbnail' => function () {
            if (!$this->parent() || $this->type() !== 'video') {
                return null;
            }

            $muxThumbnail = $this->parent()->file(F::name($this->filename()) . '-thumbnail.jpg');

            if (!$muxThumbnail) {
                $playbackId = $this->muxPlaybackId();
                if (!$playbackId) {
                    return null;
                }

                try {
                    $url = "https://image.mux.com/" . $playbackId . "/thumbnail.jpg";
                    $imagedata = @file_get_contents($url);

                    if ($imagedata === false) {
                        return null;
                    }

                    $parentRoot = $this->parent()->root();
                    if (!$parentRoot) {
                        return null;
                    }

                    F::write($parentRoot . '/' . $this->name() . '-thumbnail.jpg', $imagedata);
                    $muxThumbnail = $this->parent()->file(F::name($this->filename()) . '-thumbnail.jpg');
                } catch (Exception $e) {
                    return null;
                }
            }

            return $muxThumbnail;
        },
    ],
    'hooks' => [
        'file.create:after' => function (Kirby\Cms\File $file) {
            if (!in_array($file->type(), ['video','audio'])) {
                return;
            }

            // Resolution is rather the width and height to calculate the aspect-ratio from get ID3 vendor
            $resolutionX = '';
            $resolutionY = '';

            // Authenticate
            $assetsApi = KirbyMux\Auth::assetsApi();
            if (!$assetsApi) {
                return;
            }

            $quality = option('robinscholz.kirby-mux.videoQuality', 'plus');
            if (is_callable($quality)) {
                $quality = $quality($file);
            }

            $maxResolutionTier = option('robinscholz.kirby-mux.maxResolutionTier', '1080p');

            // Analyze locally before uploading, so the asset is persisted right after
            // createAsset and an early webhook cannot be overwritten by this hook
            $getID3 = new getID3;
            // Analyze file and store returned data in $ThisFileInfo
            $ThisFileInfo = $getID3->analyze($file->root());

            // Return the width and height for video files
            if($file->type() === 'video') {
                $resolutionX = isset($ThisFileInfo['video']['resolution_x']) ? $ThisFileInfo['video']['resolution_x'] : '';
                $resolutionY = isset($ThisFileInfo['video']['resolution_y']) ? $ThisFileInfo['video']['resolution_y'] : '';
            }

            // Upload the file to mux (returns synchronously with playback_id, even while processing)
            $result = KirbyMux\Methods::upload($assetsApi, $file->url(), $file->type(), $quality, $maxResolutionTier, KirbyMux\Methods::fileReference($file));
            if (!$result || !$result->getData()) {
                return;
            }

            // With webhooks, store the initial response and let the webhook fill in
            // aspect_ratio, duration, tracks and renditions — no blocking request.
            // Without, wait for Mux to finish analyzing so the persisted JSON is
            // complete; if it never reports ready, the self-heal in muxPlaybackId()
            // finishes the job.
            $assetData = KirbyMux\Methods::webhooksEnabled()
                ? $result->getData()
                : (KirbyMux\Methods::waitUntilReady($assetsApi, $result->getData()->getId()) ?? $result->getData());

            try {
                $file->update([
                    'mux' => $assetData,
                    'resolutionX' => $resolutionX,
                    'resolutionY' => $resolutionY,
                    'resAspect' => $assetData->getAspectRatio()
                ]);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        },
        'file.delete:before' => function (Kirby\Cms\File $file) {
            if (!in_array($file->type(), ['video','audio'])) {
                return;
            }

            if (!$file->mux()) {
                return;
            }

            $muxData = json_decode($file->mux());
            if (!$muxData || !isset($muxData->id)) {
                return;
            }

            // Authentication setup
            $assetsApi = KirbyMux\Auth::assetsApi();
            if (!$assetsApi) {
                return;
            }

            // Get mux Id
            $muxId = $muxData->id;

            // Delete Asset
            try {
                $assetsApi->deleteAsset($muxId);

                // Clean up thumbnail files for video
                if ($file->type() === 'video' && $file->parent() && $file->parent()->root()) {
                    $parentRoot = $file->parent()->root();
                    $thumbnailPath = $parentRoot . '/' . $file->name() . '-thumbnail.jpg';
                    $thumbnailMetaPath = $parentRoot . '/' . $file->name() . '-thumbnail.jpg.txt';

                    if (file_exists($thumbnailPath)) {
                        F::remove($thumbnailPath);
                    }
                    if (file_exists($thumbnailMetaPath)) {
                        F::remove($thumbnailMetaPath);
                    }
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        },
        'file.replace:before' => function (Kirby\Cms\File $file, Kirby\Filesystem\File $upload) {
            if (!in_array($upload->type(), ['video','audio'])) {
                return;
            }

            // Authentication setup
            $assetsApi = KirbyMux\Auth::assetsApi();
            if (!$assetsApi) {
                return;
            }

            // Get old mux Id with defensive checks
            if (!$file->mux()) {
                return;
            }

            $muxData = json_decode($file->mux());
            if (!$muxData || !isset($muxData->id)) {
                return;
            }

            $muxId = $muxData->id;

            // Delete old asset
            try {
                $assetsApi->deleteAsset($muxId);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        },
        'file.replace:after' => function (Kirby\Cms\File $newFile, Kirby\Cms\File $oldFile) {
            if (!in_array($newFile->type(), ['video','audio'])) {
                return;
            }

            // Authentication setup
            $assetsApi = KirbyMux\Auth::assetsApi();
            if (!$assetsApi) {
                return;
            }

            $quality = option('robinscholz.kirby-mux.videoQuality', 'plus');
            if (is_callable($quality)) {
                $quality = $quality($newFile);
            }

            $maxResolutionTier = option('robinscholz.kirby-mux.maxResolutionTier', '1080p');

            // Analyze locally before uploading, so the asset is persisted right after
            // createAsset and an early webhook cannot be overwritten by this hook
            $getID3 = new getID3;
            // Analyze file and store returned data in $ThisFileInfo
            $ThisFileInfo = $getID3->analyze($newFile->root());

            // Resolution is rather the width and height to calculate the aspect-ratio from get ID3 vendor
            $resolutionX = '';
            $resolutionY = '';

            // Return the width and height for video files
            if($newFile->type() === 'video') {
                $resolutionX = isset($ThisFileInfo['video']['resolution_x']) ? $ThisFileInfo['video']['resolution_x'] : '';
                $resolutionY = isset($ThisFileInfo['video']['resolution_y']) ? $ThisFileInfo['video']['resolution_y'] : '';
            }

            // Upload new file to mux (returns synchronously with playback_id, even while processing)
            $result = KirbyMux\Methods::upload($assetsApi, $newFile->url(), $newFile->type(), $quality, $maxResolutionTier, KirbyMux\Methods::fileReference($newFile));
            if (!$result || !$result->getData()) {
                return;
            }

            // With webhooks, store the initial response and let the webhook fill in
            // aspect_ratio, duration, tracks and renditions — no blocking request.
            // Without, wait for Mux to finish analyzing so the persisted JSON is
            // complete; if it never reports ready, the self-heal in muxPlaybackId()
            // finishes the job.
            $assetData = KirbyMux\Methods::webhooksEnabled()
                ? $result->getData()
                : (KirbyMux\Methods::waitUntilReady($assetsApi, $result->getData()->getId()) ?? $result->getData());

            try {
                $newFile->update([
                    'mux' => $assetData,
                    'resolutionX' => $resolutionX,
                    'resolutionY' => $resolutionY,
                    'resAspect' => $assetData->getAspectRatio()
                ]);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    ]
]);

/**
 * If a custom envPath is configured via Kirby config, reload the environment from that location
 * This will be picked up when Kirby initializes and the option becomes available
 *
 * Example in config.php:
 * return [
 *     'robinscholz.kirby-mux.envPath' => '/path/to/your/.env'
 * ];
 */
