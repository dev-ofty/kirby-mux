<?php
namespace KirbyMux;

use Dotenv\Dotenv;

class Env
{
    /**
     * Load environment variables from .env file
     *
     * @param string|null $envPath Custom path to .env file or directory containing .env
     * @return void
     */
    public static function load($envPath = null)
    {
        // Guard clause: Check if .env is already loaded
        if (getenv('MUX_TOKEN_ID') !== false) {
            return;
        }

        // If no path provided, try to get from Kirby config
        if ($envPath === null && function_exists('option')) {
            try {
                $envPath = option('robinscholz.kirby-mux.envPath', null);
            } catch (\Exception $e) {
                // Kirby might not be fully loaded yet, continue with fallback
            }
        }

        // Determine the .env directory path
        $envDirectory = self::resolveEnvDirectory($envPath);

        // Guard clause: If directory doesn't exist, try fallback locations
        if (!$envDirectory || !is_dir($envDirectory)) {
            $envDirectory = self::findEnvDirectory();
        }

        // Guard clause: If still no valid directory, exit silently
        if (!$envDirectory || !is_dir($envDirectory)) {
            return;
        }

        // Guard clause: Check if .env file exists in the directory
        $envFile = $envDirectory . '/.env';
        if (!file_exists($envFile)) {
            return;
        }

        try {
            Dotenv::createImmutable($envDirectory)->load();
        } catch (\Exception $e) {
            // Silently fail - environment variables might be set via server config
            return;
        }
    }

    /**
     * Resolve the .env directory from the provided path
     *
     * @param string|null $path Path to .env file or directory
     * @return string|null Resolved directory path
     */
    private static function resolveEnvDirectory($path)
    {
        // Guard clause: No path provided
        if (!$path || !is_string($path)) {
            return null;
        }

        // Normalize path separators
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        // If path ends with .env, get the directory
        if (basename($path) === '.env') {
            return dirname($path);
        }

        // Otherwise assume it's a directory path
        return $path;
    }

    /**
     * Find .env file in common Kirby installation locations
     *
     * @return string|null Path to directory containing .env file
     */
    private static function findEnvDirectory()
    {
        // Start from plugin directory
        $pluginDir = dirname(dirname(__DIR__));

        // Common Kirby installation patterns to check
        $locationsToCheck = [
            // Standard installation: /site/plugins/kirby-mux -> /
            $pluginDir . '/../../../',
            // With public folder: /site/plugins/kirby-mux -> /
            $pluginDir . '/../../../../',
            // Plugin directory itself
            $pluginDir,
            // Site directory
            $pluginDir . '/../../',
            // Public folder
            $pluginDir . '/../../../public/',
        ];

        foreach ($locationsToCheck as $location) {
            $realPath = realpath($location);

            // Guard clause: Skip if path doesn't exist
            if (!$realPath || !is_dir($realPath)) {
                continue;
            }

            // Check if .env exists in this location
            if (file_exists($realPath . '/.env')) {
                return $realPath;
            }
        }

        return null;
    }

    /**
     * Check if required environment variables are set
     *
     * @return bool True if MUX credentials are available
     */
    public static function hasCredentials()
    {
        return !empty($_ENV['MUX_TOKEN_ID']) && !empty($_ENV['MUX_TOKEN_SECRET']);
    }
}
