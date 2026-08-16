<?php declare(strict_types=1);

use Nette\Schema\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('placeholders are substituted', function () {
	$message = new Message('The %label% %path% expects to be %expected%.', 'code', ['a', 'b'], ['expected' => 'int']);
	Assert::same("The item 'a\u{a0}›\u{a0}b' expects to be int.", $message->toString());
});


test('null variable removes placeholder including preceding space', function () {
	$message = new Message('The item %path% is missing.', 'code', []);
	Assert::same('The item is missing.', $message->toString());
});


test('unknown placeholder is left untouched', function () {
	$message = new Message('Value must be 100%valid%.', 'code', []);
	Assert::same('Value must be 100%valid%.', $message->toString());
});
