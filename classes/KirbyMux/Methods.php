<?php
namespace KirbyMux;
use MuxPhp;
class Methods
{
    /**
     * Webhook events that warrant re-syncing the stored asset data.
     * Includes both the current per-rendition events and the legacy
     * `static_renditions` (plural) events.
     */
    const WEBHOOK_EVENTS = [
        'video.asset.ready',
        'video.asset.errored',
        'video.asset.updated',
        'video.asset.static_rendition.ready',
        'video.asset.static_rendition.errored',
        'video.asset.static_rendition.skipped',
        'video.asset.static_renditions.ready',
        'video.asset.static_renditions.errored',
    ];

    public static function upload($assetsApi, $url, $type, $quality = null, $maxResolutionTier = null, $passthrough = null) {
        $file = ($_ENV['MUX_DEV'] ?? null) === "true" ? "https://storage.googleapis.com/muxdemofiles/mux-video-intro.mp4" : $url;
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

        // Lets incoming webhooks map the asset back to its Kirby file without lookups.
        // Mux caps passthrough at 255 characters.
        if (is_string($passthrough) && $passthrough !== '' && strlen($passthrough) <= 255) {
            $createAssetRequestData['passthrough'] = $passthrough;
        }

        $createAssetRequest = new MuxPhp\Models\CreateAssetRequest($createAssetRequestData);
        $result = $assetsApi->createAsset($createAssetRequest);
        return $result;
    }

    /**
     * Stable reference for a Kirby file to store as Mux passthrough.
     * Prefers the UUID (survives page/file renames), falls back to the file id.
     */
    public static function fileReference($file) {
        if (!is_object($file)) {
            return null;
        }

        if (method_exists($file, 'uuid')) {
            try {
                $uuid = $file->uuid();
                if ($uuid) {
                    return $uuid->toString();
                }
            } catch (\Throwable $e) {
                // UUIDs disabled or unavailable: fall through to the id
            }
        }

        return method_exists($file, 'id') ? $file->id() : null;
    }

