<?php

declare(strict_types=1);

$name = "Lucas";
$age = 19;
$active = true;
$balance = 1000.00;
$nickname = null;

var_dump($name);
var_dump($age);
var_dump($active);
var_dump($balance);
var_dump($nickname);

echo "User: {$name}, {$age} years-old.\n";

var_dump(0 == "0");
var_dump(0 === "0");

$api_name = "taskforge api";

echo ucwords($api_name);
