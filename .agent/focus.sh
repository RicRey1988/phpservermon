#!/usr/bin/env bash
set -Eeuo pipefail
REPORT=.agent/focus-result.txt
: > "$REPORT"
CURRENT=setup
trap 'code=$?; printf "FAIL %s (%s)\n" "$CURRENT" "$code" >> "$REPORT"; exit "$code"' ERR
step() {
  CURRENT=$1
  shift
  printf 'RUN %s\n' "$CURRENT" >> "$REPORT"
  "$@"
  printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
}
step native-hope-contracts vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php
step pwa-assets vendor/bin/phpunit tests/Unit/Pwa/PwaAssetTest.php
step service-worker-js node --check service-worker.js
CURRENT=static-removal
python3 - <<'PY'
from pathlib import Path
assert not Path('src/templates/default/static/css/hs-monitor.css').exists()
body = Path('src/templates/default/main/body.tpl.html').read_text()
worker = Path('service-worker.js').read_text()
assert 'hs-monitor.css' not in body
assert 'hs-monitor.css' not in worker
assert "psm-static-4.3.2-hs-r2" in worker
for asset in ['hope-ui.min.css', 'dark.min.css', 'customizer.min.css']:
    assert asset in body
PY
printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
printf 'ALL PASS\n' >> "$REPORT"
