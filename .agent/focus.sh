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
step branding vendor/bin/phpunit tests/Unit/Ui/BrandingContractTest.php
step user-collection vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testUserCollectionAndLogsUseCardsRatherThanTables
step bootstrap5-attributes vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testModernViewsDoNotUseBootstrapFourDataAttributes
step data-hooks vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php --filter testJavaScriptUsesDataHooksRatherThanRemovedPresentationClasses
CURRENT=twig-parse
php -r 'require "vendor/autoload.php"; $loader = new Twig\Loader\FilesystemLoader("src/templates/default"); $twig = new Twig\Environment($loader); $twig->addFunction(new Twig\TwigFunction("csrf_token", static fn (): string => "")); foreach (["module/user/user/list.tpl.html","module/user/user/update.tpl.html","module/user/profile.tpl.html","module/user/notification/index.tpl.html","main/app-navbar.tpl.html"] as $file) { $twig->parse($twig->tokenize(new Twig\Source((string) file_get_contents("src/templates/default/" . $file), $file))); }'
printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
step notifications-js node --check src/templates/default/static/js/notifications.js
CURRENT=static-contract
python3 - <<'PY'
from pathlib import Path
import re
paths = [Path(path) for path in [
    'src/templates/default/module/user/user/list.tpl.html',
    'src/templates/default/module/user/user/update.tpl.html',
    'src/templates/default/module/user/profile.tpl.html',
    'src/templates/default/module/user/notification/index.tpl.html',
    'src/templates/default/main/app-navbar.tpl.html',
]]
text = '\n'.join(path.read_text() for path in paths)
removed = [
    'user-card','user-contact','searchbar','user-editor-card','identity-preview',
    'identity-preview--avatar','appearance-options','appearance-card','push-device-card',
    'dropzone-field','dropzone-preview','dropzone-copy','min-w-0','notification-unread',
    'notification-count','notification-dropdown','notification-dropdown-list','hope-icon','hope-icon-lg',
]
for name in removed:
    pattern = rf'class=["\'][^"\']*\b{re.escape(name)}\b[^"\']*["\']'
    assert not re.search(pattern, text), f'removed presentation class remains: {name}'
assert ' style=' not in text
assert 'data-user-editor' in text
assert 'data-notification-item' in text
assert 'data-notification-count' in text
assert 'data-unread-badge' in text
assert 'notification-unread' not in Path('src/templates/default/static/js/notifications.js').read_text()
PY
printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
printf 'ALL PASS\n' >> "$REPORT"
