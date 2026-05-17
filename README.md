# EWEB - MC Video Popup

## Overview

`EWEB - MC Video Popup` displays a YouTube video popup with autoplay (muted), language-based video selection (ES/EN), persistence controls, and global activation through a WordPress admin settings page.

## Core Features

- Global popup enable/disable from WordPress settings.
- Language-aware video source selection (browser or site language mode).
- Optional "show once in N hours" persistence behavior.
- Optional redirect behavior when closing with the `X` button.
- Optional clickable top layer over the video.
- Optional shortcode for page-level use: `[eweb_mc_video_popup]`.
- Query flags for QA:
  - `?mcvp=1` forces popup display.
  - `?mcvp=0` suppresses popup display.

## Technical Notes

- WordPress: `6.1+`
- PHP: `7.4.33+` (legacy-host compatibility requirement in current implementation)
- Text Domain: `eweb-mc-video-popup`
- Current version: `1.1.4`
- Option key: `eweb_mc_video_popup_options`

## Main Configuration Fields

- `enabled`
- `only_home`
- `exclude_paths`
- `video_es`
- `video_en`
- `target`
- `show_once`
- `hours`
- `start`
- `mute`
- `controls`
- `language_mode`
- `open_on_close`
- `toplink_enabled`
- `disable_for_admin`
- `debug_mode`

## Architecture

- `mc-video-popup.php`: bootstrap, settings registration, rendering, and shortcode.
- `assets/css/popup.css`: popup visuals and responsive behavior.
- `assets/js/popup.js`: popup logic, persistence, and player behavior.
- `readme.txt`: WordPress-style metadata and changelog.

## Validation Status (AI-Vault pass)

- PHP syntax check: pass
- PHPCS run: executed, style/documentation debt detected
- Unit tests: not available yet in this plugin
