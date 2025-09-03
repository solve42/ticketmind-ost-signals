#!/usr/bin/env bash

# Activate virtual environment if it exists
source ~/venv/ticketmind-core/bin/activate

# Extract API key and run command
API_KEY="$(sed -n 's/^paramiko_connection_str=\(.*\)$/\1/p' .env)"
read-osticket-logs --nlines=30 --paramiko_connection_str="$API_KEY"


deactivate
