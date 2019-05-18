<?php

const dollerReplacePattern = '/^(\$\.?)/';
const firstValuePattern = '/^([^\[ \]\.]+)\.?(.+)$/';
const nextKeyPattern = '/^(\[([^\[\]]+)\]|([^\[\] \.]+))\.?(.*)$/';

function is_hash(&$array) {
  $i = 0;
  foreach ($array as $k => $v) {
    if ($k !== $i++) {
      return true;
    }
  }
  return false;
}

function is_list(&$array) {
  return is_array($array) && !is_hash($array);
}

$dslFunctions["get"] = function(&$container, ...$args) {
  $firstArg = $args[0];
  $args = array_slice($args, 1);
  $lastKeyValueResult = &getLastKeyValue($container, $firstArg, $container);
  if ($lastKeyValueResult->hasError()) {
    return $lastKeyValueResult;
  }
  $key = &$lastKeyValueResult->value()[0];
  $parentValue = &$lastKeyValueResult->value()[1];
  $defaultValue = null;
  if (count($args) !== 0) {
// TODO 
  }
  if ($parentValue !== null) {
    if ($key === null) {
      $result = new Result([&$parentValue, null]);
      return $result;
    } else {
      if ($key === "") {
        $cursor = $parentValue;
      } else {
        $keyType = gettype($key);
        if ($keyType === 'string') {
          if (is_numeric($key)) {
            $cursor = $parentValue[intval($key)];
          } else {
            $cursor = $parentValue[$key];
          }
        } elseif ($keyType === 'integer') {
          $cursor = $parentValue[$key];
        }
      }
      while (count($args) > 0) {
        $shiftArg = $args[0];
        $args = array_slice($args, 1);
        $shiftArgResult = &$shiftArg->evaluate($container);
        if ($shiftArgResult->hasError()) {
          return $shiftArgResult;
        }
        $key = &$shiftArgResult->value();
        if (is_numeric($key)) {
          $cursor = $cursor[intval($key)];
        } else {
          $cursor = $cursor[$key];
        }
      }
      if ($cursor === null && count($args) == 0) {
        return new Result([&$defaultValue, null]);
      }
      return new Result([&$cursor, null]);
    }
  } else {
    return new Result([null, null]);
  }
};

$dslFunctions["set"] = function(&$container, ...$args) {
  $valueResult = &$args[1]->evaluate($container);
  if ($valueResult->hasError()) {
    return $valueResult;
  }
  $lastKeyValueResult = &getLastKeyValue($container, $args[0], $container);
  if ($lastKeyValueResult->hasError()) {
    return $lastKeyValueResult;
  }
  $key = &$lastKeyValueResult->value()[0];
  $parentValue = &$lastKeyValueResult->value()[1];
  if ($parentValue !== null && $key !== null && $key !== "") {
    $keyType = gettype($key);
    if ($keyType === 'string') {
      if (is_numeric($key)) {
        $parentValue[intval($key)] = &$valueResult->value();
      } else {
        $parentValue[$key] = &$valueResult->value();
      }
    } elseif ($keyType === 'integer') {
      $parentValue[$key] = &$valueResult->value();
    } else {
// TBD 
    }
  }
  return new Result([null, null]);
};

$dslFunctions["print"] = function(&$container, ...$args) {
  foreach ($args as $arg) {
    $result = $arg->evaluate($container);
    if ($result->hasError()) {
      return $result;
    } else {
      $value = $result->value();
      $valueType = gettype($value);
      if ($valueType === 'object' || $valueType === 'array') {
        var_dump($value);
      } else {
        echo $value . "\n";
      }
    }
  }
  return new Result([null, null]);
};

$dslFunctions["function"] = function(&$container, ...$args) {
  $funcContainer = $container;
  $fixedArguments = array();
  $argumentNames = $args[0]->rawArg();
  $process = $args[1];
  if (count($args) > 2) {
// TODO
//    for fixedKey in args[2].rawArg():
//    evaluated, err = Argument("$." + fixedKey).evaluate(container)
//    if err != None:
//        return None, err
//    fixedArguments[fixedKey] = evaluated
  }

// https://www.php.net/manual/ja/functions.anonymous.php
// クロージャは、変数を親のスコープから引き継ぐことができます。 
//  引き継ぐ変数は、use で渡さなければなりません。
  $func = function(...$_args) use ($funcContainer, $argumentNames, $process) {
    foreach ($argumentNames as $i => $argumentName) {
      $funcContainer[$argumentName] = $_args[$i];
// TODO
//  _funcContainer["this"] = container
// for k, v in fixedArguments.items():
//      _funcContainer[k] = v
    }
    $processResult = $process->evaluate($funcContainer);
    if ($processResult->hasError()) {
      return $processResult;
    }
    return $processResult;
  };
  $result = new Result([&$func, null]);
  return $result;
};

