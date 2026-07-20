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
step custom-class-audit vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php --filter testApplicationTemplatesUseNoClassesOwnedByRemovedStylesheet
step modern-page-audit vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testEveryApplicationPageUsesModernHopeUiContracts
CURRENT=twig-parse-all
php -r 'require "vendor/autoload.php"; $loader = new Twig\Loader\FilesystemLoader("src/templates/default"); $twig = new Twig\Environment($loader); $twig->addFunction(new Twig\TwigFunction("csrf_token", static fn (): string => "")); $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("src/templates/default")); foreach ($iterator as $file) { if ($file->isFile() && str_ends_with($file->getFilename(), ".tpl.html")) { $name = substr($file->getPathname(), strlen("src/templates/default/")); $twig->parse($twig->tokenize(new Twig\Source((string) file_get_contents($file->getPathname()), $name))); } }'
printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
CURRENT=static-global
python3 - <<'PY'
from pathlib import Path
import re
text = '\n'.join(path.read_text() for path in Path('src/templates/default').rglob('*.tpl.html'))
assert ' style=' not in text
assert '<style' not in text
assert 'data-dismiss=' not in text
assert 'sidebar-open' not in text
assert set(re.findall(r'data-toggle=["\']([^"\']+)["\']', text)) <= {'sidebar'}
PY
printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
printf 'ALL PASS\n' >> "$REPORT"
