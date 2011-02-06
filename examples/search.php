<?php

require_once '../twitter.class.php';


$twitter = new Twitter;

$results = $twitter->search('#nette');
// or use hashmap: $results = $twitter->search(array('q' => '#nette', 'geocode' => '50.088224,15.975611,20km'));

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
