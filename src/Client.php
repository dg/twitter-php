<?php declare(strict_types=1);

/**
 * X for PHP - library for sending messages to X (formerly Twitter) and receiving status updates.
 *
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 * This software is licensed under the New BSD License.
 *
 * Homepage:    https://github.com/dg/twitter-php
 * X API:       https://developer.x.com/en/docs
 * Version:     5.0
 */

namespace DG\X;

use GuzzleHttp;
use stdClass;
use function is_string;


/**
 * X (formerly Twitter) API v2 client.
 */
class Client
{
	private const ApiUrl = 'https://api.twitter.com/2/';
	private const UploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';

	public static string $cacheExpire = '30 minutes';
	public static ?string $cacheDir = null;

	/** Guzzle client options */
	public array $httpOptions = [
		'timeout' => 20,
	];

	private GuzzleHttp\Client $http;
	private string $consumerKey;
	private string $consumerSecret;
	private ?string $accessToken;
	private ?string $accessTokenSecret;

	/** Cached authenticated user ID */
	private ?string $userId = null;


	/**
	 * Creates client using consumer and access keys.
	 */
	public function __construct(
		string $consumerKey,
		string $consumerSecret,
		?string $accessToken = null,
		?string $accessTokenSecret = null,
	) {
		$this->consumerKey = $consumerKey;
		$this->consumerSecret = $consumerSecret;
		$this->accessToken = $accessToken;
		$this->accessTokenSecret = $accessTokenSecret;
		$this->http = new GuzzleHttp\Client($this->httpOptions);
	}


	/**
	 * Tests if user credentials are valid.
	 * @throws Exception
	 */
	public function authenticate(): bool
	{
		try {
			$res = $this->request('users/me', 'GET');
			return !empty($res->data->id);
		} catch (Exception $e) {
			if ($e->getCode() === 401) {
				return false;
			}
			throw $e;
		}
	}


	/**
	 * Sends a tweet.
	 * @param  string|string[]|null  $media  path(s) to local media file(s) to be uploaded
	 * @throws Exception
	 */
	public function sendTweet(string $text, string|array|null $media = null, array $options = []): stdClass
	{
		$mediaIds = [];
		foreach ((array) $media as $path) {
			$mediaIds[] = $this->uploadMedia($path);
		}

		$payload = $options + ['text' => $text];
		if ($mediaIds) {
			$payload['media'] = ['media_ids' => $mediaIds];
		}

		return $this->request('tweets', 'POST', $payload);
	}


	/**
	 * Deletes a tweet.
	 * @throws Exception
	 */
	public function deleteTweet(string $id): bool
	{
		$res = $this->request("tweets/$id", 'DELETE');
		return $res->data->deleted ?? false;
	}


	/**
	 * Retrieves a single tweet.
	 * @param  string[]  $fields  additional tweet fields to include
	 * @throws Exception
	 */
	public function getTweet(string $id, array $fields = []): stdClass
	{
		$data = [];
		if ($fields) {
			$data['tweet.fields'] = implode(',', $fields);
		}
		return $this->request("tweets/$id", 'GET', $data);
	}


	/**
	 * Returns the authenticated user's tweets.
	 * @return stdClass[]
	 * @throws Exception
	 */
	public function getMyTweets(int $count = 20, array $options = []): array
	{
		$userId = $this->getAuthenticatedUserId();
		return $this->cachedRequest("users/$userId/tweets", $options + [
			'max_results' => min($count, 100),
		]);
	}


	/**
	 * Returns the authenticated user's home timeline (reverse chronological).
	 * @return stdClass[]
	 * @throws Exception
	 */
	public function getTimeline(int $count = 20, array $options = []): array
	{
		$userId = $this->getAuthenticatedUserId();
		return $this->cachedRequest("users/$userId/reverse_chronological", $options + [
			'max_results' => min($count, 100),
		]);
	}


	/**
	 * Returns the most recent mentions for the authenticated user.
	 * @return stdClass[]
	 * @throws Exception
	 */
	public function getMentions(int $count = 20, array $options = []): array
	{
		$userId = $this->getAuthenticatedUserId();
		return $this->cachedRequest("users/$userId/mentions", $options + [
			'max_results' => min($count, 100),
		]);
	}


	/**
	 * Searches for recent tweets matching the query.
	 * @return stdClass[]
	 * @throws Exception
	 */
	public function search(string $query, array $options = []): array
	{
		return $this->cachedRequest('tweets/search/recent', $options + ['query' => $query]);
	}


