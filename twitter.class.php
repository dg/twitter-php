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
 * @version    2.2
 */
class Twitter
{
	/**#@+ Timeline {@link Twitter::load()} */
	const ME = 1;
	const ME_AND_FRIENDS = 2;
	const REPLIES = 3;
	const ALL = 4;
	const RETWEETS = 128; // include retweets?
	/**#@-*/

	/**#@+ Output format {@link Twitter::load()} */
	const XML = 0;
	const JSON = 16;
	const RSS = 32;
	const ATOM = 48;
	/**#@-*/

	/** @var int */
	public static $cacheExpire = 1800; // 30 min

	/** @var string */
	public static $cacheDir;

	/** @var OAuthSignatureMethod */
	private $signatureMethod;

	/** @var OAuthConsumer */
	private $consumer;

	/** @var OAuthConsumer */
	private $token;



	/**
	 * Creates object using consumer and access keys.
	 * @param  string  consumer key
	 * @param  string  app secret
	 * @param  string  optional access token
	 * @param  string  optinal access token secret
	 * @throws TwitterException when allow_url_fopen is not enabled
	 */
	public function __construct($consumerKey = NULL, $consumerSecret = NULL, $accessToken = NULL, $accessTokenSecret = NULL)
	{
		if (!ini_get('allow_url_fopen')) {
			throw new TwitterException('PHP directive allow_url_fopen is not enabled.');
		}
		$this->signatureMethod = new OAuthSignatureMethod_HMAC_SHA1();
		$this->consumer = new OAuthConsumer($consumerKey, $consumerSecret);
		$this->token = new OAuthConsumer($accessToken, $accessTokenSecret);
	}



