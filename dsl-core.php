<?php

const dollerReplacePattern = '/^(\$\.?)/';

function is_hash(&$array) {
  $i = 0;
  foreach ($array as $k => $v) {
    if ($k !== $i++) {
      return true;
    }
  }
  return false;
}

$dslFunctions["get"] = function($a, $b) {
  echo $a;
  echo $b;
  return new Result(111, 222);
};

class Argument {

  private $rawArg;

  public function __construct($rawArg) {
    global $dslFunctions;
    $this->dslFunctions = $dslFunctions;
    if (gettype($rawArg) === 'string' && $rawArg !== '$') {
      $this->rawArg = preg_replace(dollerReplacePattern, '$.', $rawArg);
    } else {
      $this->rawArg = $rawArg;
    }
  }

  public function rawArg() {
    return $this->rawArg;
  }

  private function stringEvalLogic($container) {
    if ($this->rawArg === '$') {
      return [$container, null];
    } elseif (false /* calcPattern.match */) {
      
    } elseif (strpos($this->rawArg, '$') === 0) {
      // TODO
      return $this->dslFunctions["get"](111, 222);
    } else {
      return [$this->rawArg, null];
    }
  }

  private function arrayEvalLogic($container) {
    $result = array();
    foreach ($this->rawArg as $value) {
      $arg = new Argument($value);
      list($r, $error ) = $arg->evaluate($container);
      if ($error != null) {
        return [null, $error];
      } else {
        array_push($result, $r);
      }
    }
    return [$result, null];
  }

  private function hashEvalLogic($container) {
    $size = count($this->rawArg);
    if ($size === 0) {
      return [array(), null];
    } elseif ($size === 1) {
      $firstKey = array_keys($this->rawArg)[0];
      if (array_key_exists($firstKey, $dslFunctions)) {
        // TODO
      } elseif (strpos($firstKey, '$') === 0) {
        return $this->dslFunctions["set"](
                        $container, new Argument($firstKey), new Argument($this->rawArg[$firstKey]));
      } else {
        // TBD
        return [$this->rawArg, null];
      }
    } else {
      $evaluatedDict = array();
      foreach ($this->rawArg as $key => $value) {
        $arg = new Argument($value);
        list($evaluated, $error) = $arg->evaluate($container);
        if ($error != null) {
          return [null, $error];
        } else {
          $evaluatedDict[$key] = $evaluated;
        }
      }
      return [$evaluatedDict, null];
    }
  }

  public function evaluate($container) {
    $type = gettype($this->rawArg);
    if ($type === 'string') {
      return $this->stringEvalLogic($container);
    } elseif (is_array($this->rawArg)) {
      if (!is_hash($this->rawArg)) {
        return $this->arrayEvalLogic($container);
      } else {
        return $this->hashEvalLogic($container);
      }
    } else {
      return [$this->rawArg, null];
    }
  }

}
