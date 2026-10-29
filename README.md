# FredKI Chat (local_fredkichat)

Give agents of the hosted AI service **FredKI** a Moodle look and use them as a native, streaming chat inside Moodle.

FredKI is a hosted service by lernsteine.com based on [MaxKB](https://github.com/1Panel-dev/MaxKB) v2.
**A FredKI account (service account) is required.** The plugin itself is free software under the GNU GPL v3 or later.

## Features

- Load the agents that the configured FredKI service account can access.
- One Moodle design per agent: nine colours, logo/avatar, notice text, imprint/privacy/feedback links.
- Greeting of the agent is loaded from FredKI; bullet lines become clickable starter questions.
- Streaming answers with a "thinking" indicator and a stop button. Internal reasoning output is never shown.
- Chat history per browser tab (`sessionStorage`), several threads.
- Floating launcher button with normal and enlarged view.
- Optional public embedding on external websites (e.g. WordPress) with domain allow/block lists.
- Message limits per Moodle user and per external visitor address.

Teachers select the agents for their courses with the companion block **FredKI Flex Agent** (`block_fredkiflexagent`).

## Access control

A chatbot design can be used

1. by users with `local/fredkichat:manage` (preview),
2. in courses whose FredKI Flex Agent block has selected it, by users who can access that course and see the block,
3. by all logged-in users only if "Available to all logged-in users" is enabled for the design,
4. by anonymous visitors of other websites only if "Allow external embedding" is enabled.

## Requirements

- Moodle 4.4 or later
- PHP cURL extension
- A FredKI service account

## Installation

1. Install the plugin in `local/fredkichat`.
2. Site administration > Plugins > Local plugins > FredKI Chat: enter the FredKI URL, workspace, user name and password.
3. Site administration > Plugins > Local plugins > FredKI chatbot designs: create a design for an agent.

If FredKI runs in a private network, its host may have to be removed from the "cURL blocked hosts list"
(Site administration > General > Security > HTTP security).

## Privacy

- FredKI credentials are only used on the Moodle server.
- Chat messages are sent to FredKI to be answered. They are not stored in Moodle and no Moodle user identity is sent.
- Moodle stores which user created a chatbot design (Privacy API implemented).

## License

2026 lernsteine.com – GNU GPL v3 or later.
