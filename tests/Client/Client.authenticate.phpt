<?php declare(strict_types=1);

use DG\X\Client;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$credentials = getTestCredentials();
if (!$credentials) {
	Tester\Environment::skip('Set X_CONSUMER_KEY, X_CONSUMER_SECRET, X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET to run integration tests');
}


test('authenticate with valid credentials', function () use ($credentials) {
	$client = new Client(
		$credentials['consumerKey'],
		$credentials['consumerSecret'],
		$credentials['accessToken'],
		$credentials['accessTokenSecret'],
	);

	Assert::true($client->authenticate());
});


test('authenticate with invalid credentials returns false', function () {
	$client = new Client('invalid', 'invalid', 'invalid', 'invalid');
	Assert::false($client->authenticate());
});
