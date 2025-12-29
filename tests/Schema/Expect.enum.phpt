<?php

declare(strict_types=1);

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


enum Suit
{
	case Clubs;
	case Diamonds;
	case Hearts;
	case Spades;
}

test('unit enum as standalone type', function () {
	$schema = Expect::type(Suit::class);

	Assert::same(Suit::Clubs, (new Processor)->process($schema, Suit::Clubs));
	Assert::same(Suit::Hearts, (new Processor)->process($schema, Suit::Hearts));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'Clubs');
	}, ['The item expects to be Suit, \'Clubs\' given.']);
});
