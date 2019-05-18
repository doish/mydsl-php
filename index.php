<?php

require 'vendor/autoload.php';
require 'dsl-core.php';

use Symfony\Component\Yaml\Yaml;

$filename = 'foo.yml';
$content = file_get_contents($filename);
$yamlParsedValue = Yaml::parse($content);
$container = array();
$result = (new Argument($yamlParsedValue))->evaluate($container);

echo "evaluation end\n";
echo sprintf("argument: %s\n", json_encode($yamlParsedValue));
echo sprintf("result: %s\n", json_encode($result->value()));
echo sprintf("container: %s\n", json_encode($container));
