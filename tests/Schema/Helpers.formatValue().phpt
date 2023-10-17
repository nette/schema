<?php

declare(strict_types=1);

use Nette\Schema\Helpers;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


Assert::same('null', Helpers::formatValue(null));
Assert::same('1', Helpers::formatValue(1));
Assert::same('1.0', Helpers::formatValue(1.0));
Assert::same('true', Helpers::formatValue(true));
Assert::same('false', Helpers::formatValue(false));
Assert::same("'hello'", Helpers::formatValue('hello'));
Assert::same("'nettenettene...'", Helpers::formatValue(str_repeat('nette', 100)));
Assert::same('array', Helpers::formatValue([1, 2]));
Assert::same('object stdClass', Helpers::formatValue(new stdClass));
Assert::same('dynamic', Helpers::formatValue(new class implements Nette\Schema\DynamicParameter {
}));
