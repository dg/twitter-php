<?php

require_once '../twitter.class.php';


$twitter = new Twitter;

$results = $twitter->search('#nette');
// or use hashmap: $results = $twitter->search(array('q' => '#nette', 'geocode' => '50.088224,15.975611,20km'));

?>
<!doctype html>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Twitter search demo</title>

<ul>
<?php foreach ($results as $result): ?>
	<li><a href="http://twitter.com/<?php echo $result->from_user ?>"><img src="<?php echo htmlspecialchars($result->profile_image_url) ?>" width="48">
		<?php echo htmlspecialchars($result->from_user) ?></a>:
		<?php echo Twitter::clickable($result->text) ?>
		<small>at <?php echo date("j.n.Y H:i", strtotime($result->created_at)) ?></small>
	</li>
<?php endforeach ?>
</ul>
