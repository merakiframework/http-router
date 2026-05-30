<?php
declare(strict_types=1);

/**
 * Extract a single version's section from CHANGELOG.md.
 *
 * Usage:  php scripts/extract-changelog.php <tag>
 *
 * `<tag>` may be the git tag form (e.g. `v1.0.0`) — the leading `v` is stripped
 * before matching. Prints the section body (without the `## [version]` header)
 * to stdout. Exits 1 with a message on stderr if the section isn't found, so
 * the calling workflow fails loudly when CHANGELOG.md is out of sync.
 *
 * Used by the Release workflow (.github/workflows/release.yml) to populate the
 * GitHub Release body from the matching CHANGELOG section.
 */

$tag = $argv[1] ?? null;

if ($tag === null) {
	fwrite(STDERR, "usage: php scripts/extract-changelog.php <tag>\n");
	exit(2);
}

$version = ltrim($tag, 'v');
$path = __DIR__ . '/../CHANGELOG.md';

if (!is_file($path)) {
	fwrite(STDERR, "CHANGELOG.md not found at {$path}\n");
	exit(1);
}

$content = file_get_contents($path);

// Match `## [<version>]<...>\n` and capture everything up to the next
// `## [` or the end of the file. Multiline (`m`) + dotall (`s`).
$pattern = '/^##\s+\[' . preg_quote($version, '/') . '\][^\n]*\n(.*?)(?=^##\s+\[|\z)/sm';

if (!preg_match($pattern, $content, $matches)) {
	fwrite(STDERR, "no CHANGELOG.md section for version {$version} (looked for `## [{$version}]`)\n");
	exit(1);
}

echo trim($matches[1]) . "\n";
