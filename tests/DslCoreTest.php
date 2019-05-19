<?php

require '../vendor/autoload.php';
require '../dsl-core.php';

use Symfony\Component\Yaml\Yaml;
use PHPUnit\Framework\TestCase;

class DslCoreTest extends TestCase {

  private function getYamlParsedValue($funcName) {
    $fileName = 'yamls/' . $funcName . '.yml';
    $content = file_get_contents($fileName);
    $yamlParsedValue = Yaml::parse($content);
    return $yamlParsedValue;
  }

  public function testGetAndSet() {
    $parsedValue = $this->getYamlParsedValue(__FUNCTION__);
    $container = array();
    (new Argument($parsedValue["prepare"]))->evaluate($container);
    $testsResult = (new Argument($parsedValue["tests"]))->evaluate($container);
    $this->assertFalse($testsResult->hasError());
    foreach ($testsResult->value() as $testcase) {
      $this->assertSame($testcase[0], $testcase[1]);
    }
  }

}
