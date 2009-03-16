<?php

require_once 'twitter.class.php';

// enables caching (path must exists and must be writable!)
// Twitter::$cacheDir = dirname(__FILE__) . '/temp';


$twitter = new Twitter('pokusnyucet2', '123456');

$withFriends = FALSE;
$channel = $twitter->load($withFriends);

?>

<ul>
<?foreach ($channel->status as $status): ?>
	<li><a href="http://twitter.com/<?=$status->user->screen_name?>"><?=$status->user->name?></a>:
	<?=$status->text?>
	<small>at <?=date("j.n.Y H:i", strtotime($status->created_at))?></small>
	</li>
<?endforeach?>
</ul>
