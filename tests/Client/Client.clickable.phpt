<?php declare(strict_types=1);

use DG\X\Client;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('tweet without entities', function () {
	$tweet = (object) ['text' => 'Hello world'];
	Assert::same('Hello world', Client::clickable($tweet));
});


test('escapes HTML in plain text', function () {
	$tweet = (object) ['text' => '<script>alert("xss")</script>'];
	Assert::same('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', Client::clickable($tweet));
});


test('makes hashtags clickable (v2 format)', function () {
	$tweet = (object) [
		'text' => 'Hello #php world',
		'entities' => (object) [
			'hashtags' => [
				(object) ['start' => 6, 'end' => 10, 'tag' => 'php'],
			],
		],
	];
	Assert::same(
		'Hello <a href="https://x.com/search?q=%23php">#php</a> world',
		Client::clickable($tweet),
	);
});


test('makes URLs clickable', function () {
	$tweet = (object) [
		'text' => 'Visit https://t.co/abc for more',
		'entities' => (object) [
			'urls' => [
				(object) [
					'start' => 6,
					'end' => 26,
					'url' => 'https://t.co/abc',
					'expanded_url' => 'https://example.com',
					'display_url' => 'example.com',
				],
			],
		],
	];

	$result = Client::clickable($tweet);
	Assert::contains('href="https://example.com"', $result);
	Assert::contains('>example.com</a>', $result);
});


test('makes mentions clickable (v2 format)', function () {
	$tweet = (object) [
		'text' => 'Hello @david!',
		'entities' => (object) [
			'mentions' => [
				(object) ['start' => 6, 'end' => 12, 'username' => 'david'],
			],
		],
	];
	Assert::same(
		'Hello <a href="https://x.com/david">@david</a>!',
		Client::clickable($tweet),
	);
});


test('handles multiple entities', function () {
	$tweet = (object) [
		'text' => '@alice check #php at https://t.co/x',
		'entities' => (object) [
			'mentions' => [
				(object) ['start' => 0, 'end' => 6, 'username' => 'alice'],
			],
			'hashtags' => [
				(object) ['start' => 13, 'end' => 17, 'tag' => 'php'],
			],
			'urls' => [
				(object) [
					'start' => 21,
					'end' => 35,
					'url' => 'https://t.co/x',
					'expanded_url' => 'https://example.com',
					'display_url' => 'example.com',
				],
			],
		],
	];

	$result = Client::clickable($tweet);
	Assert::contains('@alice</a>', $result);
	Assert::contains('#php</a>', $result);
	Assert::contains('example.com</a>', $result);
});


test('handles unicode text', function () {
	$tweet = (object) [
		'text' => 'Ahoj #svět!',
		'entities' => (object) [
			'hashtags' => [
				(object) ['start' => 5, 'end' => 10, 'tag' => 'svět'],
			],
		],
	];
	Assert::same(
		'Ahoj <a href="https://x.com/search?q=%23svět">#svět</a>!',
		Client::clickable($tweet),
	);
});
