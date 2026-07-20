#!/usr/bin/env bash
set -euo pipefail
vendor/bin/phpunit tests/Unit/Server/StatusDashboardTest.php --filter testDashboardUsesSummaryChartsAccessibleCardsAndNoTables
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testServerFormAndInstallerExposeModernSections
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testServerDetailsUseTimelineAndHistoryNeedsNoJquery
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php --filter testServerViewsContainNoLegacyFixedWidthOrBootstrapFourUtilities
php -r 'require "vendor/autoload.php"; $loader = new Twig\Loader\FilesystemLoader("src/templates/default"); $twig = new Twig\Environment($loader); $twig->addFunction(new Twig\TwigFunction("csrf_token", static fn (): string => "")); foreach (["module/server/status/index.tpl.html","module/server/status/cards.tpl.html","module/server/statistics/index.tpl.html","module/server/statistics/header.tpl.html","module/server/server/list.tpl.html","module/server/server/update.tpl.html","module/server/server/view.tpl.html","module/server/history.tpl.html","module/server/log.tpl.html"] as $file) { $twig->parse($twig->tokenize(new Twig\Source((string) file_get_contents("src/templates/default/" . $file), $file))); }'
node --check src/templates/default/static/js/status.js
node --check src/templates/default/static/js/dashboard.js
node --check src/templates/default/static/js/history.js
node --check src/templates/default/static/js/app-shell.js
python3 - <<'PY'
from pathlib import Path
import re
paths = [Path(path) for path in [
    'src/templates/default/module/server/status/index.tpl.html',
    'src/templates/default/module/server/status/cards.tpl.html',
    'src/templates/default/module/server/statistics/index.tpl.html',
    'src/templates/default/module/server/statistics/header.tpl.html',
    'src/templates/default/module/server/server/list.tpl.html',
    'src/templates/default/module/server/server/update.tpl.html',
    'src/templates/default/module/server/server/view.tpl.html',
    'src/templates/default/module/server/history.tpl.html',
    'src/templates/default/module/server/log.tpl.html',
]]
text = '\n'.join(path.read_text() for path in paths)
removed = [
    'status-card','status-banner','status-grid','server-image-box','status-card-title',
    'dashboard-stat-card','stat-icon','dashboard-chart','server-admin-card','server-media-frame',
    'server-media','status-indicator','server-facts','channel-chips','channel-chip',
    'server-detail-grid','server-detail-image','server-detail-tabs','history-panel',
    'history-graph','chart-container','history-range-controls','output-panel-content',
    'timeline-item','timeline-marker','min-w-0','dropzone-field','dropzone-preview',
    'dropzone-copy','hope-icon','form-group','types','typeWebsite','typeService','requestMethod',
]
for name in removed:
    pattern = rf'class=["\'][^"\']*\b{re.escape(name)}\b[^"\']*["\']'
    assert not re.search(pattern, text), f'removed presentation class remains: {name}'
assert ' style=' not in text
assert '<table' not in text
PY