$dslFunctions["do"] = function(&$container, ...$args) {
  $firstArg = $args[0];
  $args = array_slice($args, 1);
  $lastKeyValueResult = &getLastKeyValue($container, $firstArg, $container);
  if ($lastKeyValueResult->hasError()) {
    return $lastKeyValueResult;
  }
  $key = &$lastKeyValueResult->value()[0];
  $parentValue = &$lastKeyValueResult->value()[1];
  if ($parentValue === null || $key === null) {
    return new Result([null, null]);
  }
  $cursor = null;
  if ($key === "") {
    $cursor = $parentValue;
  } else {
    $propertyGetResult = propertyGet($parentValue, $key);
    $cursor = $propertyGetResult[0];
  }
  while (!is_callable($cursor) && count($args) > 0) {
    $nextArg = args[0];
    $args = array_slice($args, 1);
    $result = $nextArg->evaluate($container);
    if ($result->hasError()) {
      return $result;
    }
    $key = $result[0];
    $propertyGetResult = propertyGet($cursor, $key);
    $cursor = $propertyGetResult[0];
    if ($cursor === null) {
      break;
    }
  }
  if (is_callable($cursor)) {
    // TODO
    //   evaluated, err = evaluateAll(args, container)
    //    if err != None:
    //        return None, err
    //    if len(evaluated) == 1 and isinstance(evaluated[0], dict):
    //        return cursor(**evaluated[0]), None
    //    else:
    //        return cursor(*evaluated), None
    $callableResult = $cursor();
    return new Result([$callableResult, null]);
  } else {
    return new Result([null, null]);
  }
};

function &getLastKeyValue(&$container, $arg, &$root) {
  $rawArg = $arg->rawArg();
  $rawArgType = gettype($rawArg);
  if ($rawArgType === 'string') {
    if ($rawArg === '$') {
      $result = new Result([["", &$root], null]);
      return $result;
    } elseif (false /* $rawArg in $dslAvailableFunctions */) {
// return ["", $dslAvailableFunctions[$rawArg]], null; 
    } elseif (strpos($rawArg, '.') == false && strpos($rawArg, '[') == false) {
      $result = new Result([["", $rawArg], null]);
      return $result;
    } else {
      $cursor = $container;
      $remainStr = $rawArg;
      preg_match(firstValuePattern, $remainStr, $firstValueMatch);
      $result = &getLastKeyValue($cursor, new Argument($firstValueMatch[1]), $root);
      if ($result->hasError()) {
        return $result;
      }
      $firstValue = &$result->value()[1];
      if ($firstValue !== null) {
        $cursor = &$firstValue;
        $remainStr = $firstValueMatch[2];
      } else {
        $result = new Result([[null, $rawArg], null]);
        return $result;
      }
      while (true) {
        $match = preg_match(nextKeyPattern, $remainStr, $nextKeyMatch);
        if ($match !== 0) {
          $arrayKeyStr = $nextKeyMatch[2];
          $periodKeyStr = $nextKeyMatch[3];
          $remain = $nextKeyMatch[4];
          if ($periodKeyStr !== '') {
            $nextKeyResult = array();
            if ($arrayKeyStr !== '') {

              $result = &getLastKeyValue($root, new Argument($arrayKeyStr), $root);
              if ($result->hasError()) {
                return $result;
              }
              $nextKeyResult = &$result->value();
            } else {
              $result = getLastKeyValue($root, new Argument($periodKeyStr), $root);
              if ($result->hasError()) {
                return $result;
              }
              $nextKeyResult = &$result->value();
            }
            if ($nextKeyResult[0] == "") {
              $nextKey = $nextKeyResult[1];
            } elseif ($nextKeyResult[0] === null) {
              $nextKey = null;
            } else {
              $propertyGetResult = &propertyGet($nextKeyResult[1], $nextKeyResult[0]);
              $nextKey = $propertyGetResult[0];
            }
          } else {
            $newArg = new Argument($arrayKeyStr);
            $result = &$newArg->evaluate($container);
            if ($result->hasError()) {
              return result;
            } else {
              $nextKey = &$result->value();
            }
          }
          if ($remain === "") {
            $result = new Result([[$nextKey, &$cursor], null]);
            return $result;
          } else {
            $propertyGetResult = &propertyGet($cursor, $nextKey);
            if ($propertyGetResult[1] === null) {
              $cursor = &$propertyGetResult[0];
            } else {
              $result = new Result([null, $error]);
              return $result;
            }
            $remainStr = $remain;
          }
        } else {
          $result = new Result([[null, null], null]);
          return $result;
        }
      }
    }
  } else {
    $result = &$arg->evaluate($container);
    if ($result->hasError()) {
      return $result;
    } else {
      $result = new Result([["", $result->value()], null]);
      return $result;
    }
  }
}

