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


// create temporary directory
(function () {
	define('TEMP_DIR', __DIR__ . '/tmp/' . getmypid());

	// garbage collector
	$GLOBALS['\\lock'] = $lock = fopen(__DIR__ . '/lock', 'w');
	if (rand(0, 100)) {
		flock($lock, LOCK_SH);
		@mkdir(dirname(TEMP_DIR));
	} elseif (flock($lock, LOCK_EX)) {
		Tester\Helpers::purge(dirname(TEMP_DIR));
	}

	@mkdir(TEMP_DIR);
})();


function test(\Closure $function): void
{
	$function();
}


function checkValidationErrors(\Closure $function, array $messages): void
{
	$e = Assert::exception($function, Nette\Schema\ValidationException::class);
	Assert::same($messages, $e->getMessages());
	Assert::same($messages[0], $e->getMessage());
}