    /**
     * Poll Mux until the asset has finished processing and its metadata
     * (aspect_ratio, duration, tracks…) is available.
     * Returns the complete asset data, or null if it never became ready.
     */
    public static function waitUntilReady($assetsApi, $assetId, $maxAttempts = 120)
    {
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            try {
                $asset = $assetsApi->getAsset($assetId);
            } catch (\Exception $e) {
                return null;
            }

            $data = $asset ? $asset->getData() : null;
            if (!$data) {
                return null;
            }
            if ($data->getStatus() === 'ready') {
                return $data;
            }

            sleep(1);
            $attempts++;
        }
        return null;
    }

    /**
     * Normalise static_renditions to a list of file entries, supporting:
     * - legacy object `{ status, files: [...] }`
     * - current object `{ files: [{ status, ... }] }`
     * - bare array `[{ status, ... }]`
     */
    private static function renditionFiles($muxData) {
        if (!is_object($muxData) || !isset($muxData->static_renditions)) {
            return [];
        }

        $renditions = $muxData->static_renditions;
        if (is_object($renditions)) {
            $renditions = isset($renditions->files) && is_array($renditions->files) ? $renditions->files : [];
        }

        if (!is_array($renditions)) {
            return [];
        }

        return array_values(array_filter($renditions, 'is_object'));
    }

    /**
     * Overall static-renditions status: 'ready' | 'preparing' | 'errored' | 'disabled' | null (none requested).
     */
    public static function renditionsStatus($muxData) {
        if (!is_object($muxData) || !isset($muxData->static_renditions)) {
            return null;
        }

        $renditions = $muxData->static_renditions;
        $files = self::renditionFiles($muxData);

        $statuses = [];
        foreach ($files as $file) {
            if (isset($file->status) && is_string($file->status) && $file->status !== 'skipped') {
                $statuses[] = $file->status;
            }
        }

        if (count($statuses) > 0) {
            if (in_array('preparing', $statuses, true)) {
                return 'preparing';
            }
            if (in_array('ready', $statuses, true)) {
                return 'ready';
            }
            if (in_array('errored', $statuses, true)) {
                return 'errored';
            }
            return $statuses[0];
        }

        // Legacy files carry no per-file status; their presence means ready
        if (count($files) > 0) {
            return 'ready';
        }

        if (is_object($renditions) && isset($renditions->status) && is_string($renditions->status)) {
            return $renditions->status;
        }

        return 'disabled';
    }

    public static function resolveStaticRenditionName($muxData) {
        foreach (self::renditionFiles($muxData) as $file) {
            // Skip renditions Mux has not finished (or skipped/errored)
            if (isset($file->status) && $file->status !== 'ready') {
                continue;
            }

            $name = isset($file->name) && is_string($file->name) ? $file->name : null;
            $ext = isset($file->ext) ? $file->ext : null;

            if ($name === null) {
                continue;
            }

            if ($ext === 'mp4' || $ext === 'm4a' || str_ends_with($name, '.mp4') || str_ends_with($name, '.m4a')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Static MP4/M4A URL for a Kirby file, falling back to capped-1080p.mp4.
     * Without webhooks, a still-preparing asset is polled (max $maxAttempts s)
     * and the fresh data persisted; with webhooks the stored data is trusted.
     */
    public static function renditionUrl($file, $maxAttempts = 60) {
        if (!is_object($file) || !method_exists($file, 'mux') || $file->mux()->isEmpty()) {
            return null;
        }

        $muxData = json_decode($file->mux());
        if (!is_object($muxData) ||
            !isset($muxData->id) ||
            !isset($muxData->playback_ids) ||
            !is_array($muxData->playback_ids) ||
            !isset($muxData->playback_ids[0]->id)) {
            return null;
        }

        $playbackId = $muxData->playback_ids[0]->id;
        $preparing = (isset($muxData->status) && $muxData->status === 'preparing') ||
                     self::renditionsStatus($muxData) === 'preparing';

        if ($preparing && !self::syncedByWebhook($muxData)) {
            $assetsApi = Auth::assetsApi();
            if (!$assetsApi) {
                return null;
            }

            try {
                for ($attempts = 0; $attempts < $maxAttempts; $attempts++) {
                    $asset = $assetsApi->getAsset($muxData->id);
                    $assetData = $asset ? $asset->getData() : null;
                    if (!$assetData) {
                        break;
                    }

                    $fresh = json_decode((string)$assetData);
                    if (is_object($fresh) &&
                        ($fresh->status ?? null) !== 'preparing' &&
                        self::renditionsStatus($fresh) !== 'preparing') {
                        // Frontend renders have no authenticated user; impersonate
                        // so the file update is permitted outside the Panel.
                        kirby()->impersonate('kirby', function () use ($file, $assetData) {
                            $file->update(['mux' => $assetData]);
                        });
                        $muxData = $fresh;
                        break;
                    }

                    sleep(1);
                }
            } catch (\Exception $e) {
                return null;
            }
        }

        $renditionName = self::resolveStaticRenditionName($muxData);
        if ($renditionName) {
            return "https://stream.mux.com/" . $playbackId . "/" . $renditionName;
        }

        return "https://stream.mux.com/" . $playbackId . "/capped-1080p.mp4";
    }

    /**
     * Webhook signing secret from Kirby config or .env (MUX_WEBHOOK_SECRET).
     */
    public static function webhookSecret() {
        $secret = function_exists('option') ? option('robinscholz.kirby-mux.webhookSecret') : null;
        if (empty($secret)) {
            $secret = $_ENV['MUX_WEBHOOK_SECRET'] ?? null;
        }
        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public static function webhooksEnabled() {
        return self::webhookSecret() !== null;
    }

    /**
     * True when stored data will be kept fresh by webhooks, so render-time
     * polling can be skipped. Assets created before webhooks were enabled
     * carry no passthrough and still need the polling fallback.
     */
    public static function syncedByWebhook($muxData) {
        return self::webhooksEnabled() &&
               is_object($muxData) &&
               !empty($muxData->passthrough);
    }

    /**
     * Verify a `Mux-Signature: t=<timestamp>,v1=<hmac>` header.
     * HMAC-SHA256 over "{timestamp}.{rawBody}", constant-time compare,
     * events older than $tolerance seconds are rejected (replay protection).
     */
    public static function verifySignature($payload, $header, $secret, $tolerance = 300, $now = null) {
        if (!is_string($payload) || !is_string($header) || $header === '' || !is_string($secret) || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === null || !ctype_digit($timestamp) || count($signatures) === 0) {
            return false;
        }

        $now = $now ?? time();
        if (abs($now - (int)$timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the asset id from a verified webhook body, or null when the
     * event is irrelevant (wrong type, malformed, deleted asset…).
     */
    public static function webhookAssetId($payload) {
        $event = is_string($payload) ? json_decode($payload, true) : null;
        if (!is_array($event)) {
            return null;
        }

        $type = $event['type'] ?? null;
        $data = $event['data'] ?? null;
        if (!is_string($type) || !in_array($type, self::WEBHOOK_EVENTS, true) || !is_array($data)) {
            return null;
        }

        // Rendition events carry asset_id; asset events carry the asset itself as data;
        // the event envelope's `object` also names the asset.
        $object = is_array($event['object'] ?? null) ? $event['object'] : [];
        $assetId = $data['asset_id']
            ?? (str_starts_with($type, 'video.asset.static_rendition.') ? null : ($data['id'] ?? null))
            ?? (($object['type'] ?? null) === 'asset' ? ($object['id'] ?? null) : null);

        return is_string($assetId) && $assetId !== '' ? $assetId : null;
    }

    /**
     * Re-fetch an asset and persist it on the Kirby file named by its passthrough.
     * Fetching (instead of trusting the payload) avoids out-of-order events
     * overwriting newer data. Returns a short status message.
     */
    public static function syncAsset($assetsApi, $assetId) {
        try {
            $asset = $assetsApi->getAsset($assetId);
        } catch (\Exception $e) {
            return 'Asset not found';
        }

        $data = $asset ? $asset->getData() : null;
        if (!$data) {
            return 'Asset not found';
        }

        $passthrough = $data->getPassthrough();
        if (empty($passthrough)) {
            // Asset created before webhooks were enabled; self-heal covers it
            return 'No passthrough';
        }

        $kirby = kirby();
        return $kirby->impersonate('kirby', function () use ($kirby, $passthrough, $assetId, $data) {
            $file = $kirby->file($passthrough);
            if (!$file) {
                return 'File not found';
            }

            // Ignore events for an asset the file no longer points to (e.g. replaced file)
            $stored = $file->mux()->isNotEmpty() ? json_decode($file->mux()) : null;
            if (is_object($stored) && isset($stored->id) && $stored->id !== $assetId) {
                return 'Stale asset';
            }

            $update = ['mux' => $data];
            if ($data->getAspectRatio()) {
                $update['resAspect'] = $data->getAspectRatio();
            }
            $file = $file->update($update);

            // Pre-generate the local poster once playback is possible
            if ($data->getStatus() === 'ready' && $file->type() === 'video') {
                try {
                    $file->muxKirbyThumbnail();
                } catch (\Throwable $e) {
                    // Generated lazily on render instead
                }
            }

            return 'OK';
        });
    }
}
