<?php

declare(strict_types=1);

/**
 * Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 * This software is licensed under the New BSD License.
 *
 * Homepage:    https://phpfashion.com/twitter-for-php
 * Twitter API: https://dev.twitter.com/rest/public
 * Version:     4.1
 */

namespace DG\Twitter;

use stdClass;


/**
 * Twitter API.
 */
class Twitter
{
	public const ME = 1;
	public const ME_AND_FRIENDS = 2;
	public const REPLIES = 3;
	public const RETWEETS = 128; // include retweets?

	private const API_URL = 'https://api.twitter.com/1.1/';

	/** @var int */
	public static $cacheExpire = '30 minutes';

	/** @var string */
	public static $cacheDir;

	/** @var array */
	public $httpOptions = [
		CURLOPT_TIMEOUT => 20,
		CURLOPT_SSL_VERIFYPEER => 0,
		CURLOPT_USERAGENT => 'Twitter for PHP',
	];

	/** @var OAuth\Consumer */
	private $consumer;

	/** @var OAuth\Token */
	private $token;


	/**
	 * Creates object using consumer and access keys.
	 * @throws Exception when CURL extension is not loaded
	 */
	public function __construct(string $consumerKey, string $consumerSecret, string $accessToken = null, string $accessTokenSecret = null)
	{
		if (!extension_loaded('curl')) {
			throw new Exception('PHP extension CURL is not loaded.');
		}

		$this->consumer = new OAuth\Consumer($consumerKey, $consumerSecret);
		if ($accessToken && $accessTokenSecret) {
			$this->token = new OAuth\Token($accessToken, $accessTokenSecret);
		}
	}


	/**
	 * Tests if user credentials are valid.
	 * @throws Exception
	 */
	public function authenticate(): bool
	{
		try {
			$res = $this->request('account/verify_credentials', 'GET');
			return !empty($res->id);

		} catch (Exception $e) {
			if ($e->getCode() === 401) {
				return false;
			}
			throw $e;
		}
	}


	/**
	 * Sends message to the Twitter.
	 * https://dev.twitter.com/rest/reference/post/statuses/update
	 * @param  string|array  $mediaPath  path to local media file to be uploaded
	 * @throws Exception
	 */
	public function send(string $message, $mediaPath = null, array $options = []): stdClass
	{
		$mediaIds = [];
		foreach ((array) $mediaPath as $item) {
			$res = $this->request(
				'https://upload.twitter.com/1.1/media/upload.json',
				'POST',
				[],
				['media' => $item]
			);
			$mediaIds[] = $res->media_id_string;
		}
		return $this->request(
			'statuses/update',
			'POST',
			$options + ['status' => $message, 'media_ids' => implode(',', $mediaIds) ?: null]
		);
	}


	/**
	 * Sends a direct message to the specified user.
	 * https://dev.twitter.com/rest/reference/post/direct_messages/new
	 * @throws Exception
	 */
	public function sendDirectMessage(string $username, string $message): stdClass
	{
		return $this->request(
			'direct_messages/events/new',
			'JSONPOST',
			['event' => [
				'type' => 'message_create',
				'message_create' => [
					'target' => ['recipient_id' => $this->loadUserInfo($username)->id_str],
					'message_data' => ['text' => $message],
				],
			]]
		);
	}


	/**
	 * Follows a user on Twitter.
	 * https://dev.twitter.com/rest/reference/post/friendships/create
	 * @throws Exception
	 */
	public function follow(string $username): stdClass
	{
		return $this->request('friendships/create', 'POST', ['screen_name' => $username]);
	}


	/**
	 * Returns the most recent statuses.
	 * https://dev.twitter.com/rest/reference/get/statuses/user_timeline
	 * @param  int  $flags  timeline (ME | ME_AND_FRIENDS | REPLIES) and optional (RETWEETS)
	 * @param  bool clickable return clickable text?
	 * @return stdClass[]
	 * @throws Exception
	 */
	public function load(int $flags = self::ME, int $count = 20, array $data = null, $clickable = false): array
	{
		static $timelines = [
			self::ME => 'user_timeline',
			self::ME_AND_FRIENDS => 'home_timeline',
			self::REPLIES => 'mentions_timeline',
		];
		if (!isset($timelines[$flags & 3])) {
			throw new \InvalidArgumentException;
		}

		return $this->formatTweets(
		    $this->cachedRequest('statuses/' . $timelines[$flags & 3], (array) $data + [
			    'count' => $count,
			    'include_rts' => $flags & self::RETWEETS ? 1 : 0,
		    ]),
		    $clickable);
	}


