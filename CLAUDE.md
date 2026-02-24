# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

X for PHP (`dg/twitter-php`) — a PHP library for the X (formerly Twitter) API v2 using OAuth 1.0a authentication. Requires PHP 8.2+ and Guzzle HTTP client.

## Architecture

Two source files in `src/`:

- **`Client.php`** — Main API client (`DG\X\Client`). All X operations: `sendTweet()`, `deleteTweet()`, `getTweet()`, `getMyTweets()`, `getTimeline()`, `getMentions()`, `search()`, `getUser()`, `getUserById()`, `getFollowers()`, `follow()`, `sendDirectMessage()`, plus generic `request()` for any v2 endpoint. Uses Guzzle for HTTP, inline OAuth 1.0a signing, optional file-based caching. Backward compatibility aliases for `DG\Twitter\Twitter` and `Twitter` at file bottom.
- **`Exception.php`** — `DG\X\Exception` with aliases for `DG\Twitter\Exception` and `TwitterException`.

Autoloading uses classmap. Media upload still uses Twitter API v1.1 endpoint (`upload.twitter.com`).

## Commands

```bash
composer tester        # run tests (Nette Tester)
composer phpstan       # run PHPStan level 8
```

## Testing

Tests in `tests/Client/` using Nette Tester (`.phpt` files). Unit tests run always; integration tests require env vars `X_CONSUMER_KEY`, `X_CONSUMER_SECRET`, `X_ACCESS_TOKEN`, `X_ACCESS_TOKEN_SECRET` and are skipped otherwise.
