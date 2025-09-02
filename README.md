# TicketMind osTicket Signals Plugin - Installation Guide

provided by Solve42 GmbH.

Product page: https://ticketmind.de

## Overview
The TicketMind osTicket Signals Plugin forwards ticket creation and thread entry events from osTicket to the TicketMind API for processing and analytics.

## System Requirements

### Prerequisites
- **osTicket**: Version 1.10 or higher
- **PHP**: Version 8.2 or higher
- **PHP Extensions**:
  - `ext-json` (JSON functions)
  - `ext-filter` (Input filtering)
  - `ext-mbstring` (Multibyte string support)
- **Composer**: For installing PHP dependencies
- **Web Server**: Apache or Nginx with appropriate permissions

### Network Requirements
- Outbound HTTPS connectivity to TicketMind API endpoints
- SSL/TLS support for secure API communication

## Pre-Installation Steps

### 1. Download the Plugin

#### Option A: Clone from Repository
```bash
cd /path/to/osticket/include/plugins
git clone https://github.com/solve42/ticketmind-ost-signals.git
cd ticketmind-ost-signals
```

#### Option B: Download ZIP Archive
1. Download the latest release from the GitHub repository
2. Extract to your osTicket plugins directory:
```bash
cd /path/to/osticket/include/plugins
unzip ticketmind-ost-signals.zip
cd ticketmind-ost-signals
```

### 2. Install Dependencies

The plugin uses Composer for dependency management. Dependencies are installed in the `lib/` directory instead of the standard `vendor/` directory.

#### Method 1: Using the Installation Script
```bash
# Make the script executable
chmod +x install-composer.sh

# Run the installation script
./install-composer.sh
```

#### Method 2: Manual Installation
```bash
# Download Composer if not already installed
curl -sS https://getcomposer.org/installer | php

# Install dependencies
php composer.phar install

# Or if Composer is globally installed
composer install
```

### 3. Verify Installation
Ensure the following files and directories exist:
- `lib/autoload.php` - Composer autoloader
- `lib/symfony/` - Symfony HttpClient library
- `plugin.php` - Plugin metadata file
- `TicketMindSignalsPlugin.php` - Main plugin class

## Installation

### Method 1: Via osTicket Admin Panel (Recommended)

1. **Access Admin Panel**
   - Log in to osTicket as an administrator
   - Navigate to **Admin Panel → Manage → Plugins**

2. **Add New Plugin**
   - Click **Add New Plugin**
   - Click **Install** next to "TicketMind osTicket Signals"

3. **Verify Installation**
   - The plugin should appear in the installed plugins list
   - Status should show as "Disabled" initially

### Method 2: Manual File Installation

If the plugin doesn't appear in the admin panel:

1. **Set Correct Permissions**
```bash
# Navigate to plugin directory
cd /path/to/osticket/include/plugins/ticketmind-ost-signals

# Set appropriate ownership (adjust user/group as needed)
chown -R www-data:www-data .

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;
```

2. **Clear osTicket Cache**
```bash
# Remove cached plugin registry
rm -f /path/to/osticket/include/plugins/.registry
```

3. **Refresh Plugin List**
   - Return to Admin Panel → Manage → Plugins
   - The plugin should now appear

## Configuration

### 1. Enable the Plugin

1. Navigate to **Admin Panel → Manage → Plugins**
2. Click on **TicketMind osTicket Signals**
3. Change status to **Enabled**
4. Click **Save Changes**

### 2. Configure Plugin Settings

Click on the plugin name to access configuration:

#### Required Settings

