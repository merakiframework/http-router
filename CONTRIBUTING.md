# Contributing

## Setting up a development environment

This library provides pre-configured environments for [devbox](https://www.jetpack.io/devbox/) or [Dev Containers](https://code.visualstudio.com/docs/devcontainers/containers). These tools can be used on Windows, Linux, or Mac. Visit the provided links for instructions on how to install and use them.

## Workflow for pull request

Use the following steps when you want to make contributions.

1. Fork the repository
2. Clone the forked repository into a local one
3. Configure this repository as a remote (usually called upstream)
4. Create a new branch in your local clone (one branch per issue/feature/fix/etc.)
5. Make your changes, write your tests, and make sure their is documentation (remember to keep your commits short and simple)
6. Push your changes to your fork
7. Create a pull request from the pushed branch to our repository

Any pull requests made from the main/master branch of your fork will not be accepted. Always create a pull request from a branch.

## Testing

This library uses [PHPUnit](https://phpunit.readthedocs.io/en/9.5/) for writing tests and [Psalm](https://psalm.dev/) for static analysis. You can run these tools using any of the following scripts:

`composer run-script develop` or `composer develop`

This script starts a test server, in the foreground, and will automatically run the linter, tests, and formatting tools as you make changes. This is really the only script you need to use when contributing to this library. Press `Ctrl + C` to shutdown the development server.

`composer run-script lint` or `composer lint`

Runs Psalm which is a static analysis tool that is used to check the syntax of PHP code. It is more advanced/configureable then PHP's own linting/syntax-checking tool (`php -l`). This is a git pre-commit hook and must pass before any changes can be committed.

`composer run-script test` or `composer test`

Run all PHPUnit tests in the /tests folder and display any errors. This is also a git commit hook and all tests must pass before changes are committed. You rarely have to call this script. Use the `composer run-script develop` or `composer develop` script instead for development.

## Coding style

Coding style is more or less compliant with [PSR12 Coding Style Guidelines]() with a few minor exceptions:

- The type of line ending doesn't really matter (Git will transform line endings as it sees fit) and files where the line endings matter will be specified in the .gitattributes file.

- Tabs are used for indentation, not spaces. This is what the tab character was designed for.

- The `declare(strict_types=1)` statement must have a semicolon (";") at the end.

- When breaking expressions over multiple lines use the following syntax instead of what PSR recommends:

```php
if ($var === $this->handle()
	&& count($someOtherVar) > 3
) {
	// do stuff...
}
```

There are a few other minor changes versus PSR12, but you do not need to really worry about this as their is a pre-commit hook that will format the code according to Meraki's coding style. You can run the formatter with the following script:

`composer run-script format` or `composer format`

This will run [PHP Coding Standards Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer). This will automatically fix any code style issues found. There is a git hook that will automatically format the code using the rules provided in `.php-cs-fixer.dist.php` before a commit is made, so you rarely have to call this script unless you would like to develop using your own coding standard (see next paragraph).

You can create a `.php-cs-fixer.php` file in the root directory of this project and run the above command to format all code to your own style preferences. This will allow you to develop using your own preferred style. (This file is already ignored by git.)

## Releases

Releases are managed by contributors with write-access to the repository. For reference, the following scripts are related to this process.

### `composer run-script changelog` or `composer changelog`

Run this command to see a list of all changes commited since the last release. This command will tell you whether your changes are considered a patch, minor, or major according to semver requirements.

### `composer run-script release` or `composer release`

Generate a new release based off all commits made since last release and commit it to the repository. This is a manual process and no git hook exists for this, yet.

### `composer run-script release:patch` or `composer release:patch`
### `composer run-script release:minor` or `composer release:minor`
### `composer run-script release:major` or `composer release:major`

Same as above, but forces a specific release type of patch, minor, or major.

## Commit messages

Meraki uses the [Conventional Commits](https://www.conventionalcommits.org) specification for writing commit messages, but with a heavy emphasis on avoiding the use of the `<optional scope>`. For most messages, follow this format: `<type>: <description>`, where `<type>` is one of fix, feat, build, chore, ci, docs, style, refactor, test, or perf. A commit `<description>` should use the imperative mood and should describe what changed. Finally, if there are joining words (and, but, etc.) in your commit, that means you are trying to commit too many changes at once.

### Good examples

Files changed: *composer.json*, *composer.lock*
Commit message: `build: add composer support`

Files changed: *README.md*
commit message: `docs: fix spelling mistake in usage section`

Files changed: *src/*
commit message: `feat: force use of strict types`

### Bad examples

Files changed: *tests/RouterTest.php*, *tests/MatcherTest.php*
commit message: `perf: refactoring tests`
Why it's bad: message is not using the imperative mood and doesn't have a clear description of what changed.

Files changed: *CHANGELOG.md*, *composer.json*, *.gitignore*
commit message: `chore: release version 1.3.0`
Why it's bad: the *.gitignore* has been modified but has nothing to do with the new release. This should be two separate commits: one for the *.gitignore* file and one for the release.
