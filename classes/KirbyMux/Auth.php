<?php
namespace KirbyMux;
use MuxPhp;
use GuzzleHttp;

class Auth
{
    public static function assetsApi() {
        // Guard clause: Check if credentials are available
        if (!Env::hasCredentials()) {
            return null;
        }

        try {
            // Authentication setup
            $config = MuxPhp\Configuration::getDefaultConfiguration()
                ->setUsername($_ENV['MUX_TOKEN_ID'])
                ->setPassword($_ENV['MUX_TOKEN_SECRET']);

            // API client initialization
            $assetsApi = new MuxPhp\Api\AssetsApi(
                new GuzzleHttp\Client(),
                $config
            );

            return $assetsApi;
        } catch (\Exception $e) {
            // Return null if authentication fails
            return null;
        }
    }
}
