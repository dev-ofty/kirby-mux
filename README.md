# 📼 Kirby Mux

A [Kirby](https://getkirby.com) plugin to upload video and audio files to [Mux](https://mux.com).

> **Fork Notice**: This is a fork of [robinscholz/kirby-mux](https://github.com/robinscholz/kirby-mux) with additional features and Vue 3 support.

## Installation

### Download

Download and copy this repository to `/site/plugins/kirby-mux`.

### Git submodule

```
git submodule add https://github.com/dev-ofty/kirby-mux.git site/plugins/kirby-mux
```

### Composer

Since this package is not on Packagist, you need to add the repository to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/dev-ofty/kirby-mux"
    }
  ],
  "require": {
    "devofty/kirby-mux": "dev-main"
  }
}
```

Then run:
```bash
composer update
```

### Post-Installation

After installing via any method, you **must** run:

```bash
cd site/plugins/kirby-mux
composer install
```

This installs the required PHP dependencies (Mux PHP SDK, dotenv, getID3).

#### Installation with Public Folder Structure

If you're using a public folder structure (where your web root is `/public` for security), the plugin will automatically detect and work with this setup. The autoloader will be found in the correct location.

Example structure:
```
/your-project/
├── public/              ← Web root
│   └── index.php
├── site/
│   ├── config/
│   └── plugins/
│       └── kirby-mux/
├── vendor/              ← Composer dependencies
└── .env                 ← Environment file
```

## Configuration

### Environment Variables

Add a `.env` file to your Kirby installation with the following properties:

| Key              | Type      | Description |
| ---------------- | --------- | ----------- |
| MUX_TOKEN_ID     | `String`  | Your Mux API Access Token ID |
| MUX_TOKEN_SECRET | `String`  | Your Mux API Access Token Secret |
| MUX_DEV          | `Boolean` | Enable development mode |

#### MUX_TOKEN_ID

In order for the plugin to work, you need to create an `API Access Token` on the MUX dashboard. Save the `Token ID` here.

#### MUX_TOKEN_SECRET

Save the associated `Token Secret` here.

#### MUX_DEV

Set this to `true` for local development. Instead of the actual video, the plugin will upload a test video to Mux. This is necessary, since videos need to be publicly hosted for Mux to be able to import them.

> **NOTE:** This plugin includes a .env.example file as well.

#### `.env` File Location

The plugin automatically searches for your `.env` file in the following locations:

1. **Plugin directory** - `site/plugins/kirby-mux/.env`
2. **Kirby root** - `/path/to/your-project/.env` (standard installation)
3. **Parent directories** - Automatically searches up the directory tree
4. **Public folder setups** - Works with installations using a separate `public/` directory

If you have a non-standard installation or need to specify a custom path, see the **envPath** option below.

### Plugin Options

Add the following options to your `site/config/config.php` file:

```php
return [
    'robinscholz.kirby-mux.optimizeDiskSpace' => false,
    'robinscholz.kirby-mux.envPath' => null, // optional
];
```

#### optimizeDiskSpace

**Type:** `Boolean`
**Default:** `false`

When set to `true`, the plugin will download and store MP4 video files locally after uploading to Mux. This creates a local backup of your videos and reduces dependency on Mux's streaming service.

When set to `false` (default), videos are only stored on Mux and streamed from there, saving local disk space.

#### envPath

**Type:** `String|null`
**Default:** `null`

Specify a custom path to your `.env` file or the directory containing it. This is useful for non-standard Kirby installations or when using a `public/` folder structure.

**Examples:**

```php
// Standard installation with public folder
return [
    'robinscholz.kirby-mux.envPath' => dirname(__DIR__, 2), // Points to project root
];
```

```php
// Custom .env location
return [
    'robinscholz.kirby-mux.envPath' => '/var/www/myproject/.env',
];
```

```php
// Directory containing .env
return [
    'robinscholz.kirby-mux.envPath' => '/var/www/myproject',
];
```

> **Note:** If not specified, the plugin automatically searches common Kirby installation locations.

## What's New in This Fork

This fork includes several enhancements over the original:

1. **Audio Support**: Upload and stream audio files (MP3, etc.) in addition to videos
2. **Video Dimension Analysis**: Automatically extracts and stores video dimensions and aspect ratios using getID3
3. **MP4 Support**: Enables standard MP4 downloads alongside HLS streaming
4. **Vue 3 Components**: All components updated to use Vue 3 Composition API with modern best practices
5. **Improved Error Handling**: Better error handling and user feedback
6. **Flexible Installation**: Automatic detection of different Kirby installation structures (standard, public folder, composer-managed)
7. **Configurable `.env` Path**: Set a custom path for your environment file via plugin options

## Caveats

The plugin does not include any frontend facing code or snippets. In order to stream the videos from Mux you need to implement your own custom video player. [HLS.js](https://github.com/video-dev/hls.js/) is a good option for example.

## Plugin Development

[Kirbyup](https://github.com/johannschopplich/kirbyup) is used for the development and build setup.

Kirbyup will be fetched remotely with your first `npm run` command, which may take a short amount of time.

### Development

Start the dev process with:

```
npm run dev
```

This will automatically update the `index.js` and `index.css` of the plugin as soon as changes are made.
Reload the Panel to see the code changes reflected.

### Production

Build final files with:

```
npm run build
```

This will automatically create a minified and optimized version of the `index.js` and `index.css`.

## License

MIT

## Credits

- Original plugin by [Robin Scholz](https://github.com/robinscholz)
- Fork with additional features by [Dev Ofty](https://github.com/dev-ofty)
