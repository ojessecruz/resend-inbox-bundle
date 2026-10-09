# Changelog

All notable changes to `resend-inbox-bundle` will be documented in this file.

## 0.1.1 - 2026-10-09

- "Mark as read" for the ticked conversations in the list.
- The email body takes the `--inbox-email-text` and `--inbox-email-bg` variables instead of a fixed white and near-black.
- Requires `ojessecruz/resend-inbox` 0.1.1, which stores received emails at the right time in apps outside UTC.

## 0.1.0 - 2026-10-09

- First release: a shared inbox for Symfony 7.4 and 8 on top of [`ojessecruz/resend-inbox`](https://github.com/ojessecruz/resend-inbox). Tabs per address plus "Others", threading by Message-ID with a subject fallback, replies and new emails in Markdown with per-address signatures, delivery status from webhooks, attachments downloaded from Resend on demand, Doctrine entities, Messenger processing, the `RESEND_INBOX_VIEW` voter, Twig templates with their own CSS, English and Brazilian Portuguese.
