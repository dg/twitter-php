<?php

require_once dirname(__FILE__) . '/OAuth.php';


/**
 * Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 * @link       http://phpfashion.com/
 * @see        http://dev.twitter.com/doc
 * @version    3.0
 */
class Twitter
{
	const API_URL = 'http://api.twitter.com/1.1/';

	/**#@+ Timeline {@link Twitter::load()} */
	const ME = 1;
	const ME_AND_FRIENDS = 2;
	const REPLIES = 3;
	const RETWEETS = 128; // include retweets?
	/**#@-*/

	/** @var int */
	public static $cacheExpire = 1800; // 30 min

	/** @var string */
	public static $cacheDir;

	/** @var array */
	public $httpOptions = array(
		CURLOPT_TIMEOUT => 20,
		CURLOPT_SSL_VERIFYPEER => 0,
		CURLOPT_HTTPHEADER => array('Expect:'),
		CURLOPT_USERAGENT => 'Twitter for PHP',
	);

	/** @var Twitter_OAuthSignatureMethod */
	private $signatureMethod;

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

		$this->signatureMethod = new Twitter_OAuthSignatureMethod_HMAC_SHA1();
		$this->consumer = new Twitter_OAuthConsumer($consumerKey, $consumerSecret);
		$this->token = new Twitter_OAuthConsumer($accessToken, $accessTokenSecret);
	}



	/**
	 * Tests if user credentials are valid.
	 * @return boolean
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
	 * @param string   message encoded in UTF-8
	 * @return object
	 * @throws TwitterException
	 */
	public function send($message)
	{
		return $this->request('statuses/update', 'POST', array('status' => $message));
	}



	/**
	 * Returns the most recent statuses.
	 * @param  int    timeline (ME | ME_AND_FRIENDS | REPLIES) and optional (RETWEETS)
	 * @param  int    number of statuses to retrieve
	 * @param  int    page of results to retrieve
	 * @return mixed
	 * @throws TwitterException
	 */
	public function load($flags = self::ME, $count = 20, array $data = NULL)
	{
		static $timelines = array(self::ME => 'user_timeline', self::ME_AND_FRIENDS => 'home_timeline', self::REPLIES => 'mentions_timeline');
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
	 * @param  string name
	 * @return mixed
	 * @throws TwitterException
	 */
	public function loadUserInfo($user)
	{
		return $this->cachedRequest('users/show', array('screen_name' => $user));
	}



	/**
	 * Destroys status.
	 * @param  int    id of status to be destroyed
	 * @return mixed
	 * @throws TwitterException
	 */
	public function destroy($id)
	{
		$res = $this->request("statuses/destroy/$id", 'GET');
		return $res->id ? $res->id : FALSE;
	}



	/**
	 * Returns tweets that match a specified query.
	 * @param  string|array   query
	 * @return mixed
	 * @throws TwitterException
	 */
	public function search($query)
	{
		return $this->request('search/tweets', 'GET', is_array($query) ? $query : array('q' => $query))->statuses;
	}



	/**
	 * Process HTTP request.
	 * @param  string  URL or twitter command
	 * @param  string  HTTP method GET or POST
	 * @param  array   data
	 * @return mixed
	 * @throws TwitterException
	 */
	public function request($resource, $method, array $data = NULL)
	{
		if (!strpos($resource, '://')) {
			if (!strpos($resource, '.')) {
				$resource .= '.json';
			}
			$resource = self::API_URL . $resource;
		}

		foreach (array_keys((array) $data, NULL, TRUE) as $key) {
			unset($data[$key]);
		}

		$request = Twitter_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $resource, $data);
		$request->sign_request($this->signatureMethod, $this->consumer, $this->token);

		$options = array(
			CURLOPT_HEADER => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
		) + ($method === 'POST' ? array(
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $request->to_postdata(),
			CURLOPT_URL => $request->get_normalized_http_url(),
		) : array(
			CURLOPT_URL => $request->to_url(),
		)) + $this->httpOptions;

		$curl = curl_init();
		curl_setopt_array($curl, $options);
		$result = curl_exec($curl);
		if (curl_errno($curl)) {
			throw new TwitterException('Server error: ' . curl_error($curl));
		}

		$payload = version_compare(PHP_VERSION, '5.4.0') >= 0 ?
			@json_decode($result, FALSE, 128, JSON_BIGINT_AS_STRING) : @json_decode($result); // intentionally @

		if ($payload === FALSE) {
			throw new TwitterException('Invalid server response');
		}

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($code >= 400) {
			throw new TwitterException(isset($payload->errors[0]->message) ? $payload->errors[0]->message : "Server error #$code", $code);
		}

		return $payload;
	}



	/**
	 * Cached HTTP request.
	 * @param  string  URL or twitter command
	 * @param  array
	 * @param  int
	 * @return mixed
	 */
	public function cachedRequest($resource, array $data = NULL, $cacheExpire = NULL)
	{
		if (!self::$cacheDir) {
			return $this->request($resource, 'GET', $data);
		}
		if ($cacheExpire === NULL) {
			$cacheExpire = self::$cacheExpire;
		}

		$cacheFile = self::$cacheDir . '/twitter.' . md5($resource . json_encode($data) . serialize(array($this->consumer, $this->token)));
		$cache = @json_decode(@file_get_contents($cacheFile)); // intentionally @
		if ($cache && @filemtime($cacheFile) + $cacheExpire > time()) { // intentionally @
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
	 * @param  stdClass|string status
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
			$all[$item->indices[0]] = array("http://twitter.com/search?q=%23$item->text", "#$item->text", $item->indices[1]);
		}
		foreach ($status->entities->urls as $item) {
			if (!isset($item->expanded_url)) {
				$all[$item->indices[0]] = array($item->url, $item->url, $item->indices[1]);
			} else {
				$all[$item->indices[0]] = array($item->expanded_url, $item->display_url, $item->indices[1]);
			}
		}
		foreach ($status->entities->user_mentions as $item) {
			$all[$item->indices[0]] = array("http://twitter.com/$item->screen_name", "@$item->screen_name", $item->indices[1]);
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
			return "<a href='http://twitter.com/search?q=%23$m'>#$m</a>";
		} elseif ($m[0] === '@') {
			$m = substr($m, 1);
			return "@<a href='http://twitter.com/$m'>$m</a>";
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
