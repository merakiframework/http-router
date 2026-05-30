<?php
declare(strict_types=1);

/**
 * Release driver. Invoked via `composer release[:patch|:minor|:major|:alpha|:first]`.
 *
 *   1. Refuse to start with a dirty working tree.
 *   2. CI gate (composer ci -> validate + analyse + test).
 *   3. Format pass — committed as a separate `style:` commit if cs-fixer changed anything.
 *   4. Generate changelog + bump version + commit + tag via marcocesarato/php-conventional-changelog.
 *
 * The maintainer pushes after:  git push --follow-tags
 */

const RESET = "\033[0m";
const CYAN  = "\033[1;36m";
const GREEN = "\033[1;32m";
const RED   = "\033[1;31m";
const DIM   = "\033[2m";

$bump = $argv[1] ?? 'auto';

function step(string $title): void
{
	echo "\n" . CYAN . "==> {$title}" . RESET . "\n";
}

function run(string $command): int
{
	echo DIM . "  $ {$command}" . RESET . "\n";
	passthru($command, $exit);
	return $exit;
}

function fail(string $message): never
{
	fwrite(STDERR, "\n" . RED . "release aborted:" . RESET . " {$message}\n");
	exit(1);
}

// 0. Pre-flight: refuse to release with a dirty tree.
exec('git status --porcelain', $dirty, $exit);
if ($exit !== 0) {
	fail('not a git repository (or git is unavailable)');
}
if (!empty($dirty)) {
	fail("working tree has uncommitted changes — commit or stash them first:\n  " . implode("\n  ", $dirty));
}

// 1. CI gate.
step('CI gate (validate + analyse + test)');
if (run('composer ci') !== 0) {
	fail('CI failed — fix it before releasing');
}

// 2. Format. If cs-fixer changed anything, commit it as a separate style commit
//    BEFORE the release commit, so the release commit stays purely a "release"
//    commit (changelog + version bump + tag).
step('Format pass (separate commit if anything changes)');
if (run('composer format') !== 0) {
	fail('format step failed');
}
exec('git status --porcelain', $afterFormat);
if (!empty($afterFormat)) {
	echo "  cs-fixer made changes; committing them separately...\n";
	if (run('git add -A') !== 0) {
		fail('git add (post-format) failed');
	}
	if (run('git commit -m "style: format code (pre-release)"') !== 0) {
		fail('format commit failed');
	}
} else {
	echo "  (nothing to format)\n";
}

// 3. Changelog + version bump + tag + commit, via marcocesarato.
step('Generate changelog, bump version, tag');
$bumpFlag = match ($bump) {
	'auto'          => '',                   // tool default = patch
	'patch'         => '--patch',
	'minor'         => '--minor',
	'major'         => '--major',
	'alpha'         => '--alpha',
	'first'         => '--first-release',
	default         => fail("unknown bump '{$bump}'; expected: auto, patch, minor, major, alpha, first"),
};
$command = trim("vendor/bin/conventional-changelog --commit --no-interaction {$bumpFlag}");
if (run($command) !== 0) {
	fail('changelog/tag step failed — the format commit (if any) is still in place');
}

// Done.
echo "\n" . GREEN . "OK — release prepared." . RESET . " Push when ready:\n";
echo "    git push --follow-tags\n\n";