	/**
	 * Returns information about a user by username.
	 * @param  string[]  $fields  additional user fields to include
	 * @throws Exception
	 */
	public function getUser(string $username, array $fields = []): stdClass
	{
		$data = [];
		if ($fields) {
			$data['user.fields'] = implode(',', $fields);
		}
		return $this->cachedRequest("users/by/username/$username", $data);
	}


	/**
	 * Returns information about a user by ID.
	 * @param  string[]  $fields  additional user fields to include
	 * @throws Exception
	 */
	public function getUserById(string $id, array $fields = []): stdClass
	{
		$data = [];
		if ($fields) {
			$data['user.fields'] = implode(',', $fields);
		}
		return $this->cachedRequest("users/$id", $data);
	}


	/**
	 * Returns followers of a user.
	 * @throws Exception
	 */
	public function getFollowers(string $userId, array $options = []): stdClass
	{
		return $this->cachedRequest("users/$userId/followers", $options);
	}


	/**
	 * Follows a user. Requires the target user's ID.
	 * @throws Exception
	 */
	public function follow(string $targetUserId): stdClass
	{
		$userId = $this->getAuthenticatedUserId();
		return $this->request("users/$userId/following", 'POST', [
			'target_user_id' => $targetUserId,
		]);
	}


	/**
	 * Sends a direct message to a user by their ID.
	 * @throws Exception
	 */
	public function sendDirectMessage(string $participantId, string $text): stdClass
	{
		return $this->request(
			"dm_conversations/with/$participantId/messages",
			'POST',
			['text' => $text],
		);
	}


	/**
	 * Processes an HTTP request to the X API.
	 * @param  string  $method  GET|POST|DELETE
	 * @throws Exception
	 */
	public function request(string $resource, string $method = 'GET', array $data = [], array $files = []): mixed
	{
		if (!str_contains($resource, '://')) {
			$resource = self::ApiUrl . $resource;
		}

		$data = array_filter($data, fn($val) => $val !== null);

		$options = [
			'headers' => [
				'Authorization' => $this->buildOAuthHeader($method, $resource, $method === 'GET' ? $data : []),
			],
		];

		if ($method === 'GET' || $method === 'DELETE') {
			if ($data) {
				$options['query'] = $data;
			}

		} elseif ($files) {
			$multipart = [];
			foreach ($data as $key => $val) {
				$multipart[] = ['name' => $key, 'contents' => (string) $val];
			}
			foreach ($files as $key => $file) {
				if (!is_file($file)) {
					throw new Exception("Cannot read the file $file. Check if file exists on disk and check its permissions.");
				}
				$multipart[] = ['name' => $key, 'contents' => fopen($file, 'r'), 'filename' => basename($file)];
			}
			$options['multipart'] = $multipart;
			unset($options['headers']['Content-Type']); // let Guzzle set multipart boundary

		} else {
			$options['json'] = $data;
		}

		try {
			$response = $this->http->request($method, $resource, $options);
		} catch (GuzzleHttp\Exception\GuzzleException $e) {
			throw new Exception($this->extractErrorMessage($e), $this->extractStatusCode($e));
		}

		$code = $response->getStatusCode();
		if ($code === 204) {
			return true;
		}

		$body = $response->getBody()->getContents();
		$payload = @json_decode($body, false, 128, JSON_BIGINT_AS_STRING); // @ - may fail for non-JSON
		if ($payload === null && $body !== '') {
			throw new Exception('Invalid server response');
		}

		return $payload;
	}


	/**
	 * Cached HTTP GET request.
	 */
	public function cachedRequest(string $resource, array $data = [], string|int|null $cacheExpire = null): mixed
	{
		if (!self::$cacheDir) {
			return $this->request($resource, 'GET', $data);
		}

		$cacheExpire ??= self::$cacheExpire;

		$cacheFile = self::$cacheDir
			. '/twitter.'
			. md5($resource . json_encode($data) . $this->consumerKey . $this->accessToken)
			. '.json';

		$cache = @json_decode((string) @file_get_contents($cacheFile)); // @ - file may not exist
		$expiration = is_string($cacheExpire)
			? strtotime($cacheExpire) - time()
			: $cacheExpire;
		if ($cache && @filemtime($cacheFile) + $expiration > time()) { // @ - file may not exist
			return $cache;
		}

		try {
			$payload = $this->request($resource, 'GET', $data);
			file_put_contents($cacheFile, json_encode($payload));
			return $payload;
		} catch (Exception $e) {
			if ($cache) {
				return $cache;
			}
			throw $e;
		}
	}


