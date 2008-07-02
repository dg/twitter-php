<?php

require_once 'twitter.class.php';


$twitter = new Twitter('pokusnyucet2', '123456');

$withFriends = FALSE;
$channel = $twitter->load($withFriends);

?>

<h1><?$channel->title?></h1>

<ul>
<?foreach ($channel->item as $item): ?>
	<li><?=$item->description?>
		(<a href="<?=$item->link?>"><?=date("j.n.Y H:s", strtotime($item->pubDate))?></a>)
	</li>
<?endforeach?>
</ul>
