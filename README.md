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

## Configuration

Add a .env file to the root of your Kirby plugin with the following properties:

| Key              | Type      |
| ---------------- | --------- |
| MUX_TOKEN_ID     | `String`  |
| MUX_TOKEN_SECRET | `String`  |
| MUX_DEV          | `Boolean` |

### MUX_TOKEN_ID

In order for the plugin to work, you need to create an `API Access Token` on the MUX dashboard. Save the `Token ID` here.

### MUX_TOKEN_SECRET

Save the associated `Token Secret` here.

### MUX_DEV

Set this to `true` for local development. Instead of the actual video, the plugin will upload a test video to Mux. This is neccessary, since videos need to be publicly hosted for Mux to be able to import them.

> **NOTE:** This plugin includes a .env.example file as well.

## What's New in This Fork

This fork includes several enhancements over the original:

1. **Audio Support**: Upload and stream audio files (MP3, etc.) in addition to videos
2. **Video Dimension Analysis**: Automatically extracts and stores video dimensions and aspect ratios using getID3
3. **MP4 Support**: Enables standard MP4 downloads alongside HLS streaming
4. **Vue 3 Components**: All components updated to use Vue 3 Composition API with modern best practices
5. **Improved Error Handling**: Better error handling and user feedback

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
