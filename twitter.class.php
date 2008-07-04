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
		if (!extension_loaded('curl')) {
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
		$result = $this->httpRequest(
			'https://twitter.com/statuses/update.xml',
			array('status' => $message)
		);
		return strpos($result, '<created_at>') !== FALSE;
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
	 * @param array   post data
	 * @return string|FALSE
	 */
	private function httpRequest($url, $post = NULL)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERPWD, "$this->user:$this->pass");
		curl_setopt($curl, CURLOPT_HEADER, FALSE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		if ($post) {
			curl_setopt($curl, CURLOPT_POST, TRUE);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE); // no echo, just return result
		$result = curl_exec($curl);
		// debug: echo curl_errno($curl), ', ', curl_error($curl), htmlspecialchars($result);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if (curl_errno($curl) !== 0 || $code < 200 || $code >= 300) {
			return FALSE;
		} else {
			return $result;
		}
	}

}
