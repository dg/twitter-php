<?php

/**
 * Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 * This software is licensed under the New BSD License.
 *
 * Homepage:    https://phpfashion.com/twitter-for-php
 * Twitter API: https://dev.twitter.com/rest/public
 * Version:     3.8
 */

namespace DG\Twitter;

use stdClass;


/**
 * Twitter API.
 */
class Twitter
{
	const API_URL = 'https://api.twitter.com/1.1/';

	/**#@+ Timeline {@link Twitter::load()} */
	const ME = 1;
	const ME_AND_FRIENDS = 2;
	const REPLIES = 3;
	const RETWEETS = 128; // include retweets?
	/**#@-*/

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

	/** @var \Twitter_OAuthConsumer */
	private $consumer;

	/** @var \Twitter_OAuthConsumer */
	private $token;


	/**
	 * Creates object using consumer and access keys.
	 * @param  string  consumer key
	 * @param  string  app secret
	 * @param  string  optional access token
	 * @param  string  optinal access token secret
	 * @throws Exception when CURL extension is not loaded
	 */
	public function __construct($consumerKey, $consumerSecret, $accessToken = null, $accessTokenSecret = null)
	{
		if (!extension_loaded('curl')) {
			throw new Exception('PHP extension CURL is not loaded.');
		}

		$this->consumer = new \Twitter_OAuthConsumer($consumerKey, $consumerSecret);
		$this->token = new \Twitter_OAuthConsumer($accessToken, $accessTokenSecret);
	}


	/**
	 * Tests if user credentials are valid.
	 * @return bool
	 * @throws Exception
	 */
	public function authenticate()
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
	 * @param  string   message encoded in UTF-8
	 * @param  string  path to local media file to be uploaded
	 * @param  array  additional options to send to statuses/update
	 * @return stdClass  see https://dev.twitter.com/rest/reference/post/statuses/update
	 * @throws Exception
	 */
	public function send($message, $media = null, $options = [])
	{
		$mediaIds = [];
		foreach ((array) $media as $item) {
			$res = $this->request(
				'https://upload.twitter.com/1.1/media/upload.json',
				'POST',
				null,
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
	 * @return stdClass  see https://dev.twitter.com/rest/reference/post/direct_messages/new
	 * @throws Exception
	 */
	public function sendDirectMessage($username, $message)
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
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/post/friendships/create
	 * @throws Exception
	 */
	public function follow($username)
	{
		return $this->request('friendships/create', 'POST', ['screen_name' => $username]);
	}


	/**
	 * Returns the most recent statuses.
	 * @param  int    timeline (ME | ME_AND_FRIENDS | REPLIES) and optional (RETWEETS)
	 * @param  int    number of statuses to retrieve
	 * @param  array  additional options, see https://dev.twitter.com/rest/reference/get/statuses/user_timeline
	 * @return stdClass[]
	 * @throws Exception
	 */
	public function load($flags = self::ME, $count = 20, array $data = null)
	{
		static $timelines = [
			self::ME => 'user_timeline',
			self::ME_AND_FRIENDS => 'home_timeline',
			self::REPLIES => 'mentions_timeline',
		];
		if (!isset($timelines[$flags & 3])) {
			throw new \InvalidArgumentException;
		}

		return $this->cachedRequest('statuses/' . $timelines[$flags & 3], (array) $data + [
			'count' => $count,
			'include_rts' => $flags & self::RETWEETS ? 1 : 0,
		]);
	}


	/**
	 * Returns information of a given user.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/users/show
	 * @throws Exception
	 */
	public function loadUserInfo($username)
	{
		return $this->cachedRequest('users/show', ['screen_name' => $username]);
	}


	/**
	 * Returns information of a given user by id.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/users/show
	 * @throws Exception
	 */
	public function loadUserInfoById($id)
	{
		return $this->cachedRequest('users/show', ['user_id' => $id]);
	}


	/**
	 * Returns IDs of followers of a given user.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/followers/ids
	 * @throws Exception
	 */
	public function loadUserFollowers($username, $count = 5000, $cursor = -1, $cacheExpiry = null)
	{
		return $this->cachedRequest('followers/ids', [
			'screen_name' => $username,
			'count' => $count,
			'cursor' => $cursor,
		], $cacheExpiry);
	}


	/**
	 * Returns list of followers of a given user.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/followers/list
	 * @throws Exception
	 */
	public function loadUserFollowersList($username, $count = 200, $cursor = -1, $cacheExpiry = null)
	{
		return $this->cachedRequest('followers/list', [
			'screen_name' => $username,
			'count' => $count,
			'cursor' => $cursor,
		], $cacheExpiry);
	}


	/**
	 * Destroys status.
	 * @param  int|string  id of status to be destroyed
	 * @return mixed
	 * @throws Exception
	 */
	public function destroy($id)
	{
		$res = $this->request("statuses/destroy/$id", 'POST');
		return $res->id ? $res->id : false;
	}


	/**
	 * Returns tweets that match a specified query.
	 * @param  string|array
	 * @param  bool  return complete response?
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/search/tweets
	 * @throws Exception
	 */
	public function search($query, $full = false)
	{
		$res = $this->request('search/tweets', 'GET', is_array($query) ? $query : ['q' => $query]);
		return $full ? $res : $res->statuses;
	}


	/**
	 * Process HTTP request.
	 * @param  string  URL or twitter command
	 * @param  string  HTTP method GET or POST or JSONPOST
	 * @param  array   data
	 * @param  array   uploaded files
	 * @return stdClass|stdClass[]
	 * @throws Exception
	 */
	public function request($resource, $method, array $data = null, array $files = null)
	{
		if (!strpos($resource, '://')) {
			if (!strpos($resource, '.')) {
				$resource .= '.json';
			}
			$resource = self::API_URL . $resource;
		}

		foreach ((array) $data as $key => $val) {
			if ($val === null) {
				unset($data[$key]);
			}
		}

		foreach ((array) $files as $key => $file) {
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

		$request = \Twitter_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $resource);
		$request->sign_request(new \Twitter_OAuthSignatureMethod_HMAC_SHA1, $this->consumer, $this->token);
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
	 * @param  string  URL or twitter command
	 * @param  array
	 * @param  int
	 * @return stdClass|stdClass[]
	 */
	public function cachedRequest($resource, array $data = null, $cacheExpire = null)
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

		$cache = @json_decode(@file_get_contents($cacheFile)); // intentionally @
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
	 * @return string
	 */
	public static function clickable(stdClass $status)
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
}



/**
 * An exception generated by Twitter.
 */
class Exception extends \Exception
{
}
