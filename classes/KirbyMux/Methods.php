<?php
namespace KirbyMux;
use MuxPhp;
class Methods
{
    public static function upload($assetsApi, $url, $type, $quality = null, $maxResolutionTier = null) {
        $file = $_ENV['MUX_DEV'] === "true" ? "https://storage.googleapis.com/muxdemofiles/mux-video-intro.mp4" : $url;
        if ($type === 'audio') {
            $file = $url;
        }

        $input = new MuxPhp\Models\InputSettings(["url" => $file]);
        $staticRenditions = [
            ["resolution" => $type === 'audio' ? 'audio-only' : 'highest']
        ];

        $createAssetRequestData = [
            "input" => $input,
            "playback_policy" => [MuxPhp\Models\PlaybackPolicy::_PUBLIC],
            "static_renditions" => $staticRenditions
        ];

        if ($quality !== null) {
            $createAssetRequestData['video_quality'] = $quality;
        }

        if ($maxResolutionTier !== null) {
            $createAssetRequestData['max_resolution_tier'] = $maxResolutionTier;
        }

        $createAssetRequest = new MuxPhp\Models\CreateAssetRequest($createAssetRequestData);
        $result = $assetsApi->createAsset($createAssetRequest);
        return $result;
    }

    public static function resolveStaticRenditionName($muxData) {
        if (!$muxData || !isset($muxData->static_renditions) || !isset($muxData->static_renditions->files) || !is_array($muxData->static_renditions->files)) {
            return null;
        }

        foreach ($muxData->static_renditions->files as $file) {
            if (!is_object($file)) {
                continue;
            }

            $name = isset($file->name) ? $file->name : null;
            $ext = isset($file->ext) ? $file->ext : null;

            if ($ext === 'mp4' || $ext === 'm4a' || ($name !== null && str_ends_with($name, '.mp4')) || ($name !== null && str_ends_with($name, '.m4a'))) {
                return $name;
            }
        }

        return null;
    }
}