function &propertyGet(&$parent, $key) {
  $keyType = gettype($key);
  $parentType = gettype($parent);
  if ($keyType === 'string') {
    if (is_numeric($keyType)) {
      $numKey = intval($keyType);
      $result = [&$parent[$numKey], null];
      return $result;
    } else {
      if ($parentType === 'object' && property_exists($parent, $key)) {
        $result = [&$parent[$key], null];
        return $result;
      } elseif ($parentType === 'array') {
        $result = [&$parent[$key], null];
        return $result;
      } else {
        $result = [null, "error: key is invalid1"];
        return $result;
      }
    }
  } elseif ($keyType === 'integer') {
    return [&$parent[$key], null];
  } else {
    return [null, "error: key is invalid2"];
  }
}

class Result {

  public function __construct($args) {
    $this->args = &$args;
  }

  public function &value() {
    return $this->args[0];
  }

  public function hasError() {
    return $this->args[1] !== null;
  }

}

class Argument {

  private $rawArg;

  public function __construct($rawArg) {
    global $dslFunctions;
    $this->dslFunctions = &$dslFunctions;
    if (gettype($rawArg) === 'string' && $rawArg !== '$') {
      $this->rawArg = preg_replace(dollerReplacePattern, '$.', $rawArg);
    } else {
      $this->rawArg = $rawArg;
    }
    return $this;
  }

  public function rawArg() {
    return $this->rawArg;
  }

  private function &stringEvalLogic(&$container) {
    if ($this->rawArg === '$') {
      $result = new Result([&$container, null]);
      return $result;
    } elseif (false /* calcPattern.match */) {
// TODO 
      return [];
    } elseif (strpos($this->rawArg, '$') === 0) {
      $result = $this->dslFunctions["get"]($container, new Argument($this->rawArg));
      return $result;
    } else {
      $result = new Result([$this->rawArg, null]);
      return $result;
    }
  }

  private function &arrayEvalLogic(&$container) {
    $arrayResult = array();
    $arrayCount = 0;
    foreach ($this->rawArg as $value) {
      $arg = new Argument($value);
      $result = &$arg->evaluate($container);
      if ($result->hasError()) {
        return $result;
      } else {
        $arrayResult[$arrayCount++] = &$result->value();
      }
    }
    $result = new Result([&$arrayResult, null]);
    return $result;
  }

  private function &hashEvalLogic(&$container) {
    $size = count($this->rawArg);
    if ($size === 0) {
      $array = arary();
      $result = new Result([&$array, null]);
      return $result;
    } elseif ($size === 1) {
      $firstKey = array_keys($this->rawArg)[0];
      if (array_key_exists($firstKey, $this->dslFunctions)) {
        $value = $this->rawArg[$firstKey];
        $wrappedValue = array();
        if (is_list($value)) {
          foreach ($value as $v) {
            $newArg = new Argument($v);
            array_push($wrappedValue, $newArg);
          }
        } else {
          array_push($wrappedValue, new Argument($value));
        }
        $result = $this->dslFunctions[$firstKey]($container, ...$wrappedValue);
        return $result;
      } elseif (strpos($firstKey, '$') === 0) {
        $result = $this->dslFunctions["set"](
                $container, new Argument($firstKey), new Argument($this->rawArg[$firstKey]));
        return $result;
      } else {
// TBD
        $result = new Result([$this->rawArg, null]);
        return $result;
      }
    } else {
      $evaluatedDict = array();
      foreach ($this->rawArg as $key => $value) {
        $arg = new Argument($value);
        $result = &$arg->evaluate($container);
        if ($result->hasError()) {
          return $result;
        } else {
          $evaluatedDict[$key] = &$result->value();
        }
      }
      $result = new Result([&$evaluatedDict, null]);
      return $result;
    }
  }

  public function &evaluate(&$container = array()) {
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
      $result = new Result([$this->rawArg, null]);
      return $result;
    }
  }

}
