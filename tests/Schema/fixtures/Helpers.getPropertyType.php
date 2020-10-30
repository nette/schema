<?php

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