1. **TicketMind Host URL**
   - Enter the full URL of your TicketMind API endpoint
   - Example: `https://api.ticketmind.com/queue`
   - Must include protocol (https://)

2. **API Key**
   - Enter your TicketMind API authentication key
   - Obtain this from your TicketMind account settings
   - Keep this secure - it authenticates your osTicket instance

#### Optional Settings

3. **Include Content in Forwarded Messages**
   - Enable to include full ticket/thread content
   - Disable to send only metadata (ticket ID, timestamps, etc.)
   - Default: Enabled

4. **Enable Forwarding**
   - Master switch to enable/disable all forwarding
   - Useful for maintenance or troubleshooting
   - Default: Enabled

### 3. Save Configuration

Click **Save Changes** to apply your configuration.

## Testing the Installation

### 1. Verify Plugin Status

Check that the plugin is active:
```sql
-- Connect to osTicket database
SELECT * FROM ost_plugin WHERE name LIKE '%TicketMind%';
```

The plugin should show `isactive = 1`.

### 2. Test Connection

Create a test ticket to verify forwarding:

1. Create a new ticket via any channel (web form, email, API)
2. Check osTicket system logs for any errors:
   - Navigate to **Admin Panel → Dashboard → System Logs**
   - Look for entries from "TicketMind Plugin"

3. Verify in TicketMind:
   - Log in to your TicketMind dashboard
   - Check if the test ticket appears in the queue

### 3. Monitor Logs

Check server logs for detailed information:
```bash
# Apache error log
tail -f /var/log/apache2/osticket_error.log

# Or for Nginx
tail -f /var/log/nginx/error.log

# PHP error log (location varies)
tail -f /var/log/php/error.log
```

## Troubleshooting

### Common Issues

#### Plugin Not Visible in Admin Panel
- Ensure all files are properly uploaded
- Check file permissions (readable by web server)
- Delete `/include/plugins/.registry` file
- Verify PHP version compatibility

#### Dependencies Not Loading
```bash
# Regenerate autoloader
cd /path/to/osticket/include/plugins/ticketmind-ost-signals
php composer.phar dump-autoload
```

#### API Connection Failures
- Verify TicketMind Host URL is correct and accessible
- Check API key validity
- Ensure outbound HTTPS is not blocked by firewall
- Review SSL certificate validation settings

#### No Data Being Forwarded
1. Verify "Enable Forwarding" is checked
2. Check osTicket system logs for errors
3. Ensure proper signal handlers are registered:
   - The plugin should handle `ticket.created` and `threadentry.created` signals

### Debug Mode

To enable detailed logging:

1. Edit `/path/to/osticket/include/ost-config.php`
2. Add or modify:
```php
define('LOG_LEVEL', LOG_DEBUG);
```

### Log File Locations

- **osTicket System Logs**: Admin Panel → Dashboard → System Logs
- **Apache Error Log**: `/var/log/apache2/osticket_error.log`
- **PHP Error Log**: Check `phpinfo()` for error_log location
- **Plugin-specific Logs**: Check for "TicketMind Plugin" entries in system logs

## Updating the Plugin

### 1. Backup Current Installation
```bash
cd /path/to/osticket/include/plugins
cp -r ticketmind-ost-signals ticketmind-ost-signals.backup
```

### 2. Update Files
```bash
cd ticketmind-ost-signals
git pull origin main
# Or extract new version from ZIP
```

### 3. Update Dependencies
```bash
php composer.phar update
```

### 4. Clear Cache
```bash
rm -f /path/to/osticket/include/plugins/.registry
```

### 5. Verify Configuration
- Check plugin settings remain intact
- Test with a new ticket
- Check audit logs for any errors

## Uninstallation

### 1. Disable Plugin
1. Navigate to **Admin Panel → Manage → Plugins**
2. Click on **TicketMind osTicket Signals**
3. Set status to **Disabled**
4. Click **Save Changes**

### 2. Remove Plugin (Optional)
1. Click **Delete** in the plugin management interface
2. Or manually remove files:
```bash
rm -rf /path/to/osticket/include/plugins/ticketmind-ost-signals
```

### 3. Clean Database (Optional)
```sql
-- Remove plugin configuration from database
DELETE FROM ost_plugin WHERE id = 'ticketmind:ost:signals';
DELETE FROM ost_config WHERE namespace LIKE 'ticketmind%';
```

## Security Considerations

1. **API Key Protection**
   - Store API keys securely
   - Use HTTPS for all API communications
   - Rotate keys periodically

2. **File Permissions**
   - Ensure plugin files are not world-writable
   - Protect configuration files containing sensitive data

3. **Network Security**
   - Whitelist outbound connection to TicketMind API endpoints in firewall rules

## Support

### Getting Help
- **GitHub Issues**: https://github.com/solve42/ticketmind-ost-signals/issues
- **Logs**: Always check system and error logs first

### Reporting Issues
When reporting issues, please include:
- osTicket version
- PHP version
- Plugin version
- Error messages from logs
- Steps to reproduce the issue

## License
This plugin is licensed under GPL-2.0-only. See LICENSE file for details.
