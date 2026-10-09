# Contributing to Resend Inbox Bundle

Thanks for your interest. This package is maintained by one person, so these rules keep reviews short and the package focused. Pull requests that don't follow them may be closed without review.

## Before you open a pull request

- **Open an issue first** for anything other than a small, obvious bug fix or a typo. Wait for the maintainer to agree on the change before writing code. Unannounced features are usually declined.
- **One change per pull request.** No unrelated refactors, renames or reformatting.
- **No new dependencies** (runtime or dev) without agreement in the issue.
- Security problems are never reported in public issues: see [SECURITY.md](SECURITY.md).

## Scope of this package

This bundle **executes**: Doctrine entities, the webhook, Messenger processing, events and the Twig screens. Decisions (threading, replies, auto-reply detection, delivery status order, webhook parsing) belong to the core, [`ojessecruz/resend-inbox`](https://github.com/ojessecruz/resend-inbox). A pull request that adds such logic here will be asked to move it to the core.

- The bundle stays agnostic: no links to the host app's users or other entities. Who sent an email is stored as the user identifier only.
- Screens are plain Twig templates split into small partials, styled by `public/inbox.css` through `--inbox-*` variables. No Tailwind, no Symfony UX, no JavaScript framework, no build step; `public/inbox.js` only enhances and every screen works without it.
- Queries run on SQLite, MySQL and PostgreSQL. No database-specific SQL or JSON operators; the CI runs the suite on all three.
- Every new string goes in **both** `en` and `pt_BR` (`translations/`, PHP files).
- The table and column names are fixed in the entity mapping and never renamed after a release.
- The public API is: configuration keys, route names, template names and their blocks, events, the `RESEND_INBOX_VIEW` attribute and the entities' public methods. Breaking it only happens in a major version.

## Requirements for every pull request

- Tests with Pest for the change: the bug fix comes with a test that fails without it.
- `composer test` passes on PHP 8.3–8.5 × Symfony 7.4/8, with `--prefer-lowest` too, and on MySQL and PostgreSQL (`DATABASE_URL`).
- `composer analyse` passes (PHPStan at the level in `phpstan.neon.dist`). Don't add baseline entries or `@phpstan-ignore` to make it pass.
- `composer format` was run (Laravel Pint, `pint.json`). CI checks the style but doesn't fix it for you.
- The README is updated when behavior or configuration changes.
- Don't edit `CHANGELOG.md` or the version: the maintainer does that at release time.

## Commits and merging

- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/): `fix: …`, `feat: …`, `docs: …`, `test: …`, `refactor: …`, `chore: …`. A breaking change has `!` after the type and explains the migration path in the body.
- `main` is protected: changes only land through pull requests, with every check green and the maintainer's approval.
- Pull requests are squash-merged, so the title must be a valid Conventional Commit message.
- Releases (tags `v*`) are made only by the maintainer.

## Development

```bash
composer install
composer test
composer analyse
composer format
```

## License

By contributing, you agree that your contributions are licensed under the [MIT License](LICENSE.md).
