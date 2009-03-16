<?php

require_once 'twitter.class.php';


$twitter = new Twitter('pokusnyucet2', '123456');
$status = $twitter->send('I am fine');

echo $status ? 'OK' : 'ERROR';
