# 2026-10-10 1.2.0 - Optional forwarding filters
## Feature
- Added an optional agent selector that forwards only tickets visible to the selected agent.
- Leaving the agent selector set to All agents preserves unrestricted agent visibility.
- Added an optional multi-select filter for help topics. Tickets are forwarded only when their help topic is selected.
- Leaving the filter empty preserves forwarding from all help topics.
- DEV: better release lifecycle

# 2026-06-22 1.1.5 - Adapted endpoints to TicketMind Backend
## Bugfix
- endpoints prefixes

# 2026-06-22 1.1.4 - Adapted endpoints to TicketMind Backend
- endpoints changed

# 2026-06-22 1.1.3 - Certificate path validation
## Feature
- Certificate path validation added.

# 2026-03-29 1.1.2 - Fixed ci/cd release creation
## Bugfix
- Fixed ci/cd release creation

# 2026-03-29 1.1.0 - Self-Signed Certificate support.
## Features
- Added support for self-signed certificates.
- Tested on osTicket (v1.18.3)

# 2026-01-06 1.0.1 - Fixed issues
## Bugfix
- Removed dependency on composer.

# 2026-01-06 1.0.0 - Add to Rag function
## Features
- Now adding full thread to rag is possible. Use `/addtorag` command as internal note command or call with menu. 

## Changes
- Changed API setting. Now the URL to TicketMind API is needed instead of signal API endpoint.
- Remove dependency on Symphony HttpClient, works with ext-curl only.

# 2025-09-02 0.1.0 - Initial release
