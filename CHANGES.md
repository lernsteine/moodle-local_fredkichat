# Changes

## 1.2.2 (2026-10-29)

- Fixed: with developer debugging enabled, a debugging notice could break the chat launcher script.

## 1.2.1 (2026-10-28)

- Greeting changes in FredKI appear after at most five minutes (was 15).
- A greeting that was deleted in FredKI is now also removed in Moodle.

## 1.2.0 (2026-10-28)

- Licensed under GNU GPL v3 or later; coding style follows the Moodle coding standard.
- Access check: a chatbot is only usable through a course block that selected it, by managers,
  or by all logged-in users when "Available to all logged-in users" is enabled.
- Fixed FredKI (MaxKB v2) API endpoints instead of trying several variants; management token is cached.
- HTTP requests use Moodle's curl class, so proxy settings and the HTTP security blocklist apply.
- Message limits per Moodle user and per external visitor.
- Scheduled task removes expired public embed sessions.
- Agent greetings are cached instead of being loaded on every chat opening.
- Complete Privacy API implementation.
- All texts are language strings; English and German.
- Removed the deprecated global design template and unused fields.
