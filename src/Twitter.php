<?php

/**
 * Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 * This software is licensed under the New BSD License.
 *
 * Homepage:    https://phpfashion.com/twitter-for-php
 * Twitter API: https://dev.twitter.com/rest/public
 * Version:     3.6
 */



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
	public $httpOptions = array(
		CURLOPT_TIMEOUT => 20,
		CURLOPT_SSL_VERIFYPEER => 0,
		CURLOPT_HTTPHEADER => array('Expect:'),
		CURLOPT_USERAGENT => 'Twitter for PHP',
	);

	/** @var Twitter_OAuthConsumer */
	private $consumer;

	/** @var Twitter_OAuthConsumer */
	private $token;



	/**
	 * Creates object using consumer and access keys.
	 * @param  string  consumer key
	 * @param  string  app secret
	 * @param  string  optional access token
	 * @param  string  optinal access token secret
	 * @throws TwitterException when CURL extension is not loaded
	 */
	public function __construct($consumerKey, $consumerSecret, $accessToken = NULL, $accessTokenSecret = NULL)
	{
		if (!extension_loaded('curl')) {
			throw new TwitterException('PHP extension CURL is not loaded.');
		}

		$this->consumer = new Twitter_OAuthConsumer($consumerKey, $consumerSecret);
		$this->token = new Twitter_OAuthConsumer($accessToken, $accessTokenSecret);
	}


	/**
	 * Tests if user credentials are valid.
	 * @return bool
	 * @throws TwitterException
	 */
	public function authenticate()
	{
		try {
			$res = $this->request('account/verify_credentials', 'GET');
			return !empty($res->id);

		} catch (TwitterException $e) {
			if ($e->getCode() === 401) {
				return FALSE;
			}
			throw $e;
		}
	}


	/**
	 * Sends message to the Twitter.
	 * @param  string   message encoded in UTF-8
	 * @return stdClass  see https://dev.twitter.com/rest/reference/post/statuses/update
	 * @throws TwitterException
	 */
	public function send($message, $media = NULL)
	{
		$mediaIds = array();
		foreach ((array) $media as $item) {
			$res = $this->request(
				'https://upload.twitter.com/1.1/media/upload.json',
				'POST',
				NULL,
				array('media' => $item)
			);
			$mediaIds[] = $res->media_id_string;
		}
		return $this->request(
			'statuses/update',
			'POST',
			array('status' => $message, 'media_ids' => $mediaIds ? implode(',', $mediaIds) : NULL)
		);
	}


	/**
	 * Sends a direct message to the specified user.
	 * @return stdClass  see https://dev.twitter.com/rest/reference/post/direct_messages/new
	 * @throws TwitterException
	 */
	public function sendDirectMessage($username, $message)
	{
		return $this->request(
			'direct_messages/new',
			'POST',
			array('text' => $message, 'screen_name' => $username)
		);
	}


	/**
	 * Follows a user on Twitter.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/post/friendships/create
	 * @throws TwitterException
	 */
	public function follow($username)
	{
		return $this->request('friendships/create', 'POST', array('screen_name' => $username));
	}


	/**
	 * Returns the most recent statuses.
	 * @param  int    timeline (ME | ME_AND_FRIENDS | REPLIES) and optional (RETWEETS)
	 * @param  int    number of statuses to retrieve
	 * @param  array  additional options, see https://dev.twitter.com/rest/reference/get/statuses/user_timeline
	 * @return stdClass[]
	 * @throws TwitterException
	 */
	public function load($flags = self::ME, $count = 20, array $data = NULL)
	{
		static $timelines = array(
			self::ME => 'user_timeline',
			self::ME_AND_FRIENDS => 'home_timeline',
			self::REPLIES => 'mentions_timeline',
		);
		if (!isset($timelines[$flags & 3])) {
			throw new InvalidArgumentException;
		}

		return $this->cachedRequest('statuses/' . $timelines[$flags & 3], (array) $data + array(
			'count' => $count,
			'include_rts' => $flags & self::RETWEETS ? 1 : 0,
		));
	}


	/**
	 * Returns information of a given user.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/users/show
	 * @throws TwitterException
	 */
	public function loadUserInfo($username)
	{
		return $this->cachedRequest('users/show', array('screen_name' => $username));
	}


	/**
	 * Returns information of a given user by id.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/users/show
	 * @throws TwitterException
	 */
	public function loadUserInfoById($id)
	{
		return $this->cachedRequest('users/show', array('user_id' => $id));
	}


	/**
	 * Returns IDs of followers of a given user.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/followers/ids
	 * @throws TwitterException
	 */
	public function loadUserFollowers($username, $count = 5000, $cursor = -1, $cacheExpiry = null)
	{
		return $this->cachedRequest('followers/ids', array(
			'screen_name' => $username,
			'count' => $count,
			'cursor' => $cursor,
		), $cacheExpiry);
	}


	/**
	 * Returns list of followers of a given user.
	 * @param  string
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/followers/list
	 * @throws TwitterException
	 */
	public function loadUserFollowersList($username, $count = 200, $cursor = -1, $cacheExpiry = null)
	{
		return $this->cachedRequest('followers/list', array(
			'screen_name' => $username,
			'count' => $count,
			'cursor' => $cursor,
		), $cacheExpiry);
	}


	/**
	 * Destroys status.
	 * @param  int|string  id of status to be destroyed
	 * @return mixed
	 * @throws TwitterException
	 */
	public function destroy($id)
	{
		$res = $this->request("statuses/destroy/$id", 'POST');
		return $res->id ? $res->id : FALSE;
	}


	/**
	 * Returns tweets that match a specified query.
	 * @param  string|array
	 * @param  bool  return complete response?
	 * @return stdClass  see https://dev.twitter.com/rest/reference/get/search/tweets
	 * @throws TwitterException
	 */
	public function search($query, $full = FALSE)
	{
		$res = $this->request('search/tweets', 'GET', is_array($query) ? $query : array('q' => $query));
		return $full ? $res : $res->statuses;
	}


	/**
	 * Process HTTP request.
	 * @param  string  URL or twitter command
	 * @param  string  HTTP method GET or POST
	 * @param  array   data
	 * @param  array   uploaded files
	 * @return stdClass|stdClass[]
	 * @throws TwitterException
	 */
	public function request($resource, $method, array $data = NULL, array $files = NULL)
	{
		if (!strpos($resource, '://')) {
			if (!strpos($resource, '.')) {
				$resource .= '.json';
			}
			$resource = self::API_URL . $resource;
		}

		$hasCURLFile = class_exists('CURLFile', FALSE) && defined('CURLOPT_SAFE_UPLOAD');

		foreach ((array) $data as $key => $val) {
			if ($val === NULL) {
				unset($data[$key]);
			} elseif ($files && !$hasCURLFile && substr($val, 0, 1) === '@') {
				throw new TwitterException('Due to limitation of cURL it is not possible to send message starting with @ and upload file at the same time in PHP < 5.5');
			}
		}

		foreach ((array) $files as $key => $file) {
			if (!is_file($file)) {
				throw new TwitterException("Cannot read the file $file. Check if file exists on disk and check its permissions.");
			}
			$data[$key] = $hasCURLFile ? new CURLFile($file) : '@' . $file;
		}

		$request = Twitter_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $resource, $files ? array() : $data);
		$request->sign_request(new Twitter_OAuthSignatureMethod_HMAC_SHA1, $this->consumer, $this->token);

		$options = array(
			CURLOPT_HEADER => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
		) + ($method === 'POST' ? array(
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $files ? $data : $request->to_postdata(),
			CURLOPT_URL => $files ? $request->to_url() : $request->get_normalized_http_url(),
		) : array(
			CURLOPT_URL => $request->to_url(),
		)) + $this->httpOptions;

		if ($method === 'POST' && $hasCURLFile) {
			$options[CURLOPT_SAFE_UPLOAD] = TRUE;
		}

		$curl = curl_init();
		curl_setopt_array($curl, $options);
		$result = curl_exec($curl);
		if (curl_errno($curl)) {
			throw new TwitterException('Server error: ' . curl_error($curl));
		}

		$payload = defined('JSON_BIGINT_AS_STRING')
			? @json_decode($result, FALSE, 128, JSON_BIGINT_AS_STRING)
			: @json_decode($result); // intentionally @

		if ($payload === FALSE) {
			throw new TwitterException('Invalid server response');
		}

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($code >= 400) {
			throw new TwitterException(isset($payload->errors[0]->message)
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
	public function cachedRequest($resource, array $data = NULL, $cacheExpire = NULL)
	{
		if (!self::$cacheDir) {
			return $this->request($resource, 'GET', $data);
		}
		if ($cacheExpire === NULL) {
			$cacheExpire = self::$cacheExpire;
		}

		$cacheFile = self::$cacheDir
			. '/twitter.'
			. md5($resource . json_encode($data) . serialize(array($this->consumer, $this->token)))
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

		} catch (TwitterException $e) {
			if ($cache) {
				return $cache;
			}
			throw $e;
		}
	}


	/**
	 * Makes twitter links, @usernames and #hashtags clickable.
	 * @param  stdClass  status
	 * @return string
	 */
	public static function clickable($status)
	{
		if (!is_object($status)) { // back compatibility
			trigger_error(__METHOD__ . '() has been changed; pass as parameter status object, not just text.', E_USER_WARNING);
			return preg_replace_callback(
				'~(?<!\w)(https?://\S+\w|www\.\S+\w|@\w+|#\w+)|[<>&]~u',
				array(__CLASS__, 'clickableCallback'),
				html_entity_decode($status, ENT_QUOTES, 'UTF-8')
			);
		}

		$all = array();
		foreach ($status->entities->hashtags as $item) {
			$all[$item->indices[0]] = array("https://twitter.com/search?q=%23$item->text", "#$item->text", $item->indices[1]);
		}
		foreach ($status->entities->urls as $item) {
			if (!isset($item->expanded_url)) {
				$all[$item->indices[0]] = array($item->url, $item->url, $item->indices[1]);
			} else {
				$all[$item->indices[0]] = array($item->expanded_url, $item->display_url, $item->indices[1]);
			}
		}
		foreach ($status->entities->user_mentions as $item) {
			$all[$item->indices[0]] = array("https://twitter.com/$item->screen_name", "@$item->screen_name", $item->indices[1]);
		}
		if (isset($status->entities->media)) {
			foreach ($status->entities->media as $item) {
				$all[$item->indices[0]] = array($item->url, $item->display_url, $item->indices[1]);
			}
		}

		krsort($all);
		$s = $status->text;
		foreach ($all as $pos => $item) {
			$s = iconv_substr($s, 0, $pos, 'UTF-8')
				. '<a href="' . htmlspecialchars($item[0]) . '">' . htmlspecialchars($item[1]) . '</a>'
				. iconv_substr($s, $item[2], iconv_strlen($s, 'UTF-8'), 'UTF-8');
		}
		return $s;
	}


	private static function clickableCallback($m)
	{
		$m = htmlspecialchars($m[0]);
		if ($m[0] === '#') {
			$m = substr($m, 1);
			return "<a href='https://twitter.com/search?q=%23$m'>#$m</a>";
		} elseif ($m[0] === '@') {
			$m = substr($m, 1);
			return "@<a href='https://twitter.com/$m'>$m</a>";
		} elseif ($m[0] === 'w') {
			return "<a href='http://$m'>$m</a>";
		} elseif ($m[0] === 'h') {
			return "<a href='$m'>$m</a>";
		} else {
			return $m;
		}
	}

}



/**
 * An exception generated by Twitter.
 */
class TwitterException extends Exception
{
}
