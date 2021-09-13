<?php

/**
 * @phpversion 7.4
 */

declare(strict_types=1);

use Nette\Schema\Helpers;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/fixtures/Helpers.getPropertyType.74.php';


Assert::null(Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'noType')));

Assert::same('Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'classType')));

Assert::same('string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'nativeType')));

Assert::same('NS\A', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'selfType')));

Assert::same('?Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'nullableClassType')));

Assert::same('?string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'nullableNativeType')));

Assert::same('?NS\A', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'nullableSelfType')));

Assert::same('Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationClassType')));

Assert::same('Test\B|null|string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationUnionType')));

Assert::same('string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationNativeType')));

Assert::same('NS\A', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationSelfType')));

Assert::same('?Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationNullable')));