	/**
	 * Returns information of a given user.
	 * https://dev.twitter.com/rest/reference/get/users/show
	 * @throws Exception
	 */
	public function loadUserInfo(string $username): stdClass
	{
		return $this->cachedRequest('users/show', ['screen_name' => $username]);
	}


	/**
	 * Returns information of a given user by id.
	 * https://dev.twitter.com/rest/reference/get/users/show
	 * @throws Exception
	 */
	public function loadUserInfoById(string $id): stdClass
	{
		return $this->cachedRequest('users/show', ['user_id' => $id]);
	}


	/**
	 * Returns IDs of followers of a given user.
	 * https://dev.twitter.com/rest/reference/get/followers/ids
	 * @throws Exception
	 */
	public function loadUserFollowers(string $username, int $count = 5000, int $cursor = -1, $cacheExpiry = null): stdClass
	{
		return $this->cachedRequest('followers/ids', [
			'screen_name' => $username,
			'count' => $count,
			'cursor' => $cursor,
		], $cacheExpiry);
	}


	/**
	 * Returns list of followers of a given user.
	 * https://dev.twitter.com/rest/reference/get/followers/list
	 * @throws Exception
	 */
	public function loadUserFollowersList(string $username, int $count = 200, int $cursor = -1, $cacheExpiry = null): stdClass
	{
		return $this->cachedRequest('followers/list', [
			'screen_name' => $username,
			'count' => $count,
			'cursor' => $cursor,
		], $cacheExpiry);
	}


	/**
	 * Destroys status.
	 * @param  int|string  $id  status to be destroyed
	 * @throws Exception
	 */
	public function destroy($id)
	{
		$res = $this->request("statuses/destroy/$id", 'POST');
		return $res->id ?: false;
	}


	/**
	 * Retrieves a single status.
	 * @param  int|string  $id  status to be retrieved
	 * @throws Exception
	 */
	public function get($id)
	{
		$res = $this->request("statuses/show/$id", 'GET');
		return $res;
	}


	/**
	 * Returns tweets that match a specified query.
	 * @param  string|array
	 * @param  bool  return complete response?
         * @param  bool  clickable return clickable text?
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/search/tweets
	 * @throws TwitterException
	 */
	public function search($query, $full = false, $clickable = false)
	{
		$res = $this->request('search/tweets', 'GET', is_array($query) ? $query : ['q' => $query]);
		return $this->formatTweets($full ? $res : $res->statuses, $clickable);
	}


	/**
	 * Process HTTP request.
	 * @param  string  $method  GET|POST|JSONPOST|DELETE
	 * @return stdClass|stdClass[]
	 * @throws Exception
	 */
	public function request(string $resource, string $method, array $data = [], array $files = [])
	{
		if (!strpos($resource, '://')) {
			if (!strpos($resource, '.')) {
				$resource .= '.json';
			}
			$resource = self::API_URL . $resource;
		}

		foreach ($data as $key => $val) {
			if ($val === null) {
				unset($data[$key]);
			}
		}

		foreach ($files as $key => $file) {
			if (!is_file($file)) {
				throw new Exception("Cannot read the file $file. Check if file exists on disk and check its permissions.");
			}
			$data[$key] = new \CURLFile($file);
		}

		$headers = ['Expect:'];

		if ($method === 'JSONPOST') {
			$method = 'POST';
			$data = json_encode($data);
			$headers[] = 'Content-Type: application/json';

		} elseif ($method === 'GET' && $data) {
			$resource .= '?' . http_build_query($data, '', '&');
		}

		$request = OAuth\Request::from_consumer_and_token($this->consumer, $this->token, $method, $resource);
		$request->sign_request(new OAuth\SignatureMethod_HMAC_SHA1, $this->consumer, $this->token);
		$headers[] = $request->to_header();

		$options = [
			CURLOPT_URL => $resource,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
		] + $this->httpOptions;

		if ($method === 'POST') {
			$options += [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $data,
				CURLOPT_SAFE_UPLOAD => true,
			];
		} elseif ($method === 'DELETE') {
			$options += [
				CURLOPT_CUSTOMREQUEST => 'DELETE',
			];
		}

		$curl = curl_init();
		curl_setopt_array($curl, $options);
		$result = curl_exec($curl);
		if (curl_errno($curl)) {
			throw new Exception('Server error: ' . curl_error($curl));
		}

		$payload = @json_decode($result, false, 128, JSON_BIGINT_AS_STRING); // intentionally @

		if ($payload === false) {
			throw new Exception('Invalid server response');
		}

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($code >= 400) {
			throw new Exception(isset($payload->errors[0]->message)
				? $payload->errors[0]->message
				: "Server error #$code with answer $result",
				$code
			);
		}

		return $payload;
	}


