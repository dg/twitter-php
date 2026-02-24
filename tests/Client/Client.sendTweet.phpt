<?php declare(strict_types=1);

use DG\X\Client;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$credentials = getTestCredentials();
if (!$credentials) {
	Tester\Environment::skip('Set X_CONSUMER_KEY, X_CONSUMER_SECRET, X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET to run integration tests');
}


test('send and delete tweet', function () use ($credentials) {
	$client = new Client(
		$credentials['consumerKey'],
		$credentials['consumerSecret'],
		$credentials['accessToken'],
		$credentials['accessTokenSecret'],
	);

	$message = 'Test tweet from dg/twitter-php ' . date('Y-m-d H:i:s') . ' #test';
	$result = $client->sendTweet($message);

	Assert::type('object', $result);
	Assert::type('object', $result->data);
	Assert::type('string', $result->data->id);

	// Clean up: delete the test tweet
	$deleted = $client->deleteTweet($result->data->id);
	Assert::true($deleted);
});
