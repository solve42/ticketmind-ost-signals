#!/bin/bash

REMOTE_HOST="osTicket-azure-test"
REMOTE_USER="solve42osticketadmin"
REMOTE_PATH="/var/www/osTicket/upload/include/plugins/ticketmind-ost-signals"

echo "Deploying plugin files to Azure..."

# Deploy main files
sftp $REMOTE_USER@$REMOTE_HOST << EOF
cd $REMOTE_PATH
put plugin.php
put TicketMindSignalsPlugin.php
put composer.json
bye
EOF

# Deploy config file
sftp $REMOTE_USER@$REMOTE_HOST << EOF
cd $REMOTE_PATH/include/Signals/osTicket/Configuration
put include/Signals/osTicket/Configuration/TicketMindSignalsPluginConfig.php
bye
EOF

# Deploy lib files
sftp $REMOTE_USER@$REMOTE_HOST << EOF
cd $REMOTE_PATH/lib
put lib/autoload.php
cd composer
put lib/composer/autoload_classmap.php
put lib/composer/autoload_namespaces.php
put lib/composer/autoload_psr4.php
put lib/composer/autoload_real.php
put lib/composer/autoload_static.php
put lib/composer/ClassLoader.php
put lib/composer/installed.json
put lib/composer/installed.php
put lib/composer/InstalledVersions.php
put lib/composer/platform_check.php
bye
EOF

echo "Deployment complete!"