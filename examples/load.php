<?php

require_once '../twitter.class.php';

// enables caching (path must exists and must be writable!)
// Twitter::$cacheDir = dirname(__FILE__) . '/temp';


// ENTER HERE YOUR CREDENTIALS (see readme.txt)
$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

$channel = $twitter->load(Twitter::ME_AND_FRIENDS);

?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<ul>
<?foreach ($channel->status as $status): ?>
	<li><a href="http://twitter.com/<?=$status->user->screen_name?>"><img src="<?=$status->user->profile_image_url?>"> <?=$status->user->name?></a>:
	<?=$status->text?>
	<small>at <?=date("j.n.Y H:i", strtotime($status->created_at))?></small>
	</li>
<?endforeach?>
</ul>
