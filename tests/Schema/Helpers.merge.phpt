<?php

/**
 * Test: Nette\Schema\Helpers::merge()
 */

declare(strict_types=1);

use Nette\Schema\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$obj = new stdClass;
$arr1 = ['a' => 'b', 'x'];
$arr2 = ['c' => 'd', 'y'];
$arr3 = [Helpers::PreventMerging => true, 'c' => 'd', 'y'];


Assert::same(null, Helpers::merge(null, null));
Assert::same(null, Helpers::merge(null, 231));
Assert::same(null, Helpers::merge(null, $obj));
Assert::same([], Helpers::merge(null, []));
Assert::same($arr1, Helpers::merge(null, $arr1));
Assert::same(231, Helpers::merge(231, null));
Assert::same(231, Helpers::merge(231, 231));
Assert::same(231, Helpers::merge(231, $obj));
Assert::same(231, Helpers::merge(231, []));
Assert::same(231, Helpers::merge(231, $arr1));
Assert::same($obj, Helpers::merge($obj, null));
Assert::same($obj, Helpers::merge($obj, 231));
Assert::same($obj, Helpers::merge($obj, $obj));
Assert::same($obj, Helpers::merge($obj, []));
Assert::same($obj, Helpers::merge($obj, $arr1));
Assert::same([], Helpers::merge([], null));
Assert::same([], Helpers::merge([], 231));
Assert::same([], Helpers::merge([], $obj));
Assert::same([], Helpers::merge([], []));
Assert::same($arr1, Helpers::merge([], $arr1));
Assert::same($arr2, Helpers::merge($arr2, null));
Assert::same($arr2, Helpers::merge($arr2, 231));
Assert::same($arr2, Helpers::merge($arr2, $obj));
Assert::same($arr2, Helpers::merge($arr2, []));
Assert::same(['a' => 'b', 'x', 'c' => 'd', 'y'], Helpers::merge($arr2, $arr1));
Assert::same(['c' => 'd', 'y'], Helpers::merge($arr3, $arr1));
Assert::same(['inner' => ['c' => 'd', 'y']], Helpers::merge(['inner' => $arr3], ['inner' => $arr1]));
Assert::same([['a' => 'b', 'x'], [Helpers::PreventMerging => true, 'c' => 'd', 'y']], Helpers::merge([$arr3], [$arr1]));
Assert::same([Helpers::PreventMerging => true, 'c' => 'd', 'y', 'a' => 'b', 'x'], Helpers::merge($arr1, $arr3));
Assert::same([20 => 'b', 10 => 'a'], Helpers::merge([10 => 'a'], [20 => 'b']));
Assert::same(['b', 'a'], Helpers::merge(['a'], ['b']));
