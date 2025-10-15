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
        'optimizeDiskSpace' => false,
        'envPath' => null, // Custom path to .env file or directory (optional)
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
            if (!$this->mux()) {
                return null;
            }

            $muxData = json_decode($this->mux());
            if (!$muxData ||
                !isset($muxData->id) ||
                !isset($muxData->playback_ids) ||
                !is_array($muxData->playback_ids) ||
                !isset($muxData->playback_ids[0]) ||
                !isset($muxData->playback_ids[0]->id)) {
                return null;
            }

            $assetId = $muxData->id;
            $playbackId = $muxData->playback_ids[0]->id;
            $preparingRenditions = (isset($muxData->status) && $muxData->status === 'preparing') ||
                                   (isset($muxData->static_renditions) && isset($muxData->static_renditions->status) && $muxData->static_renditions->status !== 'ready');

            if ($preparingRenditions) {
                // Authenticate
                $assetsApi = KirbyMux\Auth::assetsApi();
                if (!$assetsApi) {
                    return null;
                }

                try {
                    $maxAttempts = 60; // Maximum 60 seconds wait time
                    $attempts = 0;
                    while ($attempts < $maxAttempts) {
                        $waitingAsset = $assetsApi->getAsset($assetId);
                        if (!$waitingAsset || !$waitingAsset->getData()) {
                            break;
                        }

                        $assetData = $waitingAsset->getData();
                        if (!isset($assetData['static_renditions']) || $assetData['static_renditions']['status'] !== 'ready') {
                            sleep(1);
                            $attempts++;
                        } else {
                            $this->update([
                                'mux' => $assetData
                            ]);
                            break;
                        }
                    }
                } catch (Exception $e) {
                    // Handle error gracefully
                    return null;
                }
            }

            return "https://stream.mux.com/" . $playbackId . "/capped-1080p.mp4";
        },
        'muxUrlHigh' => function () {
            if (!$this->mux()) {
                return null;
            }

            $muxData = json_decode($this->mux());
            if (!$muxData ||
                !isset($muxData->id) ||
                !isset($muxData->playback_ids) ||
                !is_array($muxData->playback_ids) ||
                !isset($muxData->playback_ids[0]) ||
                !isset($muxData->playback_ids[0]->id)) {
                return null;
            }

            $assetId = $muxData->id;
            $playbackId = $muxData->playback_ids[0]->id;
            $preparingRenditions = (isset($muxData->status) && $muxData->status === 'preparing') ||
                                   (isset($muxData->static_renditions) && isset($muxData->static_renditions->status) && $muxData->static_renditions->status !== 'ready');

            if ($preparingRenditions) {
                // Authenticate
                $assetsApi = KirbyMux\Auth::assetsApi();
                if (!$assetsApi) {
                    return null;
                }

                try {
                    $maxAttempts = 60; // Maximum 60 seconds wait time
                    $attempts = 0;
                    while ($attempts < $maxAttempts) {
                        $waitingAsset = $assetsApi->getAsset($assetId);
                        if (!$waitingAsset || !$waitingAsset->getData()) {
                            break;
                        }

                        $assetData = $waitingAsset->getData();
                        if (!isset($assetData['static_renditions']) || $assetData['static_renditions']['status'] !== 'ready') {
                            sleep(1);
                            $attempts++;
                        } else {
                            $this->update([
                                'mux' => $assetData
                            ]);
                            break;
                        }
                    }
                } catch (Exception $e) {
                    return null;
                }
            }

            $static_renditions = isset($muxData->static_renditions) ? $muxData->static_renditions : null;

            return ($static_renditions &&
                    $static_renditions->status === 'ready' &&
                    isset($static_renditions->files) &&
                    is_array($static_renditions->files) &&
                    count($static_renditions->files) > 1)
                ? "https://stream.mux.com/" . $playbackId . "/high.mp4"
                : "https://stream.mux.com/" . $playbackId . "/capped-1080p.mp4";
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

            // Upload the file to mux
            $result = KirbyMux\Methods::upload($assetsApi, $file->url(), $file->type());
            if (!$result || !$result->getData()) {
                return;
            }

            // Save mux data
            $getID3 = new getID3;
            // Analyze file and store returned data in $ThisFileInfo
            $ThisFileInfo = $getID3->analyze($file->root());

            // Return the width and height for video files
            if($file->type() === 'video') {
                $resolutionX = isset($ThisFileInfo['video']['resolution_x']) ? $ThisFileInfo['video']['resolution_x'] : '';
                $resolutionY = isset($ThisFileInfo['video']['resolution_y']) ? $ThisFileInfo['video']['resolution_y'] : '';
            }

            try {
                $file = $file->update([
                    'mux' => $result->getData(),
                    'resolutionX' => $resolutionX,
                    'resolutionY' => $resolutionY,
                    'resAspect' => $result->getData()->getAspectRatio()
                ]);

                // Wait for the asset to become ready...
                if ($result->getData()->getStatus() !== 'ready') {
                    $maxAttempts = 300; // Maximum 300 seconds (5 minutes) wait time
                    $attempts = 0;
                    while ($attempts < $maxAttempts) {
                        $waitingAsset = $assetsApi->getAsset($result->getData()->getId());
                        if (!$waitingAsset || !$waitingAsset->getData() || $waitingAsset->getData()->getStatus() !== 'ready') {
                            sleep(1);
                            $attempts++;
                        } else {
                            // Only generate thumbnail for video files
                            if ($file->type() === 'video') {
                                $playbackIds = $result->getData()->getPlaybackIds();
                                if ($playbackIds && count($playbackIds) > 0) {
                                    $url = "https://image.mux.com/" . $playbackIds[0]->getId() . "/thumbnail.jpg";
                                    $imagedata = @file_get_contents($url);

                                    if ($imagedata !== false && $file->parent() && $file->parent()->root()) {
                                        F::write($file->parent()->root() . '/' . $file->name() . '-thumbnail.jpg', $imagedata);
                                    }
                                }
                            }

                            $file = $file->update([
                                'mux' => $waitingAsset->getData()
                            ]);

                            // Optionally download video for disk space optimization
                            if (option('robinscholz.kirby-mux.optimizeDiskSpace', false) && $file->type() === 'video') {
                                $lowUrl = $file->muxUrlLow();
                                if ($lowUrl && $file->parent() && $file->parent()->root()) {
                                    $videodata = @file_get_contents($lowUrl);
                                    if ($videodata !== false) {
                                        F::write($file->parent()->root() . '/' . $file->name() . '.mp4', $videodata);
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
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

            // Upload new file to mux
            $result = KirbyMux\Methods::upload($assetsApi, $newFile->url(), $newFile->type());
            if (!$result || !$result->getData()) {
                return;
            }

            // Save mux data
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

            // Save playback Id
            try {
                $newFile = $newFile->update([
                    'mux' => $result->getData(),
                    'resolutionX' => $resolutionX,
                    'resolutionY' => $resolutionY,
                    'resAspect' => $result->getData()->getAspectRatio()
                ]);

                // Wait for the asset to become ready...
                if ($result->getData()->getStatus() !== 'ready') {
                    $maxAttempts = 300; // Maximum 300 seconds (5 minutes) wait time
                    $attempts = 0;
                    while ($attempts < $maxAttempts) {
                        $waitingAsset = $assetsApi->getAsset($result->getData()->getId());
                        if (!$waitingAsset || !$waitingAsset->getData() || $waitingAsset->getData()->getStatus() !== 'ready') {
                            sleep(1);
                            $attempts++;
                        } else {
                            // Only generate thumbnail for video files
                            if ($newFile->type() === 'video') {
                                $playbackIds = $result->getData()->getPlaybackIds();
                                if ($playbackIds && count($playbackIds) > 0) {
                                    $url = "https://image.mux.com/" . $playbackIds[0]->getId() . "/thumbnail.jpg?time=0";
                                    $imagedata = @file_get_contents($url);

                                    if ($imagedata !== false && $newFile->parent() && $newFile->parent()->root()) {
                                        F::write($newFile->parent()->root() . '/' . $newFile->name() . '-thumbnail.jpg', $imagedata);
                                    }
                                }
                            }

                            $newFile = $newFile->update([
                                'mux' => $waitingAsset->getData()
                            ]);

                            // Optionally download video for disk space optimization
                            if (option('robinscholz.kirby-mux.optimizeDiskSpace', false) && $newFile->type() === 'video') {
                                $lowUrl = $newFile->muxUrlLow();
                                if ($lowUrl && $newFile->parent() && $newFile->parent()->root()) {
                                    $videodata = @file_get_contents($lowUrl);
                                    if ($videodata !== false) {
                                        F::write($newFile->parent()->root() . '/' . $newFile->name() . '.mp4', $videodata);
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
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