	/**
	 * Tests if user credentials are valid.
	 * @return boolean
	 * @throws TwitterException
	 */
	public function authenticate()
	{
		try {
			$xml = $this->request('account/verify_credentials', NULL, 'GET');
			return !empty($xml->id);

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
	 * @return mixed   ID on success or FALSE on failure
	 * @throws TwitterException
	 */
	public function send($message)
	{
		if (iconv_strlen($message, 'UTF-8') > 140) {
			$message = preg_replace_callback('#https?://\S+[^:);,.!?\s]#', array($this, 'shortenUrl'), $message);
		}

		$xml = $this->request('statuses/update', array('status' => $message));
		return $xml->id ? (string) $xml->id : FALSE;
	}



	/**
	 * Returns the most recent statuses.
	 * @param  int    timeline (ME | ME_AND_FRIENDS | REPLIES | ALL) and optional (RETWEETS) or format (XML | JSON | RSS | ATOM)
	 * @param  int    number of statuses to retrieve
	 * @param  int    page of results to retrieve
	 * @return mixed
	 * @throws TwitterException
	 */
	public function load($flags = self::ME, $count = 20, $page = 1)
	{
		static $timelines = array(self::ME => 'user_timeline', self::ME_AND_FRIENDS => 'friends_timeline', self::REPLIES => 'mentions', self::ALL => 'public_timeline');

		if (!is_int($flags)) { // back compatibility
			$flags = $flags ? self::ME_AND_FRIENDS : self::ME;

		} elseif (!isset($timelines[$flags & 0x0F])) {
			throw new InvalidArgumentException;
		}

		return $this->cachedRequest('statuses/' . $timelines[$flags & 0x0F] . '.' . self::getFormat($flags), array(
			'count' => $count,
			'page' => $page,
			'include_rts' => $flags & self::RETWEETS ? 1 : 0,
		));
	}



	/**
	 * Returns information of a given user.
	 * @param  string name
	 * @param  int    format (XML | JSON)
	 * @return mixed
	 * @throws TwitterException
	 */
	public function loadUserInfo($user, $flags = self::XML)
	{
		return $this->cachedRequest('users/show.' . self::getFormat($flags), array('screen_name' => $user));
	}



	/**
	 * Destroys status.
	 * @param  int    id of status to be destroyed
	 * @return mixed
	 * @throws TwitterException
	 */
	public function destroy($id)
	{
		$xml = $this->request("statuses/destroy/$id");
		return $xml->id ? (string) $xml->id : FALSE;
	}



	/**
	 * Returns tweets that match a specified query.
	 * @param  string|array   query
	 * @param  int      format (JSON | ATOM)
	 * @return mixed
	 * @throws TwitterException
	 */
	public function search($query, $flags = self::JSON)
	{
		return $this->request(
			'http://search.twitter.com/search.' . self::getFormat($flags),
			is_array($query) ? $query : array('q' => $query),
			'GET'
		)->results;
	}



	/**
	 * Process HTTP request.
	 * @param  string  URL or twitter command
	 * @param  array   data
	 * @param  string  HTTP method
	 * @return mixed
	 * @throws TwitterException
	 */
	public function request($request, $data = NULL, $method = 'POST')
	{
		if (!strpos($request, '://')) {
			if (!strpos($request, '.')) {
				$request .= '.json';
			}
			$request = 'http://api.twitter.com/1/' . $request;
		}

		$request = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $request, $data);
		$request->sign_request($this->signatureMethod, $this->consumer, $this->token);

		$options = array(
			'method' => $method,
			'timeout' => 20,
			'content' => $method === 'POST' ? $request->to_postdata() : NULL,
			'user_agent' => 'Twitter for PHP',
		);

		$f = @fopen($method === 'POST' ? $request->get_normalized_http_url() : $request->to_url(),
			'r', FALSE, stream_context_create(array('http' => $options)));
		if (!$f) {
			throw new TwitterException('Server error');
		}

		$result = stream_get_contents($f);
		$payload = @simplexml_load_string($result); // intentionally @
		if (empty($payload)) {
			$payload = @json_decode($result); // intentionally @
			if (empty($payload)) {
				throw new TwitterException('Invalid server response');
			}
		}
		return $payload;
	}



	/**
	 * Cached HTTP request.
	 * @param  string  URL or twitter command
	 * @return array
	 */
	public function cachedRequest($request, $data = NULL)
	{
		if (!self::$cacheDir) {
			return $this->request($request, $data, 'GET');
		}

		$cacheFile = self::$cacheDir . '/twitter.' . md5($request . json_encode($data));
		$cache = @file_get_contents($cacheFile); // intentionally @
		$cache = strncmp($cache, '<', 1) ? @json_decode($cache) : @simplexml_load_string($cache); // intentionally @
		if ($cache && @filemtime($cacheFile) + self::$cacheExpire > time()) { // intentionally @
			return $cache;
		}

		try {
			$payload = $this->request($request, $data, 'GET');
			file_put_contents($cacheFile, $payload instanceof SimpleXMLElement ? $payload->asXml() : json_encode($payload));
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
	 * @param  string
	 * @return string
	 */
	public static function clickable($s)
	{
		return preg_replace_callback(
			'~(?<!\w)(https?://\S+\w|www\.\S+\w|@\w+|#\w+|<>&)~u',
			array(__CLASS__, 'clickableCallback'),
			html_entity_decode($s, ENT_QUOTES, 'UTF-8')
		);
	}



	private static function clickableCallback($m)
	{
		$m = htmlspecialchars($m[1]);
		if ($m[0] === '#') {
			$m = substr($m, 1);
			return "<a href='http://twitter.com/search?q=%23$m'>#$m</a>";
		} elseif ($m[0] === '@') {
			$m = substr($m, 1);
			return "@<a href='http://www.twitter.com/$m'>$m</a>";
		} elseif ($m[0] === 'w') {
			return "<a href='http://$m'>$m</a>";
		} elseif ($m[0] === 'h') {
			return "<a href='$m'>$m</a>";
		} else {
			return $m;
		}
	}



	/**
	 * Shortens URL using http://is.gd API.
	 * @param  array
	 * @return string
	 */
	private function shortenUrl($m)
	{
		$f = @fopen('http://is.gd/api.php?longurl=' . urlencode($m[0]), 'r');
		return $f ? stream_get_contents($f) : $m[0];
	}



	private static function getFormat($flag)
	{
		static $formats = array(self::XML => 'xml', self::JSON => 'json', self::RSS => 'rss', self::ATOM => 'atom');
		$flag = $flag & 0x30;
		if (isset($formats[$flag])) {
			return $formats[$flag];
		} else {
			throw new InvalidArgumentException('Invalid format');
		}
	}

}



/**
 * An exception generated by Twitter.
 */
class TwitterException extends Exception
{
}
