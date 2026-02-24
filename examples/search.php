<?php declare(strict_types=1);

use DG\X\Client;

require_once __DIR__ . '/../vendor/autoload.php';

// ENTER HERE YOUR CREDENTIALS (see readme.md)
$x = new Client($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

$tweets = $x->search('#php');

?>
<!doctype html>
<meta charset="utf-8">
<title>X search demo</title>

<ul>
<?php foreach ($tweets as $tweet) { ?>
	<li><?php echo Client::clickable($tweet) ?>
</li>
<?php } ?>
</ul>
