Twitter for PHP (c) David Grudl, 2008 (http://davidgrudl.com)


Introduction
------------

Twitter for PHP is a very small and easy-to-use library for sending
messages to Twitter and receiving status updates.


Project at GitHub: http://github.com/dg/twitter-php
Twitter's API documentation: http://apiwiki.twitter.com/Twitter-API-Documentation
My PHP blog: http://phpfashion.com


Requirements
------------
- PHP (version 5 or better)
- cURL extension


Usage
-----

Create object using your credentials (user name and password)

	$twitter = new Twitter($userName, $password);

The send() method updates your status. The message must be encoded in UTF-8:

	$twitter->send('I am fine today.');

The load() method returns the 20 most recent status updates
posted in the last 24 hours by you:

	$channel = $twitter->load(Twitter::ME);

or posted by you and your friends:

	$channel = $twitter->load(Twitter::ME_AND_FRIENDS);

or most recent mentions for you:

	$channel = $twitter->load(Twitter::REPLIES);

The returned channel is a SimpleXMLElement object. Extracting
the information from the channel is easy:

	foreach ($channel->status as $status) {
		echo "message: ", $status->text;
		echo "posted at " , $status->created_at;
		echo "posted by " , $status->user->name;
	}

The authenticate() method tests if user credentials are valid:

	if (!$twitter->authenticate()) {
		die('Invalid name or password');
	}

The search() method provides searching in twitter statuses:

	$results = $twitter->search('#nette');

The returned result is a PHP array:

	foreach ($results as $result) {
		echo "message: ", $result->text;
		echo "posted at " , $result->created_at;
		echo "posted by " , $result->form_user;
	}




Files
-----
readme.txt          - This file.
license.txt         - The license for this software (New BSD License).
twitter.class.php   - The core Twitter class source.
examples/send.php   - Example sending message to Twitter.
examples/load.php   - Example loading statuses from Twitter.
examples/search.php - Example searching on Twitter.
