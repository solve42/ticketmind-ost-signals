#!/usr/bin/env bash

# Activate virtual environment if it exists
source ~/venv/OsTicketDraftGeneration/bin/activate

# Extract API key and run command
API_KEY="$(sed -n 's/^paramiko_connection_str=\(.*\)$/\1/p' .env)"
read-osticket-logs --nlines=20 --paramiko_connection_str="$API_KEY"


deactivate
