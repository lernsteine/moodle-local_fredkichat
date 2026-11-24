# Changes

## 1.2.12 (2026-11-24)

- Settings and the list of chatbot designs show where to get a FredKI® server: hosted by lernsteine.com or self-hosted from GitHub.

## 1.2.11 (2026-11-24)

- "Keep proportions of the chat button": changing the width adjusts the height and the other way round.

## 1.2.10 (2026-11-24)

- New options per chatbot design: width and height of the floating chat button (40–320 × 40–160 px, default 68 × 68). A wide round button becomes a pill.

## 1.2.9 (2026-11-24)

- New option per chatbot design: "Chat button shape" – round (default, as before) or square with rounded corners.

## 1.2.8 (2026-11-24)

- New option per chatbot design: "Logo shape" – rectangle with rounded corners (default, as before) or round.
- Chatbot designs can be copied, including the logo. The copy opens for editing; external embedding is switched off for it.

## 1.2.7 (2026-11-17)

- New option per chatbot design: "White frame around the logo" (on by default, as before). Untick it for logos with their own background.

## 1.2.6 (2026-11-17)

- Chatbot design form: "Save and return" saves and goes back to the list of designs; "Save and continue editing" stays on the form.

## 1.2.5 (2026-11-13)

- LICENSE file with the full GNU GPL v3 text in the plugin root.
- README: FredKI can be self-hosted for free (https://github.com/lernsteine/FredKI); the hosted service is optional.

## 1.2.4 (2026-10-29)

- Fixed: missing space between the greeting and the starter questions.

## 1.2.3 (2026-10-29)

- Passes the Moodle plugin CI on Moodle 4.4, 4.5 and 5.0 (stylelint, ESLint, Mustache lint, grunt build).
- Stylesheet reformatted without !important; chat template uses valid HTML structure.
- Accessible label of tables in answers is now a language string.

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
