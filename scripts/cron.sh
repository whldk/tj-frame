#!/bin/bash

SCRIPT_FOLDER=$(dirname $(readlink -f "$0"))

echo "host/refresh..."
/usr/bin/php "$SCRIPT_FOLDER/script.php" host/refresh
echo