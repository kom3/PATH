<?php

require '../src/PATH.php';

$app = new PATH\API();
$app->login('email', 'password');
$app->likeAllTimeline(5, "love", function($data){
    echo "Sukses Likes\n";
});
