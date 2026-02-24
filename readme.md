X for PHP
=========

[![Downloads this Month](https://img.shields.io/packagist/dm/dg/twitter-php.svg)](https://packagist.org/packages/dg/twitter-php)
[![Tests](https://github.com/dg/twitter-php/workflows/Tests/badge.svg?branch=master)](https://github.com/dg/twitter-php/actions)
[![Latest Stable Version](https://poser.pugx.org/dg/twitter-php/v/stable)](https://github.com/dg/twitter-php/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/dg/twitter-php/blob/master/license.md)

Small and easy-to-use library for the [X](https://x.com) (formerly Twitter) API v2.

 <!---->


Installation
============

Install via Composer:

```shell
composer require dg/twitter-php
```

Requirements: PHP 8.2 or higher.

 <!---->


Getting API Keys
================

1. Go to [X Developer Portal](https://developer.x.com)
2. Sign in and create a Developer account (Free tier is sufficient)
3. Create a new Project and App

4. **Set up permissions** (important!):
   - App Settings → User authentication settings → Edit
   - App permissions: **Read and Write**
   - Type of App: Web App
   - Callback URI: `https://localhost/callback` (placeholder, not used)
   - Website URL: any valid URL

5. **Generate keys**:
   - Keys and tokens → API Key and Secret → Generate
   - Keys and tokens → Access Token and Secret → Generate

   ⚠️ **Note**: After changing permissions, you must regenerate the Access Token!

6. **Use case description** (required by X, min. 250 characters):
   ```
   I am building a personal tool for publishing my own content to X. The
   application will only be used to post text updates and images from my own
   account. It will not read, collect, or analyze any data from other users.
   There is no automation of likes, retweets, follows, or any other
   interactions. This is strictly a single-user tool for my personal use to
   streamline my social media posting workflow.
   ```

 <!---->


Usage
=====

Create the client using your API credentials:

```php
use DG\X\Client;

$x = new Client($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
```


**Note:** The Free tier of the X API only allows sending and deleting tweets and reading your own profile (`authenticate()`).
Reading timelines, searching, user info, followers and other read endpoints require the [Basic tier](https://developer.x.com/en/docs/x-api/getting-started/about-x-api) or higher.


### Sending Tweets

```php
$x->sendTweet('I am fine today.');
```

With an image:

```php
$x->sendTweet('Check this out!', '/path/to/image.jpg');
```

With multiple images:

```php
$x->sendTweet('Photos!', ['/path/to/img1.jpg', '/path/to/img2.jpg']);
```


### Timelines

Get your own tweets:

```php
$tweets = $x->getMyTweets();
```

Get your home timeline (you and people you follow):

```php
$tweets = $x->getTimeline();
```

Get your mentions:

```php
$tweets = $x->getMentions();
```


### Displaying Tweets

The static method `Client::clickable()` makes links, mentions and hashtags in tweets clickable:

```php
foreach ($tweets->data as $tweet) {
	echo Client::clickable($tweet);
}
```


### Searching

```php
$results = $x->search('#php');
```


### Users

```php
$user = $x->getUser('elonmusk');
$user = $x->getUserById('44196397');
$followers = $x->getFollowers('44196397');
```


### Social Actions

```php
$x->follow('44196397');
$x->sendDirectMessage('44196397', 'Hello!');
```


### Authentication

Test if user credentials are valid:

```php
if (!$x->authenticate()) {
	die('Invalid credentials');
}
```


### Custom API Requests

You can call any [X API v2](https://developer.x.com/en/docs/x-api) endpoint directly:

```php
$result = $x->request('tweets', 'POST', ['text' => 'Hello from raw API!']);
$result = $x->request('users/me', 'GET', ['user.fields' => 'description,profile_image_url']);
```


### Error Handling

All methods throw `DG\X\Exception` on error:

```php
try {
	$x->sendTweet('Hello!');
} catch (DG\X\Exception $e) {
	echo 'Error: ', $e->getMessage();
}
```

 <!---->


Backward Compatibility
======================

The old class names `DG\Twitter\Twitter`, `DG\Twitter\Exception`, `Twitter` and `TwitterException`
are available as aliases for `DG\X\Client` and `DG\X\Exception`.

 <!---->


[Support Me](https://github.com/sponsors/dg)
---------------------------------------------

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!

 <!---->


Changelog
---------

v5.0 (2/2026)
- new namespace `DG\X` with `Client` class
- uses X API v2 endpoints
- uses Guzzle HTTP client (replaces raw CURL)
- simplified inline OAuth 1.0a
- clean API: `sendTweet()`, `deleteTweet()`, `getTweet()`, `getMyTweets()`, `getTimeline()`, `getMentions()`, `search()`, `getUser()`, `getUserById()`, `getFollowers()`, `follow()`, `sendDirectMessage()`
- PHPStan level 8
- Nette Tester tests
- requires PHP 8.2+

v4.1 (11/2019)
- added Delete Method (#68)
- token is optional throughout + supply get() method

v4.0 (2/2019)
- requires PHP 7.1 and uses its advantages like typehints, strict types etc.
- class Twitter is now DG\Twitter\Twitter
- class TwitterException is now DG\Twitter\Exception

v3.8 (2/2019)
- Twitter::sendDirectMessage() uses new API
- Twitter::clickable: added support for $status->full_text (#60)

v3.7 (3/2018)
- minimal required PHP version changed to 5.4
- Twitter::send() added $options
- Twitter::clickable() now works only with statuses and entites
- fixed coding style

v3.6 (8/2016)
- added loadUserFollowersList() and sendDirectMessage()
- Twitter::send() allows to upload multiple images
- changed http:// to https://

v3.5 (12/2014)
- allows to send message starting with @ and upload file at the same time in PHP >= 5.5

v3.4 (11/2014)
- cache expiration can be specified as string
- fixed some bugs

v3.3 (3/2014)
- Twitter::send($status, $image) can upload image
- added Twitter::follow()

v3.2 (1/2014)
- Twitter API uses SSL OAuth
- Twitter::clickable() supports media
- added Twitter::loadUserInfoById() and loadUserFollowers()
- fixed Twitter::destroy()

v3.1 (3/2013)
- Twitter::load() - added third argument $data
- Twitter::clickable() uses entities; pass as parameter status object, not just text
- added Twitter::$httpOptions for custom cURL configuration

v3.0 (12/2012)
- updated to Twitter API 1.1

v2.0 (8/2012)
- added support for OAuth authentication protocol
- added Twitter::clickable() which makes links, @usernames and #hashtags clickable
- installable via `composer require dg/twitter-php`

v1.0 (7/2008)
- initial release


-----
(c) David Grudl, 2008, 2026 (https://davidgrudl.com)
