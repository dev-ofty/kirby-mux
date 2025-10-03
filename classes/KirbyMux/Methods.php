<?php
namespace KirbyMux;
use MuxPhp;
class Methods
{
    public static function upload($assetsApi, $url, $type) {
        $file = $_ENV['MUX_DEV'] === "true" ? "https://storage.googleapis.com/muxdemofiles/mux-video-intro.mp4" : $url;
        if($type === 'audio') :
        $file = $url;
        endif;
        $input = new MuxPhp\Models\InputSettings(["url" => $file]);
        $staticRenditions = [
            ["resolution" => "highest"]
        ];
        $createAssetRequest = new MuxPhp\Models\CreateAssetRequest([
            "input" => $input,
            "playback_policy" => [MuxPhp\Models\PlaybackPolicy::_PUBLIC],
            "static_renditions" => $staticRenditions
        ]);
        $result = $assetsApi->createAsset($createAssetRequest);
        return $result;
    }
}
