<?php declare(strict_types=1);

use DG\X\Client;

require_once __DIR__ . '/../vendor/autoload.php';

// ENTER HERE YOUR CREDENTIALS (see readme.md)
$x = new Client($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

try {
	$tweet = $x->sendTweet('I am fine'); // you can add $imagePath or array of image paths as second argument

} catch (DG\X\Exception $e) {
	echo 'Error: ' . $e->getMessage();
}
