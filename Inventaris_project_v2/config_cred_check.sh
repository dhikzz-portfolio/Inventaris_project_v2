#!/bin/bash
set -euo pipefail
php artisan config:show database.default && echo "HAVE_CONFIG"
