<?php declare(strict_types=1);

use DG\X\Client;

require_once __DIR__ . '/../vendor/autoload.php';

// ENTER HERE YOUR CREDENTIALS (see readme.md)
$x = new Client($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

// See https://developer.x.com/en/docs/x-api
$result = $x->request('users/me', 'GET', ['user.fields' => 'description,profile_image_url']);

?>
<!doctype html>
<meta charset="utf-8">
<title>X custom request demo</title>

<p>
	<img src="<?php echo htmlspecialchars($result->data->profile_image_url ?? '') ?>">
	<?php echo htmlspecialchars($result->data->name ?? '') ?>:
	<?php echo htmlspecialchars($result->data->description ?? '') ?>
</p>
