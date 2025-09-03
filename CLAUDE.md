# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an osTicket plugin for TicketMind integration that forwards tickets to a queue system. The plugin is built using
PHP and follows osTicket's plugin architecture.

**License**: GPL-2.0-only (GNU General Public License v2.0)

## Technology Stack

- **PHP**: >= 8.2 (with ext-json, ext-filter, ext-mbstring)
- **Framework**: osTicket plugin framework (v1.10+) for a documentation see @./../osTicket/AI_DOCUMENTATION.md
- **Dependency Management**: Composer (custom vendor directory: `lib/`)
- **HTTP Client**: Symfony HttpClient (v6.0|v7.0)
- **Namespace**: `TicketMind\Plugin\` (PSR-4 autoloading from `include/` directory)

## Architecture

The plugin follows osTicket's standard plugin structure:

1. **plugin.php**: Plugin metadata and configuration
2. **TicketMindSignalsPlugin.php**: Main plugin class extending osTicket's `Plugin` class
    - Single-instance plugin (`isMultiInstance() = FALSE`)
    - Bootstrap method connects to osTicket signals (`ticket.created`, `threadentry.created`)
    - Custom enable() method that modifies plugin name in database
    - Implements signal handlers for forwarding ticket data to TicketMind API

### Current Implementation

The plugin currently implements a simpler architecture focused on forwarding ticket signals to TicketMind:

1. **Core Components**:
   - `RestApiClient` - Handles HTTP communication with TicketMind API using Symfony HttpClient
   - `ConfigValues` - Provides access to plugin configuration values
   - `TicketMindSignalsPluginConfig` - Manages plugin configuration form with custom fields
   - `ExtraBooleanField` - Custom boolean field implementation for configuration

2. **Signal Handlers**:
   - `onTicketCreated` - Forwards new ticket data to TicketMind
   - `onThreadEntryCreated` - Forwards thread entry (replies/notes) to TicketMind

3. **Configuration Options**:
   - `queue_url` - TicketMind Host URL
   - `api_key` - API key for authentication (PasswordField)
   - `with_content` - Include ticket content in messages (ExtraBooleanField)
   - `forward_enabled` - Enable/disable forwarding (ExtraBooleanField)


## Development Commands

```bash
# Install Composer (if not present)
./install-composer.sh

# Install dependencies
php composer.phar install

# Update dependencies
php composer.phar update

# Regenerate autoloader
php composer.phar dump-autoload
```

## Important Development Notes

1. **Custom Vendor Directory**: Dependencies are installed in `lib/` instead of the default `vendor/` directory
2. **Autoloading**: The plugin uses Composer autoloading with PSR-4 for the `TicketMind\Plugin\` namespace
3. **Plugin ID**: `ticketmind:ost:signals` - this is used by osTicket to identify the plugin
4. **Database Modifications**: The plugin modifies its name in the database by prepending "__" when enabled

## osTicket Plugin Development

When developing features:

- Extend osTicket's base classes and follow their patterns
- Use osTicket's database abstraction layer (`db_query`, `db_input`, etc.)
- Follow osTicket's configuration patterns for plugin settings. See `TicketMindSignalsPluginConfig.php` for the actual implementation example which extends `PluginConfig` and implements `PluginCustomConfig`

### Field Types Used in This Plugin
- `TextboxField`: Single-line text input (used for TicketMind Host URL)
- `PasswordField`: Password input field (used for API keys)
- `ExtraBooleanField`: Custom boolean field with NULL default (plugin-specific implementation for toggles)
- `SectionBreakField`: Visual separator in the form

Note: Additional field types like `TextareaField`, `ChoiceField`, and `BooleanField` are available in osTicket but not currently used in this plugin.

## Testing

### Test Environment Access

To access the osTicket test environment for plugin testing:

```bash
# SSH into the test server
ssh solve42osticketadmin@osTicket-azure-test

# View osTicket logs (once connected)
# Application logs location will depend on the server configuration
```

The plugin is installed on the following remote directory: `/var/www/osTicket/upload/include/plugins/ticketmind-ost-signals`.

### Testing Approaches

No testing framework is currently set up. When adding tests, consider:

- PHPUnit for unit testing
- osTicket's testing patterns for plugin integration tests
- Manual testing in the Azure test environment

### Log files on host to check for log output

- `/var/log/apache2/osticket_error.log`  <- Most likely here are the errors
- `/var/log/apache2/osticket_access.log`
- `/var/log/apache2/error.log`
- `/var/log/apache2/access.log`

### Debugging Errors

If you encounter errors while developing or testing the plugin, you can use the provided script to retrieve osTicket logs:

```bash
# Run the log reader script
./read-osticket-logs.sh
```

This script will:
1. Connect to the remote osTicket server using credentials from the `.env` file
2. Retrieve the latest osTicket error logs
3. Display them for analysis

This is particularly useful for debugging plugin initialization errors, configuration issues, or runtime exceptions that may not be visible in the osTicket UI.
