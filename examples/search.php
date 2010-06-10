<?php

require_once '../twitter.class.php';


// ENTER HERE YOUR CREDENTIALS:
$twitter = new Twitter;

$results = $twitter->search('#nette');

?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<ul>
<?foreach ($results as $result): ?>
	<li><a href="http://twitter.com/<?=$result->from_user?>"><img src="<?=$result->profile_image_url?>" width="48"> <?=$result->from_user?></a>:
	<?=$result->text?>
	<small>at <?=date("j.n.Y H:i", strtotime($result->created_at))?></small>
	</li>
<?endforeach?>
</ul>
