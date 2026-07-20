#!/usr/bin/env bash
set -euo pipefail
vendor/bin/phpunit tests/Unit/Ui/BrandingContractTest.php
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testUserCollectionAndLogsUseCardsRatherThanTables
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testModernViewsDoNotUseBootstrapFourDataAttributes
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testEveryApplicationPageUsesModernHopeUiContracts
vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php --filter testJavaScriptUsesDataHooksRatherThanRemovedPresentationClasses
php -r 'require "vendor/autoload.php"; $loader = new Twig\Loader\FilesystemLoader("src/templates/default"); $twig = new Twig\Environment($loader); $twig->addFunction(new Twig\TwigFunction("csrf_token", static fn (): string => "")); foreach (["module/user/user/list.tpl.html","module/user/user/update.tpl.html","module/user/profile.tpl.html","module/user/notification/index.tpl.html","main/app-navbar.tpl.html"] as $file) { $twig->parse($twig->tokenize(new Twig\Source((string) file_get_contents("src/templates/default/" . $file), $file))); }'
node --check src/templates/default/static/js/notifications.js
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
PY
