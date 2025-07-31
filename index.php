<?php
@include_once __DIR__ . '/vendor/autoload.php';
// To get the video aspect ratio and other meta data
require('vendor/james-heinrich/getid3/getid3/getid3.php');
Dotenv\Dotenv::createImmutable(kirby()->root('base'))->load(); // TODO: Add configurable path for .env
Kirby::plugin('robinscholz/kirby-mux', [
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
            // Upload the file to mux
            $result = KirbyMux\Methods::upload($assetsApi, $file->url(), $file->type());
            // Save mux data
            $getID3 = new getID3;
            // Analyze file and store returned data in $ThisFileInfo
            $ThisFileInfo = $getID3->analyze($file->root());
            // Return the width and height
            if($file->type() === 'video') {
                $resolutionX = $ThisFileInfo['video']['resolution_x'];
                $resolutionY = $ThisFileInfo['video']['resolution_y'];
            }
            try {
                $file = $file->update([
                    'mux' => $result->getData(),
                    'resolutionX' => $resolutionX,
                    'resolutionY' => $resolutionY,
                    'resAspect' => $result->getData()->getAspectRatio()
                ]);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        },
        'file.delete:before' => function (Kirby\Cms\File $file) {
            if (!in_array($file->type(), ['video','audio'])) {
                return;
            }
            if (is_null(json_decode($file->mux())) != 1) :
                // Authentication setup
                $assetsApi = KirbyMux\Auth::assetsApi();
                // Get mux Id
                $muxId = json_decode($file->mux())->id;
                // Delete Asset
                try {
                    $assetsApi->deleteAsset($muxId);
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            endif;
        },
        'file.replace:before' => function (Kirby\Cms\File $file, Kirby\Filesystem\File $upload) {
            if (!in_array($upload->type(), ['video','audio'])) {
                return;
            }
            // Authentication setup
            $assetsApi = KirbyMux\Auth::assetsApi();
            // Get old mux Id
            $muxId = json_decode($file->mux())->id;
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
            // Upload new file to mux
            $result = KirbyMux\Methods::upload($assetsApi, $newFile->url());
            // Save playback Id
            try {
                $newFile = $newFile->update([
                    'mux' => $result->getData()
                ]);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    ]
]);
