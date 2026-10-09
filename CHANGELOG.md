# Changelog

All notable changes to `resend-inbox-bundle` will be documented in this file.

## 0.1.3 - 2026-10-09

- The email iframe shrinks to short emails: it measured the document, which never reports less than the iframe's own 400px.
- `theme` option. The screens only followed the operating system's dark mode, so an app with its own light/dark toggle could not bring the inbox along. `app` follows a `dark` class, `data-theme="dark"` or `data-bs-theme="dark"` set by the app; `light` and `dark` are fixed; `system` stays the default.

## 0.1.2 - 2026-10-09

- "Mark as unread" for the ticked conversations in the list.

## 0.1.1 - 2026-10-09

- "Mark as read" for the ticked conversations in the list.
- The email body takes the `--inbox-email-text` and `--inbox-email-bg` variables instead of a fixed white and near-black.
- Requires `ojessecruz/resend-inbox` 0.1.1, which stores received emails at the right time in apps outside UTC.

## 0.1.0 - 2026-10-09

- First release: a shared inbox for Symfony 7.4 and 8 on top of [`ojessecruz/resend-inbox`](https://github.com/ojessecruz/resend-inbox). Tabs per address plus "Others", threading by Message-ID with a subject fallback, replies and new emails in Markdown with per-address signatures, delivery status from webhooks, attachments downloaded from Resend on demand, Doctrine entities, Messenger processing, the `RESEND_INBOX_VIEW` voter, Twig templates with their own CSS, English and Brazilian Portuguese.
