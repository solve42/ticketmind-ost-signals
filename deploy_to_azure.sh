#!/bin/bash

# Deployment script for TicketMind osTicket Signals Plugin

REMOTE_HOST="osTicket-azure-test"
REMOTE_USER="solve42osticketadmin"
REMOTE_PATH="/var/www/osTicket/upload/include/plugins/ticketmind-ost-signals"

echo "Deploying TicketMind osTicket Signals Plugin to Azure..."

# Create SFTP batch file
cat > /tmp/sftp_batch.txt << 'EOF'
cd /var/www/osTicket/upload/include/plugins/ticketmind-ost-signals
put plugin.php
put TicketMindSignalsPlugin.php
put composer.json
mkdir include
cd include
mkdir Signals
cd Signals
mkdir osTicket
cd osTicket
mkdir Configuration
cd Configuration
lcd include/Signals/osTicket/Configuration
put TicketMindSignalsPluginConfig.php
cd /var/www/osTicket/upload/include/plugins/ticketmind-ost-signals
mkdir lib
cd lib
lcd lib
put autoload.php
mkdir composer
cd composer
lcd composer
put autoload_classmap.php
put autoload_namespaces.php
put autoload_psr4.php
put autoload_real.php
put autoload_static.php
put ClassLoader.php
put installed.json
put installed.php
put InstalledVersions.php
put platform_check.php
bye
EOF

# Execute SFTP
sftp -b /tmp/sftp_batch.txt $REMOTE_USER@$REMOTE_HOST

# Clean up
rm /tmp/sftp_batch.txt

echo "Deployment complete!"