<?php declare(strict_types=1);

require_once __DIR__ . '/OAuth.php';
require_once __DIR__ . '/Twitter.php';

class_alias(DG\Twitter\Twitter::class, 'Twitter');
class_alias(DG\Twitter\Exception::class, 'TwitterException');
