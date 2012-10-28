<?php

include('Farser.php');

$farser = new Farser("template.html");

echo $farser->parse();

// $farser->dumpLog();
