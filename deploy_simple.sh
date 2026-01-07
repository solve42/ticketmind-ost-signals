#!/bin/bash

REMOTE_HOST="osTicket-azure-test"
REMOTE_USER="solve42osticketadmin"
REMOTE_PATH="/var/www/osTicket/upload/include/plugins/ticketmind-ost-signals"

echo "Deploying plugin files to Azure..."

# Deploy main files
sftp $REMOTE_USER@$REMOTE_HOST << EOF
mkdir $REMOTE_PATH
mkdir $REMOTE_PATH/include
mkdir $REMOTE_PATH/include/Signals
mkdir $REMOTE_PATH/include/Signals/osTicket
mkdir $REMOTE_PATH/include/Signals/osTicket/Configuration
mkdir $REMOTE_PATH/include/Signals/osTicket/Client

cd $REMOTE_PATH
put plugin.php
put TicketMindSignalsPlugin.php
bye
EOF

# Deploy config file
sftp $REMOTE_USER@$REMOTE_HOST << EOF
cd $REMOTE_PATH/include/Signals/osTicket/Configuration
put include/Signals/osTicket/Configuration/TicketMindSignalsPluginConfig.php
put include/Signals/osTicket/Configuration/ConfigValues.php
put include/Signals/osTicket/Configuration/ExtraBooleanField.php

cd $REMOTE_PATH/include/Signals/osTicket/Client
put include/Signals/osTicket/Client/RestApiClient.php
put include/Signals/osTicket/Client/RestApiClientPure.php
bye
EOF


echo "Deployment complete!"
