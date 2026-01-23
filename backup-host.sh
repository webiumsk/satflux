#!/bin/bash

# Wrapper script to run backup.sh on host system
# This is called from within Docker container

cd "$(dirname "$0")"
exec ./backup.sh "$@"