	/**
	 * Makes links, @usernames and #hashtags clickable in a tweet.
	 */
	public static function clickable(stdClass $tweet): string
	{
		$text = $tweet->text ?? '';
		$entities = $tweet->entities ?? null;
		if (!$entities) {
			return htmlspecialchars($text);
		}

		$all = [];
		foreach ($entities->hashtags ?? [] as $item) {
			$start = $item->start ?? $item->indices[0] ?? null;
			$end = $item->end ?? $item->indices[1] ?? null;
			if ($start !== null && $end !== null) {
				$tag = $item->tag ?? $item->text ?? '';
				$all[$start] = ["https://x.com/search?q=%23$tag", "#$tag", $end];
			}
		}

		foreach ($entities->urls ?? [] as $item) {
			$start = $item->start ?? $item->indices[0] ?? null;
			$end = $item->end ?? $item->indices[1] ?? null;
			if ($start !== null && $end !== null) {
				$all[$start] = [
					$item->expanded_url ?? $item->url,
					$item->display_url ?? $item->url ?? $item->expanded_url,
					$end,
				];
			}
		}

		foreach ($entities->mentions ?? $entities->user_mentions ?? [] as $item) {
			$start = $item->start ?? $item->indices[0] ?? null;
			$end = $item->end ?? $item->indices[1] ?? null;
			if ($start !== null && $end !== null) {
				$username = $item->username ?? $item->screen_name ?? '';
				$all[$start] = ["https://x.com/$username", "@$username", $end];
			}
		}

		krsort($all);
		foreach ($all as $pos => $item) {
			$text = iconv_substr($text, 0, $pos, 'UTF-8')
				. '<a href="' . htmlspecialchars($item[0]) . '">' . htmlspecialchars($item[1]) . '</a>'
				. iconv_substr($text, $item[2], iconv_strlen($text, 'UTF-8'), 'UTF-8');
		}

		return $text;
	}


	/**
	 * Uploads media file and returns the media ID string.
	 */
	private function uploadMedia(string $path): string
	{
		if (!is_file($path)) {
			throw new Exception("Cannot read the file $path. Check if file exists on disk and check its permissions.");
		}

		$response = $this->http->post(self::UploadUrl, [
			'headers' => [
				'Authorization' => $this->buildOAuthHeader('POST', self::UploadUrl, [
					'media_data' => base64_encode(file_get_contents($path)),
				]),
			],
			'form_params' => [
				'media_data' => base64_encode(file_get_contents($path)),
			],
		]);

		$data = json_decode($response->getBody()->getContents(), true);
		return $data['media_id_string'] ?? throw new Exception('No media_id in upload response');
	}


	/**
	 * Returns the authenticated user's ID, caching the result.
	 */
	private function getAuthenticatedUserId(): string
	{
		if ($this->userId === null) {
			$res = $this->request('users/me', 'GET');
			$this->userId = $res->data->id ?? throw new Exception('Cannot determine authenticated user ID');
		}

		return $this->userId;
	}


	/**
	 * Builds OAuth 1.0a Authorization header for the request.
	 */
	private function buildOAuthHeader(string $method, string $url, array $bodyParams = []): string
	{
		$oauth = [
			'oauth_consumer_key' => $this->consumerKey,
			'oauth_nonce' => bin2hex(random_bytes(16)),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => (string) time(),
			'oauth_token' => $this->accessToken ?? '',
			'oauth_version' => '1.0',
		];

		$params = array_merge($oauth, $bodyParams);
		ksort($params);

		$paramString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
		$baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);

		$signingKey = rawurlencode($this->consumerSecret)
			. '&'
			. rawurlencode($this->accessTokenSecret ?? '');

		$oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

		$parts = [];
		foreach ($oauth as $key => $value) {
			$parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
		}

		return 'OAuth ' . implode(', ', $parts);
	}


	/**
	 * Extracts a human-readable error message from a Guzzle exception.
	 */
	private function extractErrorMessage(GuzzleHttp\Exception\GuzzleException $e): string
	{
		if (!$e instanceof GuzzleHttp\Exception\RequestException || !$e->getResponse()) {
			return $e->getMessage();
		}

		$body = $e->getResponse()->getBody()->getContents();
		$data = @json_decode($body, true); // @ - may not be JSON

		return $data['detail']
			?? $data['errors'][0]['message']
			?? $data['error']
			?? $e->getMessage();
	}


	/**
	 * Extracts HTTP status code from a Guzzle exception.
	 */
	private function extractStatusCode(GuzzleHttp\Exception\GuzzleException $e): int
	{
		return $e instanceof GuzzleHttp\Exception\RequestException && $e->getResponse()
			? $e->getResponse()->getStatusCode()
			: 0;
	}
}


// backward compatibility
class_alias(Client::class, 'DG\Twitter\Twitter');
class_alias(Exception::class, 'DG\Twitter\Exception');
class_alias(Client::class, 'Twitter');
class_alias(Exception::class, 'TwitterException');
