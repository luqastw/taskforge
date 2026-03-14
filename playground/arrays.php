<?php

$tasks = [
  ["title" => "task01", "done" => true, "hours" => 8],
  ["title" => "task02", "done" => false, "hours" => 2],
  ["title" => "task03", "done" => false, "hours" => 12],
];

$notdone = array_filter($tasks, fn($task) => $task["done"] === false);
var_dump($notdone);

$pendenttitles = array_map(fn($task) => $task["title"], $notdone);
var_dump($pendenttitles);

foreach ($pendenttitles as $title) {
    echo "- " . $title . " Setup.\n";
}

$totalhours = array_reduce(array_map(fn($task) => $task["hours"], $notdone), fn($carry, $task) => $carry + $task, 0);
var_dump($totalhours);