	/**
	 * Cached HTTP request.
	 * @return stdClass|stdClass[]
	 */
	public function cachedRequest(string $resource, array $data = [], $cacheExpire = null)
	{
		if (!self::$cacheDir) {
			return $this->request($resource, 'GET', $data);
		}
		if ($cacheExpire === null) {
			$cacheExpire = self::$cacheExpire;
		}

		$cacheFile = self::$cacheDir
			. '/twitter.'
			. md5($resource . json_encode($data) . serialize([$this->consumer, $this->token]))
			. '.json';

		$cache = @json_decode((string) @file_get_contents($cacheFile)); // intentionally @
		$expiration = is_string($cacheExpire) ? strtotime($cacheExpire) - time() : $cacheExpire;
		if ($cache && @filemtime($cacheFile) + $expiration > time()) { // intentionally @
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
	 * Makes twitter links, @usernames and #hashtags clickable.
	 */
	public static function clickable(stdClass $status): string
	{
		$all = [];
		foreach ($status->entities->hashtags as $item) {
			$all[$item->indices[0]] = ["https://twitter.com/search?q=%23$item->text", "#$item->text", $item->indices[1]];
		}
		foreach ($status->entities->urls as $item) {
			if (!isset($item->expanded_url)) {
				$all[$item->indices[0]] = [$item->url, $item->url, $item->indices[1]];
			} else {
				$all[$item->indices[0]] = [$item->expanded_url, $item->display_url, $item->indices[1]];
			}
		}
		foreach ($status->entities->user_mentions as $item) {
			$all[$item->indices[0]] = ["https://twitter.com/$item->screen_name", "@$item->screen_name", $item->indices[1]];
		}
		if (isset($status->entities->media)) {
			foreach ($status->entities->media as $item) {
				$all[$item->indices[0]] = [$item->url, $item->display_url, $item->indices[1]];
			}
		}

		krsort($all);
		$s = isset($status->full_text) ? $status->full_text : $status->text;
		foreach ($all as $pos => $item) {
			$s = iconv_substr($s, 0, $pos, 'UTF-8')
				. '<a href="' . htmlspecialchars($item[0]) . '">' . htmlspecialchars($item[1]) . '</a>'
				. iconv_substr($s, $item[2], iconv_strlen($s, 'UTF-8'), 'UTF-8');
		}
		return $s;
	}

	
    /**
     * format tweets if extended mode is set or if clickable is called
     * @param  stdClass[] tweets
     * @param  bool       clickable return clickable text?
     * @return stdClass[]
     */
	protected function formatTweets($tweets, $clickable = false)
	{
		// for full tweets, must add this to query: 'tweet_mode' => 'extended',
		// this will replace text by full_text, need to copy it
		if (!empty($tweets)) {
			// look at each tweet
			foreach ($tweets as &$tweet) {
				// get full_text as text for compatibility
				if (isset($tweet->full_text)) {
					$tweet->text = $tweet->full_text;
				}
				// while we are here, make links clickable
				if ($clickable) {
					$tweet->text = $this->clickable($tweet);
				}
			}
		}

		return $tweets;
	}
}



/**
 * An exception generated by Twitter.
 */
class Exception extends \Exception
{
}
