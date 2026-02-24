<?php declare(strict_types=1);

use DG\X\Client;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('buildOAuthHeader produces valid OAuth header', function () {
	$client = new Client('consumerKey', 'consumerSecret', 'accessToken', 'accessTokenSecret');

	$method = new ReflectionMethod($client, 'buildOAuthHeader');
	$header = $method->invoke($client, 'GET', 'https://api.twitter.com/2/tweets');

	Assert::match('~^OAuth .+$~', $header);
	Assert::contains('oauth_consumer_key="consumerKey"', $header);
	Assert::contains('oauth_token="accessToken"', $header);
	Assert::contains('oauth_signature_method="HMAC-SHA1"', $header);
	Assert::contains('oauth_version="1.0"', $header);
	Assert::contains('oauth_signature=', $header);
	Assert::contains('oauth_nonce=', $header);
	Assert::contains('oauth_timestamp=', $header);
});


test('buildOAuthHeader includes body params in signature', function () {
	$client = new Client('key', 'secret', 'token', 'tokenSecret');

	$method = new ReflectionMethod($client, 'buildOAuthHeader');

	$header1 = $method->invoke($client, 'POST', 'https://api.twitter.com/2/tweets', []);
	$header2 = $method->invoke($client, 'POST', 'https://api.twitter.com/2/tweets', ['status' => 'hello']);

	// Different body params should produce different signatures
	preg_match('/oauth_signature="([^"]+)"/', $header1, $m1);
	preg_match('/oauth_signature="([^"]+)"/', $header2, $m2);
	Assert::notSame($m1[1], $m2[1]);
});


test('buildOAuthHeader works without access token', function () {
	$client = new Client('consumerKey', 'consumerSecret');

	$method = new ReflectionMethod($client, 'buildOAuthHeader');
	$header = $method->invoke($client, 'GET', 'https://api.twitter.com/2/tweets');

	Assert::contains('oauth_token=""', $header);
});
