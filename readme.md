[Twitter for PHP](http://phpfashion.com/twitter-for-php)
================================

Twitter for PHP is a very small and easy-to-use library for sending
messages to Twitter and receiving status updates.

It requires PHP 5.0 or newer with CURL extension and is licensed under the New BSD License.
You can obtain the latest version from our [GitHub repository](http://github.com/dg/twitter-php)
or install it via Composer:

	php composer.phar require dg/twitter-php


Usage
-----
Sign in to the http://twitter.com and register an application from the http://dev.twitter.com/apps page. Remember
to never reveal your consumer secrets. Click on My Access Token link from the sidebar and retrieve your own access
token. Now you have consumer key, consumer secret, access token and access token secret.

Create object using application and request/access keys

	$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

The send() method updates your status. The message must be encoded in UTF-8:

	$twitter->send('I am fine today.');

The load() method returns the 20 most recent status updates
posted in the last 24 hours by you:

	$statuses = $twitter->load(Twitter::ME);

or posted by you and your friends:

	$statuses = $twitter->load(Twitter::ME_AND_FRIENDS);

or most recent mentions for you:

	$statuses = $twitter->load(Twitter::REPLIES);

Extracting the information from the channel is easy:

	foreach ($statuses as $status) {
		echo "message: ", Twitter::clickable($status);
		echo "posted at " , $status->created_at;
		echo "posted by " , $status->user->name;
	}

The static method `Twitter::clickable()` makes links, mentions and hash tags in status clickable.

The authenticate() method tests if user credentials are valid:

	if (!$twitter->authenticate()) {
		die('Invalid name or password');
	}

The search() method provides searching in twitter statuses:

	$results = $twitter->search('#nette');

The returned result is a again array of statuses.


Error handling
--------------

All methods throw a TwitterException on error:

	try {
		$statuses = $twitter->load(Twitter::ME);
	} catch (TwitterException $e) {
		echo "Error: ", $e->getMessage();
	}


Additional features
-------------------

The `authenticate()` method tests if user credentials are valid:

	if (!$twitter->authenticate()) {
		die('Invalid name or password');
	}


Other commands
--------------

You can use all commands defined by [Twitter API 1.1](https://dev.twitter.com/docs/api/1.1).
For example [GET statuses/retweets_of_me](https://dev.twitter.com/docs/api/1.1/get/statuses/retweets_of_me)
returns the array of most recent tweets authored by the authenticating user:

	$statuses = $twitter->request('statuses/retweets_of_me', 'GET', array('count' => 20));


Changelog
---------
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
(c) David Grudl, 2008, 2014 (http://davidgrudl.com)
