<?php

require_once '../src/twitter.class.php';

// ENTER HERE YOUR CREDENTIALS (see readme.txt)
$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

try {
	$tweet = $twitter->send('I am fine3');

} catch (TwitterException $e) {
	echo 'Error: ' . $e->getMessage();
}
