<?php

/**
 * Test: Nette\DI\Helpers::getPropertyType
 */

declare(strict_types=1);

namespace NS
{
	use Test\B;

	class A
	{
		public $noType;

		/** @var B noise */
		public $annotationClassType;

		/** @var B|null|string */
		public $annotationUnionType;

		/** @var String */
		public $annotationNativeType;

		/** @var self */
		public $annotationSelfType;

		/** @var static */
		public $annotationStaticType;

		/** @var ?B */
		public $annotationNullable;
	}
}

namespace
{
	use Nette\Schema\Helpers;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::null(Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'noType')));

	Assert::same('Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationClassType')));

	Assert::same('Test\B|null|string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationUnionType')));

	Assert::same('string', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationNativeType')));

	Assert::same('NS\A', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationSelfType')));

	Assert::same('?Test\B', Helpers::getPropertyType(new \ReflectionProperty(NS\A::class, 'annotationNullable')));
}
