[Twitter for PHP](https://phpfashion.com/twitter-for-php)  [![Buy me a coffee](https://files.nette.org/images/coffee1s.png)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=QLFKBFDU6C94L)
================================

[![Downloads this Month](https://img.shields.io/packagist/dm/dg/twitter-php.svg)](https://packagist.org/packages/dg/twitter-php)

Twitter for PHP is a very small and easy-to-use library for sending
messages to Twitter and receiving status updates.

It requires PHP 5.4 or newer with CURL extension and is licensed under the New BSD License.
You can obtain the latest version from our [GitHub repository](https://github.com/dg/twitter-php)
or install it via Composer:

	composer require dg/twitter-php


Usage
-----
Sign in to the https://twitter.com and register an application from the https://apps.twitter.com page. Remember
to never reveal your consumer secrets. Click on My Access Token link from the sidebar and retrieve your own access
token. Now you have consumer key, consumer secret, access token and access token secret.

Create object using application and request/access keys

```php
$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
```

The send() method updates your status. The message must be encoded in UTF-8:

```php
$twitter->send('I am fine today.');
```

The load() method returns the 20 most recent status updates
posted in the last 24 hours by you:

```php
$statuses = $twitter->load(Twitter::ME);
```

or posted by you and your friends:

```php
$statuses = $twitter->load(Twitter::ME_AND_FRIENDS);
```
or most recent mentions for you:

```php
$statuses = $twitter->load(Twitter::REPLIES);
```
Extracting the information from the channel is easy:

```php
foreach ($statuses as $status) {
	echo "message: ", Twitter::clickable($status);
	echo "posted at " , $status->created_at;
	echo "posted by " , $status->user->name;
}
```

The static method `Twitter::clickable()` makes links, mentions and hash tags in status clickable.

The authenticate() method tests if user credentials are valid:

```php
if (!$twitter->authenticate()) {
	die('Invalid name or password');
}
```

The search() method provides searching in twitter statuses:

```php
$results = $twitter->search('#nette');
```

The returned result is a again array of statuses.


Error handling
--------------

All methods throw a TwitterException on error:

```php
try {
	$statuses = $twitter->load(Twitter::ME);
} catch (TwitterException $e) {
	echo "Error: ", $e->getMessage();
}
```

Additional features
-------------------

The `authenticate()` method tests if user credentials are valid:

```php
if (!$twitter->authenticate()) {
	die('Invalid name or password');
}
```

Other commands
--------------

You can use all commands defined by [Twitter API 1.1](https://dev.twitter.com/rest/public).
For example [GET statuses/retweets_of_me](https://dev.twitter.com/rest/reference/get/statuses/retweets_of_me)
returns the array of most recent tweets authored by the authenticating user:

```php
$statuses = $twitter->request('statuses/retweets_of_me', 'GET', ['count' => 20]);
```

Changelog
---------
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
- updated to Twitter API 1.1. Some stuff deprecated by Twitter was removed:
	- removed RSS, ATOM and XML support
	- removed Twitter::ALL
	- Twitter::load() - removed third argument $page
	- Twitter::search() requires authentication and returns different structure
- removed shortening URL using http://is.gd
- changed order of Twitter::request() arguments to $resource, $method, $data

v2.0 (8/2012)
- added support for OAuth authentication protocol
- added Twitter::clickable() which makes links, @usernames and #hashtags clickable
- installable via `composer require dg/twitter-php`

v1.0 (7/2008)
- initial release


-----
(c) David Grudl, 2008, 2016 (https://davidgrudl.com)
