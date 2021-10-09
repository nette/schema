<?php

namespace NS
{
	use Test\B;

	class A
	{
		public $noType;
		public B $classType;
		public string $nativeType;
		public self $selfType;
		public ?B $nullableClassType;
		public ?string $nullableNativeType;
		public ?self $nullableSelfType;

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
