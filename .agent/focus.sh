#!/usr/bin/env bash
set -euo pipefail
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter ApplicationShellProvidesModernFormInteractions
php -r 'require "vendor/autoload.php"; $loader = new Twig\Loader\FilesystemLoader("src/templates/default"); $twig = new Twig\Environment($loader); $twig->addFunction(new Twig\TwigFunction("csrf_token", static fn (): string => "")); foreach (["main/components.tpl.html", "main/macros.tpl.html", "main/appearance-customizer.tpl.html"] as $file) { $twig->parse($twig->tokenize(new Twig\Source((string) file_get_contents("src/templates/default/" . $file), $file))); }'
python3 - <<'PY'
from pathlib import Path
paths = [
    Path('src/templates/default/main/components.tpl.html'),
    Path('src/templates/default/main/macros.tpl.html'),
    Path('src/templates/default/main/appearance-customizer.tpl.html'),
]
removed = ['dropzone-field', 'hope-icon', 'form-row', 'input-group-prepend', 'searchbar', 'search_input', 'hope-settings', 'settings-section', 'setting-choice', 'preview-choice', 'accent-choice', 'accent-swatch', 'accent-grid', 'grid-cols-6', 'btn-setting']
text = '\n'.join(path.read_text() for path in paths)
for name in removed:
    assert name not in text, f'removed class remains: {name}'
PY
