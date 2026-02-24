<?php declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}

Tester\Environment::setup();
Tester\Environment::setupFunctions();


function getTempDir(): string
{
	$dir = __DIR__ . '/tmp/' . getmypid();

	if (empty($GLOBALS['\lock'])) {
		$GLOBALS['\lock'] = $lock = fopen(__DIR__ . '/lock', 'w');
		if (rand(0, 100)) {
			flock($lock, LOCK_SH);
			@mkdir(dirname($dir)); // @ - directory may already exist
		} elseif (flock($lock, LOCK_EX)) {
			Tester\Helpers::purge(dirname($dir));
		}

		@mkdir($dir); // @ - directory may already exist
	}

	return $dir;
}


/**
 * Returns test credentials from environment variables, or null if not set.
 * @return array{consumerKey: string, consumerSecret: string, accessToken: string, accessTokenSecret: string}|null
 */
function getTestCredentials(): ?array
{
	$consumerKey = getenv('X_CONSUMER_KEY');
	$consumerSecret = getenv('X_CONSUMER_SECRET');
	$accessToken = getenv('X_ACCESS_TOKEN');
	$accessTokenSecret = getenv('X_ACCESS_TOKEN_SECRET');

	if (!$consumerKey || !$consumerSecret || !$accessToken || !$accessTokenSecret) {
		return null;
	}

	return [
		'consumerKey' => $consumerKey,
		'consumerSecret' => $consumerSecret,
		'accessToken' => $accessToken,
		'accessTokenSecret' => $accessTokenSecret,
	];
}
