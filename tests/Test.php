<?php
include "vendor/autoload.php";

use Sohris\Thread\Thread;


$thread = new Thread;
$thread->child(function (){
    echo 13;

});

$thread->run();