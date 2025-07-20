# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an osTicket plugin for TicketMind integration that forwards tickets to a queue system. The plugin is built using
PHP and follows osTicket's plugin architecture.

## Reference Implementation

The `@../bfx-ost-streamer` directory contains a mature reference implementation of an osTicket streaming plugin that demonstrates:
- Complete factory pattern implementation for all components (Stream, Record, Serializer, Encoder, Tuple, UseCase)
- Signal-based extensibility system for runtime configuration
- Comprehensive validation framework
- Multi-format data streaming (JSON, CSV, Line-delimited JSON)
- Multiple encoding options (Base64, Hex, UTF-8)
- Production-ready error handling and logging

Study this implementation for architectural patterns and best practices when implementing the TicketMind integration.

## Technology Stack

- **PHP**: >= 7.1 (with ext-json, ext-filter, ext-mbstring)
- **Framework**: osTicket plugin framework (v1.10+) for a documentation see @./../osTicket/AI_DOCUMENTATION.md
- **Dependency Management**: Composer (custom vendor directory: `lib/`)
- **Namespace**: `TicketMind\Data\` (PSR-4 autoloading from `include/` directory)

## Architecture

The plugin follows osTicket's standard plugin structure:

1. **plugin.php**: Plugin metadata and configuration
2. **TicketMindSignalsPlugin.php**: Main plugin class extending osTicket's `Plugin` class
    - Single-instance plugin (`isMultiInstance() = FALSE`)
    - Bootstrap method contains commented UseCase factory pattern (not yet implemented)
    - Custom enable() method that modifies plugin name in database

### Architectural Patterns (from Reference Implementation)

The reference implementation demonstrates several key patterns that should be followed:

1. **Factory Pattern**: All components use factories extending `AbstractFactory`
   - StreamFactory, RecordFactory, SerializerFactory, EncoderFactory, TupleFactory, UseCaseFactory
   
2. **Data Pipeline Architecture**:
   ```
   osTicket Event → UseCase → Tuple → Serializer → Encoder → Record → Stream → External System
   ```

3. **Signal/Action System**: Extensibility through osTicket signals
   - Format signals: `ticketmind.data.stream.format.<component>`
   - Option signals: `ticketmind.data.stream.option.<component>`
   - Use case signals: `ticketmind.data.stream.use_case`

4. **Interface-Driven Design**: Core interfaces to implement:
   - `ActionInterface`: Event-driven actions
   - `StreamInterface`: Stream implementations
   - `RecordInterface`: Data records
   - `SerializerInterface`: Data serialization
   - `EncoderInterface`: Data encoding
   - `TupleInterface`: Data collection
   - `UseCaseInterface`: Business logic
   - `ValidatorInterface`: Input validation

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
2. **Autoloading**: The plugin uses Composer autoloading with PSR-4 for the `TicketMind\Data\` namespace
3. **Plugin ID**: `ticketmind:ost:signals` - this is used by osTicket to identify the plugin
4. **Database Modifications**: The plugin modifies its name in the database by prepending "__" when enabled

## osTicket Plugin Development

When developing features:

- Extend osTicket's base classes and follow their patterns
- Use osTicket's database abstraction layer (`db_query`, `db_input`, etc.)
- Follow osTicket's configuration patterns for plugin settings:
          It looks the following:

          ```php
           <?php
           require_once INCLUDE_DIR . 'class.plugin.php';
       
           class YourPluginConfig extends PluginConfig {
               function getOptions() {
                   list($__, $_N) = self::translate();
          
                   return array(
                       'section' => new SectionBreakField(array(
                           'label' => $__('Plugin Settings'),
                       )),
                       'your-setting' => new TextboxField(array(
                           'label' => $__('Your Setting'),
                           'configuration' => array(
                               'size' => 60,
                               'length' => 100,
                           ),
                       )),
                   );
               }
           }
           ```

          Following field types are available (see file: osTicket/include/class.forms.php)
            - `TextboxField`: Single-line text input.
            - `TextareaField`: Multi-line text input.
            - `BooleanField`: Checkbox for true/false values.
            - `ChoiceField`: Dropdown selection.
            - `SectionBreakField`: Visual separator in the form.

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
