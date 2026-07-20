#!/usr/bin/env bash
set -euo pipefail
vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php --filter Shell
php -l src/psm/Module/AbstractController.php
node --check src/templates/default/static/js/app-shell.js
