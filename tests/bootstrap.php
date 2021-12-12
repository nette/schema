<?php

declare(strict_types=1);

use Tester\Assert;

// The Nette Tester command-line runner can be
// invoked through the command: ../vendor/bin/tester .

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}


// configure environment
Tester\Environment::setup();
date_default_timezone_set('Europe/Prague');


function test(string $title, Closure $function): void
{
	$function();
}


function checkValidationErrors(Closure $function, array $messages): Nette\Schema\ValidationException
{
	$e = Assert::exception($function, Nette\Schema\ValidationException::class);
	Assert::same($messages, $e->getMessages());
	Assert::same($messages[0], $e->getMessage());
	return $e;
}
