<?php

require 'vendor/autoload.php';
require 'dsl-core.php';

use Symfony\Component\Yaml\Yaml;

$arg = new Argument(['$', '$', '$', '$']);
list($result) = $arg->evaluate(111);
var_dump($result);
$filename = 'foo.yml';
$content = file_get_contents($filename);
$value = json_encode(Yaml::parse($content));
echo $value;
