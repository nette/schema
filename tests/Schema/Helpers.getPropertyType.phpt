<?php

declare(strict_types=1);

use Nette\Schema\Helpers;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/fixtures/Helpers.getPropertyType.php';

if (!class_exists(Nette\Utils\Type::class)) {
	Tester\Environment::skip('Expect::from() requires nette/utils 3.x');
}

Assert::null(Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'noType')));

Assert::same('Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationClassType')));

Assert::same('Test\B|null|string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationUnionType')));

Assert::same('string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationNativeType')));

Assert::same('NS\A', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationSelfType')));

Assert::same('?Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationNullable')));
