<?php

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . '/src')
	->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
	->setFinder($finder)
	->setIndent("\t")
	->setRules([
		// Base style: PSR-12. Stable and version-agnostic — the safe core.
		'@PSR12' => true,

		// Modernise to the project's minimum PHP (composer.json `php: ^8.4`).
		// Migrations are additive, so future PHP versions (8.5+) keep working;
		// bump this when the minimum PHP requirement bumps.
		'@PHP8x4Migration' => true,

		// Keep the opening `<?php` flush against the namespace declaration.
		'blank_line_after_opening_tag' => false,

		// No one-line function bodies — even empty ones span lines. Arrow
		// functions are unaffected (they're `fn() => expr`, not brace-bodies).
		'single_line_empty_body' => false,

		// Arguments live on the same line as the method name. Property promotion
		// is the one case they're written multi-line (each arg on its own line),
		// and this rule respects that — multi-line stays multi-line, short
		// single-line signatures stay single-line.
		'method_argument_space' => [
			'on_multiline' => 'ensure_fully_multiline',
			'keep_multiple_spaces_after_comma' => false,
		],
	]);
