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
step auth-controls vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testAuthenticationFormsUseVisibleAccessibleControls
step installer-sections vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testServerFormAndInstallerExposeModernSections
step installer-links vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testInstallerUsesModernCardsAndHsProjectLinks
step application-pages vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testEveryApplicationPageUsesModernHopeUiContracts
CURRENT=twig-parse
php -r 'require "vendor/autoload.php"; $loader = new Twig\Loader\FilesystemLoader("src/templates/default"); $twig = new Twig\Environment($loader); $twig->addFunction(new Twig\TwigFunction("csrf_token", static fn (): string => "")); foreach (["module/user/login/login.tpl.html","module/user/login/forgot.tpl.html","module/user/login/reset.tpl.html","module/user/login/register.tpl.html","module/install/index.tpl.html","module/install/main.tpl.html","module/install/config_new.tpl.html","module/install/config_new_user.tpl.html","module/install/config_upgrade.tpl.html","module/install/results.tpl.html","module/install/success.tpl.html","module/error/401.tpl.html","util/module/modal.tpl.html","util/module/sidebar.tpl.html"] as $file) { $twig->parse($twig->tokenize(new Twig\Source((string) file_get_contents("src/templates/default/" . $file), $file))); }'
printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
CURRENT=static-contract
python3 - <<'PY'
from pathlib import Path
import re
paths = [Path(path) for path in [
    'src/templates/default/module/user/login/login.tpl.html',
    'src/templates/default/module/user/login/forgot.tpl.html',
    'src/templates/default/module/user/login/reset.tpl.html',
    'src/templates/default/module/user/login/register.tpl.html',
    'src/templates/default/module/install/index.tpl.html',
    'src/templates/default/module/install/main.tpl.html',
    'src/templates/default/module/install/config_new.tpl.html',
    'src/templates/default/module/install/config_new_user.tpl.html',
    'src/templates/default/module/install/config_upgrade.tpl.html',
    'src/templates/default/module/install/results.tpl.html',
    'src/templates/default/module/install/success.tpl.html',
    'src/templates/default/module/error/401.tpl.html',
    'src/templates/default/util/module/modal.tpl.html',
    'src/templates/default/util/module/sidebar.tpl.html',
]]
text = '\n'.join(path.read_text() for path in paths)
removed = [
    'auth-shell','auth-layout','auth-layout-single','auth-form-pane','auth-card',
    'auth-visual','auth-visual-brand','auth-brand-panel','auth-visual-logo',
    'brand-mark','brand-mark-lg','brand-logo','brand-logo--auth','install-shell',
    'install-stepper','error-state-card','form-group','form-row','custom-select',
    'input-group-prepend','hope-icon','hope-icon-lg',
]
for name in removed:
    pattern = rf'class=["\'][^"\']*\b{re.escape(name)}\b[^"\']*["\']'
    assert not re.search(pattern, text), f'removed presentation class remains: {name}'
assert ' style=' not in text
assert '<table' not in text
assert 'data-install-stepper' in text
assert 'data-error-state' in text
assert 'class="card border-0 shadow-none bg-transparent"' in text
PY
printf 'PASS %s\n' "$CURRENT" >> "$REPORT"
printf 'ALL PASS\n' >> "$REPORT"
