<?php

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . '/tests')
	->in(__DIR__ . '/src');

return (new PhpCsFixer\Config())
	->setFinder($finder)
	->setIndent("\t")
	->setRules([
		'@PSR12' => true,
		'@PHP80Migration' => true,
		'blank_line_after_opening_tag' => false
	]);
