<?php

require_once 'twitter.class.php';

// enables caching (path must exists and must be writable!)
// Twitter::$cacheDir = dirname(__FILE__) . '/temp';


// ENTER HERE YOUR CREDENTIALS:
$twitter = new Twitter('pokusnyucet2', '123456');

$channel = $twitter->load(Twitter::ME_AND_FRIENDS);

?>

<ul>
<?foreach ($channel->status as $status): ?>
	<li><a href="http://twitter.com/<?=$status->user->screen_name?>"><img src="<?=$status->user->profile_image_url?>"> <?=$status->user->name?></a>:
	<?=$status->text?>
	<small>at <?=date("j.n.Y H:i", strtotime($status->created_at))?></small>
	</li>
<?endforeach?>
</ul>
