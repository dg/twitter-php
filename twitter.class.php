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
		if (!function_exists('curl_version')) {
			throw new Exception('PHP extension CURL is not loaded.');
		}

		$this->user = $user;
		$this->pass = $pass;
	}



	/**
	 * Sends message to the Twitter.
	 * @param string   message encoded in UTF-8
	 * @return boolean TRUE on success or FALSE on failure
	 */
	public function send($message)
	{
		$url = 'http://twitter.com/statuses/update.xml?status=' . urlencode($message);
		return strpos($this->httpRequest($url), '<created_at>') !== FALSE;
	}



	/**
	 * Returns the 20 most recent statuses posted from you and your friends (optionally).
	 * @param  bool  with friends?
	 * @return SimpleXMLElement
	 * @throws Exception
	 */
	public function load($withFriends)
	{
		$line = $withFriends ? 'friends_timeline' : 'user_timeline';
		$url = "http://twitter.com/statuses/$line/$this->user.xml";
		$feed = $this->httpRequest($url);
		if ($feed === FALSE) {
			throw new Exception('Cannot load channel.');
		}

		$xml = new SimpleXMLElement($feed);
		if (!$xml || !$xml->status) {
			throw new Exception('Invalid channel.');
		}

		return $xml;
	}



	/**
	 * Process HTTP request.
	 * @param string  URL
	 * @return string|FALSE
	 */
	private function httpRequest($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,        $url);
		curl_setopt($ch, CURLOPT_USERPWD,    "$this->user:$this->pass");
		curl_setopt($ch, CURLOPT_HEADER,     FALSE);
		curl_setopt($ch, CURLOPT_POST,       TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // no echo, just return result
		$result = curl_exec($ch);
		// debug: echo curl_errno($ch), ', ', curl_error($ch), htmlspecialchars($result);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (curl_errno($ch) !== 0 || $code < 200 || $code >= 300) {
			return FALSE;
		} else {
			return $result;
		}
	}

}
