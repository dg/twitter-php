<?php

require_once '../src/twitter.class.php';

// ENTER HERE YOUR CREDENTIALS (see readme.txt)
$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

try {
	$tweet = $twitter->send('I am fine'); // you can add $imagePath or array of image paths as second argument

} catch (TwitterException $e) {
	echo 'Error: ' . $e->getMessage();
}
