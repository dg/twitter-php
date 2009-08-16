<?php

/**
 * Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 * @link       http://phpfashion.com/
 * @version    1.0
 */
class Twitter
{
	/** @var int */
	public static $cacheExpire = 1800; // 30 min

	/** @var string */
	public static $cacheDir;

	/** @var  user name */
	private $user;

	/** @var  password */
	private $pass;



	/**
	 * Creates object using your credentials.
	 * @param  string  user name
	 * @param  string  password
	 * @throws Exception
	 */
	public function __construct($user, $pass)
	{
		if (!extension_loaded('curl')) {
			throw new Exception('PHP extension CURL is not loaded.');
		}

		$this->user = $user;
		$this->pass = $pass;
	}



	/**
	 * Tests if user credentials are valid.
	 * @return boolean
	 * @throws Exception
	 */
	public function authenticate()
	{
		$xml = $this->httpRequest('http://twitter.com/account/verify_credentials.xml');
		return (bool) $xml;
	}



	/**
	 * Sends message to the Twitter.
	 * @param string   message encoded in UTF-8
	 * @return mixed   ID on success or FALSE on failure
	 */
	public function send($message)
	{
		$xml = $this->httpRequest(
			'https://twitter.com/statuses/update.xml',
			array('status' => $message)
		);
		return $xml && $xml->id ? (string) $xml->id : FALSE;
	}



	/**
	 * Returns the 20 most recent statuses posted from you and your friends (optionally).
	 * @param  bool  with friends?
	 * @param  int   number of statuses to retrieve
	 * @param  int   page of results to retrieve
	 * @return SimpleXMLElement
	 * @throws Exception
	 */
	public function load($withFriends, $count = 20, $page = 1)
	{
		$line = $withFriends ? 'friends_timeline' : 'user_timeline';
		$xml = $this->httpRequest("http://twitter.com/statuses/$line/$this->user.xml?count=$count&page=$page", FALSE);
		if (!$xml || !$xml->status) {
			throw new Exception('Cannot load channel.');
		}
		return $xml;
	}



	/**
	 * Process HTTP request.
	 * @param string  URL
	 * @param array   of post data (or FALSE = cached get)
	 * @return SimpleXMLElement|FALSE
	 */
	private function httpRequest($url, $post = NULL)
	{
		if ($post === FALSE && self::$cacheDir) {
			$cacheFile = self::$cacheDir . '/twitter.' . md5($url) . '.xml';
			if (@filemtime($cacheFile) + self::$cacheExpire > time()) {
				return new SimpleXMLElement(@file_get_contents($cacheFile));
			}
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERPWD, "$this->user:$this->pass");
		curl_setopt($curl, CURLOPT_HEADER, FALSE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));

		if ($post) {
			curl_setopt($curl, CURLOPT_POST, TRUE);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE); // no echo, just return result
		$result = curl_exec($curl);
		$ok = curl_errno($curl) === 0 && curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200; // code 200 is required

		if (!$ok) {
			if (isset($cacheFile)) {
				$result = @file_get_contents($cacheFile);
				if (is_string($result)) {
					return new SimpleXMLElement($result);
				}
			}
			return FALSE;
		}

		if (isset($cacheFile)) {
			file_put_contents($cacheFile, $result);
		}

		return new SimpleXMLElement($result);
	}

}
