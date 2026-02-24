<?php declare(strict_types=1);

use DG\X\Client;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$credentials = getTestCredentials();
if (!$credentials) {
	Tester\Environment::skip('Set X_CONSUMER_KEY, X_CONSUMER_SECRET, X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET to run integration tests');
}


test('getMyTweets returns response', function () use ($credentials) {
	$client = new Client(
		$credentials['consumerKey'],
		$credentials['consumerSecret'],
		$credentials['accessToken'],
		$credentials['accessTokenSecret'],
	);

	$result = $client->getMyTweets(5);
	Assert::type('object', $result);
});
