Twitter for PHP (c) David Grudl, 2008 (http://davidgrudl.com)


Introduction
------------

This is very small and easy library for sending messages to Twitter
and receiving statuses.

Twitter's API documentation: http://groups.google.com/group/twitter-development-talk/web/api-documentation
My PHP blog: http://phpfashion.com


Requirements
------------
- PHP (version 5 or better)
- cURL extension


Usage
-----

Create object using your credentials (user name and password)

    $twitter = new Twitter($userName, $password);

Method send() updates your status. The message must be encoded in UTF-8:

    $twitter->send('I am fine today.');

Method load() returns the 20 most recent statuses posted in the
last 24 hours from you and your friends (optionally):

    $withFriends = FALSE;
    $channel = $twitter->load($withFriends);

Returned channel is a SimpleXMLElement object. Extracting the 
informations from channel is easy:

	echo "Title: ", $channel->title;

	foreach ($channel->item as $item) {
	    echo "Message: ", $item->description;
	    echo "posted at " , $item->pubDate;
    }


Files
-----
readme.txt        - This file.
license.txt       - The license for this software (New BSD License).
twitter.class.php - The core Twitter class source.
send.php          - Example sending message to Twitter.
load.php          - Example loading statuses from Twitter.
