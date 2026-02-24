<?php declare(strict_types=1);

use DG\X\Client;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$credentials = getTestCredentials();
if (!$credentials) {
	Tester\Environment::skip('Set X_CONSUMER_KEY, X_CONSUMER_SECRET, X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET to run integration tests');
}


test('getUser returns user data', function () use ($credentials) {
	$client = new Client(
		$credentials['consumerKey'],
		$credentials['consumerSecret'],
		$credentials['accessToken'],
		$credentials['accessTokenSecret'],
	);

	$result = $client->getUser('x');
	Assert::type('object', $result);
	Assert::type('object', $result->data);
	Assert::type('string', $result->data->id);
	Assert::type('string', $result->data->username);
});


test('getUserById returns user data', function () use ($credentials) {
	$client = new Client(
		$credentials['consumerKey'],
		$credentials['consumerSecret'],
		$credentials['accessToken'],
		$credentials['accessTokenSecret'],
	);

	// Get user by username first, then by ID
	$user = $client->getUser('x');
	$result = $client->getUserById($user->data->id);
	Assert::type('object', $result);
	Assert::same($user->data->id, $result->data->id);
});
