# PHP Server Monitor 4.3.2-hs Native HOPE UI Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar completamente `src/templates/default/static/css/hs-monitor.css`, migrar todas las vistas a contratos nativos de HOPE UI/Bootstrap 5 y corregir el buscador, selector de tema, flecha del sidebar, sesión duplicada y navegación móvil.

**Architecture:** HOPE UI seguirá siendo la única capa visual mediante sus tres hojas oficiales ya empaquetadas. Las plantillas compondrán tarjetas, grids, formularios, navegación, estados, imágenes y vistas responsivas con clases nativas; los selectores de comportamiento pasarán a atributos `data-*`. `app-shell.js` complementará el toggle nativo de HOPE UI únicamente para sincronizar accesibilidad y cierre móvil.

**Tech Stack:** PHP 8.5+, Twig 3.28, Symfony 7.4, PHPUnit 12, HOPE UI, Bootstrap 5, vanilla JavaScript, Apache, MariaDB.

## Global Constraints

- La versión del producto permanece en `4.3.2-hs`; todo tag y release usa el sufijo `-hs`.
- Solo pueden cargarse `hope-ui.min.css`, `dark.min.css` y `customizer.min.css`.
- `src/templates/default/static/css/hs-monitor.css` debe dejar de existir.
- No se agregará otro CSS, CSS inline ni un bloque `<style>` sustitutivo.
- Las clases usadas exclusivamente como hooks de JavaScript se reemplazan por `data-*`.
- No hay cambios de esquema de base de datos.
- Se preservan autenticación, PWA, notificaciones, gráficos, updater, branding, avatar, diagnósticos y preferencias de apariencia.
- Contraseñas, secretos y configuración específica del VPS nunca se escriben en Git ni en logs de CI.
- Todas las páginas deben funcionar en modo claro y oscuro y en anchos de escritorio, tableta y móvil.
- La implementación se realiza en la rama `feature/native-hope-ui-4.3.2-hs`; `main` se actualiza únicamente después de pasar toda la verificación.

---

### Task 1: Crear contratos de regresión para la eliminación del CSS

**Files:**
- Create: `tests/Unit/Ui/NativeHopeUiContractTest.php`
- Modify: `tests/Unit/Ui/ModernViewContractTest.php`
- Modify: `tests/Unit/Server/StatusDashboardTest.php`
- Modify: `tests/Unit/Pwa/PwaAssetTest.php`

**Interfaces:**
- Consumes: rutas de plantillas bajo `src/templates/default/`.
- Produces: contratos que impiden restaurar el CSS, las clases retiradas o el sidebar móvil antiguo.

- [ ] **Step 1: Crear el test fallido de shell y CSS nativo**

Crear `tests/Unit/Ui/NativeHopeUiContractTest.php` con este contenido:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class NativeHopeUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testOnlyBundledHopeUiStylesheetsAreLoaded(): void
    {
        $body = $this->read('src/templates/default/main/body.tpl.html');
        $worker = $this->read('service-worker.js');

        self::assertFileDoesNotExist($this->root . '/src/templates/default/static/css/hs-monitor.css');
        self::assertStringContainsString('hope/css/hope-ui.min.css', $body);
        self::assertStringContainsString('hope/css/dark.min.css', $body);
        self::assertStringContainsString('hope/css/customizer.min.css', $body);
        self::assertStringNotContainsString('hs-monitor.css', $body . $worker);
        self::assertStringNotContainsString('<style', $body);
    }

    public function testApplicationShellUsesNativeHopeUiContracts(): void
    {
        $body = $this->read('src/templates/default/main/body.tpl.html');
        $navbar = $this->read('src/templates/default/main/app-navbar.tpl.html');
        $menu = $this->read('src/templates/default/main/menu.tpl.html');
        $javascript = $this->read('src/templates/default/static/js/app-shell.js');

        self::assertStringContainsString('class="sidebar sidebar-default sidebar-white sidebar-base', $body);
        self::assertStringContainsString('class="sidebar-toggle" data-toggle="sidebar"', $body);
        self::assertStringContainsString('class="input-group search-input', $navbar);
        self::assertStringContainsString('data-theme-icon="dark"', $navbar);
        self::assertStringContainsString('data-theme-icon="light"', $navbar);
        self::assertStringNotContainsString('sidebar-user', $menu);
        self::assertStringNotContainsString('url_profile', $menu);
        self::assertStringNotContainsString('url_logout', $menu);
        self::assertStringContainsString("classList.add('sidebar-mini')", $javascript);
        self::assertStringContainsString("event.key === 'Escape'", $javascript);
        self::assertStringContainsString("classList.toggle('d-none'", $javascript);
        self::assertStringNotContainsString('sidebar-open', $body . $navbar . $javascript);
    }

    public function testRemovedPresentationClassesDoNotRemainInTemplates(): void
    {
        $contents = $this->allTemplateContents();
        $removed = [
            'hope-icon', 'hope-icon-lg', 'skip-link', 'place-items-center',
            'brand-mark', 'brand-logo', 'brand-logo--auth', 'auth-visual-logo',
            'navbar-avatar', 'identity-preview', 'identity-preview--avatar',
            'app-footer', 'theme-icon-dark', 'theme-icon-light', 'btn-setting',
            'hope-settings', 'settings-section', 'setting-choice', 'preview-choice',
            'accent-choice', 'accent-swatch', 'accent-grid', 'grid-cols-6',
            'appearance-options', 'appearance-card', 'push-device-card',
            'auth-page-content', 'auth-messages', 'auth-layout', 'auth-form-pane',
            'auth-card', 'auth-visual', 'auth-visual-brand', 'auth-layout-single',
            'brand-mark-lg', 'user-card', 'server-admin-card', 'status-card',
            'user-contact', 'timeline-list', 'timeline-item', 'timeline-marker',
            'dropzone-field', 'dropzone-preview', 'dropzone-copy', 'min-w-0',
            'server-media-frame', 'server-media--compact', 'status-indicator',
            'status-banner', 'status-banner-icon', 'status-pulse', 'status-grid',
            'server-image-box', 'status-card-title', 'dashboard-stat-card',
            'stat-icon', 'dashboard-chart', 'notification-count',
            'notification-dropdown', 'notification-dropdown-list',
            'notification-unread', 'server-facts', 'channel-chips', 'channel-chip',
            'server-detail-grid', 'server-detail-tabs', 'history-panel',
            'history-graph', 'chart-container', 'history-range-controls',
            'output-panel-content', 'config-layout', 'config-tabs', 'config-content',
            'install-shell', 'install-stepper', 'form-row', 'input-group-prepend',
            'custom-select', 'search_input', 'searchbar',
        ];

        foreach ($removed as $class) {
            self::assertDoesNotMatchRegularExpression(
                '/class=(?:"|\')[^"\']*\\b' . preg_quote($class, '/') . '\\b[^"\']*(?:"|\')/',
                $contents,
                'Legacy/custom presentation class remains: ' . $class,
            );
        }
    }

    private function allTemplateContents(): string
    {
        $contents = '';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root . '/src/templates/default'),
        );
        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            if ($file->isFile() && $file->getExtension() === 'html') {
                $contents .= "\n" . (string) file_get_contents($file->getPathname());
            }
        }

        return $contents;
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing file: ' . $path);

        return $contents;
    }
}
```

- [ ] **Step 2: Actualizar los tests existentes para el nuevo contrato**

En `ModernViewContractTest.php`, eliminar toda lectura o aserción sobre `static/css/hs-monitor.css` y sustituirlas por:

```php
$icons = $this->read('main/icons.tpl.html');
self::assertStringContainsString('class="icon-20 {{ className|default', $icons);
self::assertStringNotContainsString('hope-icon', $icons);
```

En `StatusDashboardTest.php`, reemplazar las aserciones de `server-image-box`, `status-card-title`, `status-banner` y CSS fijo por:

```php
self::assertStringContainsString('data-server-card', $cards);
self::assertStringContainsString('data-server-image', $cards);
self::assertStringContainsString('border-4', $cards);
self::assertStringContainsString('data-server-status-label', $cards);
self::assertStringNotContainsString('status-banner', $cards);
self::assertStringNotContainsString('hs-monitor.css', $body);
```

En `PwaAssetTest.php`, exigir la nueva revisión y la ausencia del stylesheet:

```php
self::assertStringContainsString("psm-static-4.3.2-hs-r2", $worker);
self::assertStringNotContainsString('hs-monitor.css', $worker);
```

- [ ] **Step 3: Ejecutar los contratos y confirmar el fallo inicial**

Run:

```bash
vendor/bin/phpunit \
  tests/Unit/Ui/NativeHopeUiContractTest.php \
  tests/Unit/Ui/ModernViewContractTest.php \
  tests/Unit/Server/StatusDashboardTest.php \
  tests/Unit/Pwa/PwaAssetTest.php
```

Expected: FAIL porque `hs-monitor.css` todavía existe, las plantillas aún lo cargan y permanecen las clases personalizadas.

- [ ] **Step 4: Commit de los tests fallidos**

```bash
git add tests/Unit/Ui/NativeHopeUiContractTest.php \
  tests/Unit/Ui/ModernViewContractTest.php \
  tests/Unit/Server/StatusDashboardTest.php \
  tests/Unit/Pwa/PwaAssetTest.php
git commit -m "test: define native Hope UI contracts"
```

---

### Task 2: Migrar el shell, buscador, tema y sidebar móvil

**Files:**
- Modify: `src/templates/default/main/icons.tpl.html`
- Modify: `src/templates/default/main/body.tpl.html`
- Modify: `src/templates/default/main/app-navbar.tpl.html`
- Modify: `src/templates/default/main/menu.tpl.html`
- Modify: `src/templates/default/static/js/app-shell.js`
- Modify: `src/psm/Module/AbstractController.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: `appearance.*`, `html_menu`, navbar user/notification data and native `hope-ui.js` sidebar toggling.
- Produces: sidebar controlled exclusively by `sidebar-mini`; theme icons controlled by `d-none`; primary sidebar without sesión duplicada.

- [ ] **Step 1: Convertir el macro de iconos a clases nativas**

Cambiar la apertura del SVG en `icons.tpl.html` a:

```twig
<svg class="icon-20 {{ className|default('') }}" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
```

No debe permanecer `hope-icon` ni `hope-icon-lg`.

- [ ] **Step 2: Reescribir la estructura del sidebar en `body.tpl.html`**

Usar el contrato nativo:

```twig
<aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all {{ appearance.sidebar_classes }}" aria-label="Navegación principal">
    <div class="sidebar-header d-flex align-items-center justify-content-start">
        <a href="index.php" class="navbar-brand d-flex align-items-center gap-2">
            {% if site_logo_url %}
                <img src="{{ site_logo_url }}" width="40" height="40" class="img-fluid rounded-3 object-fit-contain" alt="">
            {% else %}
                <span class="avatar-40 rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold" aria-hidden="true">HS</span>
            {% endif %}
            <h4 class="logo-title mb-0">{{ title }}</h4>
        </a>
        <button type="button" class="sidebar-toggle" data-toggle="sidebar" data-active="true" aria-label="Contraer menú" aria-expanded="true">
            <i class="icon">{{ hope.icon('arrow-left') }}</i>
        </button>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar">
        <div class="sidebar-list">{{ html_menu|raw }}</div>
    </div>
    <div class="sidebar-footer"></div>
</aside>
```

Eliminar el link a `hs-monitor.css`, la clase `no-menu`, `skip-link`, `app-footer`, `app-content`, `module-actions` y los wrappers que solo dependían del CSS retirado. Sustituirlos por utilidades como `visually-hidden-focusable`, `d-flex`, `flex-column`, `min-vh-100`, `py-4`, `border-top` y `bg-body`.

- [ ] **Step 3: Reescribir navbar, buscador e icono de tema**

En `app-navbar.tpl.html`, conservar la forma upstream del buscador y usar un único cuadro de icono:

```twig
<button type="button" class="sidebar-toggle" data-toggle="sidebar" aria-label="Abrir menú" aria-expanded="false">
    <i class="icon">{{ hope.icon('menu') }}</i>
</button>

<div class="input-group search-input d-none d-xl-flex">
    <span class="input-group-text" id="navbar-search-icon">{{ hope.icon('search','icon-18') }}</span>
    <input type="search" class="form-control" placeholder="Buscar en esta página" data-card-search aria-labelledby="navbar-search-icon">
</div>

<li class="nav-item">
    <button type="button" class="btn btn-icon nav-link d-inline-flex align-items-center justify-content-center" data-theme-quick-toggle aria-label="Cambiar a modo oscuro" title="Cambiar a modo oscuro">
        <span data-theme-icon="dark">{{ hope.icon('moon') }}</span>
        <span class="d-none" data-theme-icon="light">{{ hope.icon('sun') }}</span>
    </button>
</li>
```

Usar `avatar-40`, `rounded-pill`, `object-fit-cover`, `d-flex`, `align-items-center` y `justify-content-center` para logo/avatar; retirar `brand-*`, `navbar-avatar`, `place-items-center` y las clases `theme-icon-*`.

- [ ] **Step 4: Eliminar la sesión duplicada del sidebar**

Dejar `menu.tpl.html` únicamente con:

```twig
{% import 'main/icons.tpl.html' as hope %}
<nav aria-label="Secciones">
    <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
        {% for item in menu %}
        <li class="nav-item">
            <a class="nav-link {{ item.active }}" href="{{ item.url|raw }}"{% if item.active %} aria-current="page"{% endif %}>
                <i class="icon">{{ hope.icon(item.icon) }}</i>
                <span class="item-name">{{ item.label }}</span>
                {% if item.active %}<span class="visually-hidden"> ({{ label_current }})</span>{% endif %}
            </a>
        </li>
        {% endfor %}
    </ul>
</nav>
```

En `AbstractController::createHTMLMenu()`, retirar `label_help`, `label_profile`, `label_logout`, `url_profile`, `url_logout` y `label_usermenu`; conservar solo los datos de navegación primaria.

- [ ] **Step 5: Sincronizar accesibilidad y cierre móvil en `app-shell.js`**

Agregar estas funciones antes del listener `DOMContentLoaded`:

```javascript
function initializeSidebarAccessibility() {
    var sidebar = document.querySelector('.sidebar-default');
    var toggles = Array.from(document.querySelectorAll('[data-toggle="sidebar"]'));
    var mobile = window.matchMedia('(max-width: 1199.98px)');
    if (!sidebar || toggles.length === 0) { return; }

    function isOpen() { return !sidebar.classList.contains('sidebar-mini'); }
    function sync() {
        var open = isOpen();
        toggles.forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Contraer menú' : 'Abrir menú');
        });
    }
    function closeMobile() {
        if (!mobile.matches) { return; }
        sidebar.classList.add('sidebar-mini');
        sync();
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () { window.requestAnimationFrame(sync); });
    });
    sidebar.querySelectorAll('#sidebar-menu a').forEach(function (link) {
        link.addEventListener('click', closeMobile);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && mobile.matches && isOpen()) { closeMobile(); }
    });
    document.addEventListener('click', function (event) {
        if (!mobile.matches || !isOpen()) { return; }
        if (sidebar.contains(event.target) || toggles.some(function (toggle) { return toggle.contains(event.target); })) { return; }
        closeMobile();
    });
    window.addEventListener('resize', function () { window.requestAnimationFrame(sync); });
    sync();
}

function syncThemeIcons(resolved) {
    document.querySelectorAll('[data-theme-quick-toggle]').forEach(function (button) {
        var showLightAction = resolved === 'dark';
        var darkIcon = button.querySelector('[data-theme-icon="dark"]');
        var lightIcon = button.querySelector('[data-theme-icon="light"]');
        if (darkIcon) { darkIcon.classList.toggle('d-none', showLightAction); }
        if (lightIcon) { lightIcon.classList.toggle('d-none', !showLightAction); }
        var label = showLightAction ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    });
}
```

Llamar `syncThemeIcons(resolved)` dentro de `initializeTheme().apply()` y `initializeSidebarAccessibility()` en `DOMContentLoaded`. Eliminar cualquier referencia a `body.sidebar-open`.

- [ ] **Step 6: Ejecutar el test de shell**

```bash
vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php --filter ApplicationShell
```

Expected: PASS para sidebar, buscador, tema y menú; el test global seguirá fallando hasta migrar los módulos y borrar el CSS.

- [ ] **Step 7: Commit del shell**

```bash
git add src/templates/default/main src/templates/default/static/js/app-shell.js src/psm/Module/AbstractController.php tests/Unit/Ui/NativeHopeUiContractTest.php
git commit -m "fix: adopt native Hope UI application shell"
```

---

### Task 3: Migrar macros, componentes y personalizador

**Files:**
- Modify: `src/templates/default/main/components.tpl.html`
- Modify: `src/templates/default/main/macros.tpl.html`
- Modify: `src/templates/default/main/appearance-customizer.tpl.html`
- Modify: `src/templates/default/static/js/app-shell.js`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: macros Twig existentes y eventos `hope:setting-selected`.
- Produces: controles reutilizables sin clases respaldadas por `hs-monitor.css`.

- [ ] **Step 1: Convertir dropzone y empty state a composición nativa**

En `components.tpl.html` sustituir los macros por:

```twig
{% macro dropzone(id, name, label, accept, help) %}
<label class="card border border-2 border-dashed mb-3" for="{{ id }}" data-dropzone tabindex="0">
    <span class="card-body d-flex flex-column align-items-center justify-content-center gap-2 text-center py-4">
        {{ hope.icon('cloud-upload-alt','icon-32 text-primary') }}
        <span class="fw-semibold">{{ label }}</span>
        {% if help %}<small class="text-body-secondary">{{ help }}</small>{% endif %}
    </span>
    <input class="visually-hidden" type="file" id="{{ id }}" name="{{ name }}" accept="{{ accept }}" data-image-input>
</label>
{% endmacro %}

{% macro empty_state(title, body) %}
<div class="card"><div class="card-body text-center py-5">{{ hope.icon('inbox','icon-32 text-body-secondary mb-3') }}<h2 class="h5">{{ title }}</h2><p class="text-body-secondary mb-0">{{ body }}</p></div></div>
{% endmacro %}
```

- [ ] **Step 2: Retirar Bootstrap 4 de `macros.tpl.html`**

Cambiar `input_select_monitoring` a `row g-3 align-items-center mb-3`, mover el label a `col-md-3`, el select a `col-md-9`, y eliminar `form-group`, `form-row` y `p-0` estructural. En `input_field`, colocar el side label después del input:

```twig
{% if side_label and help %}<div class="input-group">{% endif %}
<input type="{{ type }}" class="form-control" id="{{ id }}" ...>
{% if side_label and help %}<span class="input-group-text" id="{{ help }}">{{ side_label }}</span></div>{% endif %}
```

Cambiar `table_search` a:

```twig
<div class="input-group mb-3">
    <span class="input-group-text">{{ hope.icon('search') }}</span>
    <input class="form-control" type="search" maxlength="255" placeholder="{{ label }}..." aria-label="{{ label }}" data-card-search>
</div>
```

- [ ] **Step 3: Rehacer el personalizador con rows, cols y botones**

Eliminar `hope-settings`, `settings-section`, `grid-cols-*`, `setting-choice`, `preview-choice`, `accent-choice`, `accent-swatch`, `accent-*`, `accent-grid` y `btn-setting`. Cada grupo usa `row g-2`, `col-*` y botones nativos. El selector de color usa texto y tonos Bootstrap, sin swatches:

```twig
{% set tones = {'default':'primary','blue':'info','gray':'secondary','red':'danger','yellow':'warning','pink':'primary'} %}
<div class="row row-cols-2 row-cols-sm-3 g-2">
{% for value, label in {'default':'Original','blue':'Azul','gray':'Gris','red':'Rojo','yellow':'Naranja','pink':'Rosa'} %}
    <div class="col">
        <button type="button" class="btn btn-outline-{{ tones[value] }} w-100{% if appearance.accent == value %} active{% endif %}" data-preference="accent" data-value="{{ value }}" aria-pressed="{{ appearance.accent == value ? 'true' : 'false' }}">{{ label }}</button>
    </div>
{% endfor %}
</div>
```

Las vistas previas usan `btn btn-outline-secondary w-100 h-100 p-2`, `img-fluid rounded border mb-2` y texto visible. El botón flotante conserva la clase oficial `btn-fixed-end`:

```twig
<button class="btn btn-fixed-end btn-warning btn-icon" type="button" data-bs-toggle="offcanvas" data-bs-target="#hope-ui-settings" aria-controls="hope-ui-settings" aria-label="Abrir personalizador de diseño">
    {{ hope.icon('gear','animated-rotate') }}
</button>
```

- [ ] **Step 4: Ejecutar tests de macros y legacy utilities**

```bash
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
```

Expected: las aserciones de macros y personalizador pasan; aún fallan las clases de módulos pendientes.

- [ ] **Step 5: Commit de componentes**

```bash
git add src/templates/default/main/components.tpl.html src/templates/default/main/macros.tpl.html src/templates/default/main/appearance-customizer.tpl.html src/templates/default/static/js/app-shell.js tests/Unit/Ui
git commit -m "refactor: use native Hope UI components"
```

---

### Task 4: Migrar Estado, Servidores, Estadísticas, Historial y Registro

**Files:**
- Modify: `src/templates/default/module/server/status/index.tpl.html`
- Modify: `src/templates/default/module/server/status/cards.tpl.html`
- Modify: `src/templates/default/module/server/statistics/index.tpl.html`
- Modify: `src/templates/default/module/server/statistics/header.tpl.html`
- Modify: `src/templates/default/module/server/server/list.tpl.html`
- Modify: `src/templates/default/module/server/server/update.tpl.html`
- Modify: `src/templates/default/module/server/server/view.tpl.html`
- Modify: `src/templates/default/module/server/history.tpl.html`
- Modify: `src/templates/default/module/server/log.tpl.html`
- Modify: `src/templates/default/static/js/status.js`
- Modify: `src/templates/default/static/js/dashboard.js`
- Modify: `src/templates/default/static/js/history.js`
- Test: `tests/Unit/Server/StatusDashboardTest.php`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: `server.status_tone`, `server.status_label`, imágenes, datos de estadísticas y endpoints XHR existentes.
- Produces: tarjetas nativas con estados semánticos y refresco que reemplaza HTML renderizado por Twig.

- [ ] **Step 1: Reescribir la tarjeta de estado sin clases propias**

Usar un mapa Twig de tonos y atributos `data-*`:

```twig
{% macro server_card(server, compact, label_last_online, label_last_offline, label_last_check, label_rtime) %}
{% set tone = {'online':'success','offline':'danger','warning':'warning','paused':'secondary'}[server.status_tone]|default('secondary') %}
<article class="card h-100 overflow-hidden border-top border-4 border-{{ tone }}" data-server-card data-server-id="{{ server.server_id }}">
    <div class="card-header bg-{{ tone }}{% if tone == 'warning' %} text-dark{% else %} text-white{% endif %} d-flex align-items-center gap-2" role="status" aria-label="Estado: {{ server.status_label }}">
        <span class="badge rounded-pill bg-white text-{{ tone }}" data-server-status-icon aria-hidden="true">{{ server.status_tone == 'online' ? '✓' : (server.status_tone == 'offline' ? '×' : (server.status_tone == 'warning' ? '!' : 'Ⅱ')) }}</span>
        <strong data-server-status-label>{{ server.status_label|upper }}</strong>
    </div>
    <div class="card-body">
        <div class="{% if compact %}row g-3 align-items-center{% endif %}">
            <div class="{% if compact %}col-auto{% else %}text-center mb-3{% endif %}">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-body-tertiary p-3">
                    <img src="{{ server.image_url }}" width="96" height="96" class="img-fluid object-fit-contain" alt="" loading="lazy" decoding="async" data-server-image>
                </span>
            </div>
            <div class="{% if compact %}col min-w-0{% endif %}">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div class="overflow-hidden">
                        <h2 class="h5 mb-1 text-break"><a class="stretched-link text-decoration-none" href="{{ server.url_view|raw }}">{{ server.label }}</a></h2>
                        <small class="text-body-secondary text-break d-block">{{ server.ip }}{% if server.port %}:{{ server.port }}{% endif %}</small>
                    </div>
                    <span class="badge text-bg-{{ tone }}" aria-label="{{ server.status_label }}">{{ server.status_label }}</span>
                </div>
                <dl class="list-group list-group-flush mb-0">
                    <div class="list-group-item px-0 d-flex justify-content-between gap-3"><dt class="small text-body-secondary">{{ label_last_online }}</dt><dd class="mb-0 text-end" data-server-last-online>{{ server.last_online_nice }}</dd></div>
                    {% if server.status_tone == 'online' %}
                    <div class="list-group-item px-0 d-flex justify-content-between gap-3"><dt class="small text-body-secondary">{{ label_last_offline }}</dt><dd class="mb-0 text-end">{{ server.last_offline_nice }} {{ server.last_offline_duration_nice }}</dd></div>
                    <div class="list-group-item px-0 d-flex justify-content-between gap-3"><dt class="small text-body-secondary">{{ label_rtime }}</dt><dd class="mb-0 text-end" data-server-latency>{{ (server.rtime * 1000)|round(2) }} ms</dd></div>
                    {% else %}
                    <div class="list-group-item px-0 d-flex justify-content-between gap-3"><dt class="small text-body-secondary">{{ label_last_check }}</dt><dd class="mb-0 text-end" data-server-last-check>{{ server.last_checked_nice }}</dd></div>
                    {% endif %}
                </dl>
            </div>
        </div>
    </div>
</article>
{% endmacro %}
```

Sustituir `min-w-0` por `overflow-hidden` donde sea necesario; no conservar esa clase propia.

- [ ] **Step 2: Convertir layouts del módulo servidor**

Aplicar estos contratos exactos:

```text
status/index.tpl.html     -> row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4
server/list.tpl.html      -> row row-cols-1 row-cols-lg-2 row-cols-xxl-3 g-4; card h-100
server/update.tpl.html    -> row g-4; cada data-form-section es card mb-4
server/view.tpl.html      -> row g-4; imagen en col-12 col-xl-4; tabs en col-12 col-xl-8
history.tpl.html          -> card + nav nav-pills + ratio ratio-21x9 + overflow-auto
log.tpl.html              -> nav nav-pills + tab-content; eventos como list-group-item
statistics/index.tpl.html -> row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3; gráficos en card
statistics/header.tpl.html-> d-flex flex-wrap align-items-center gap-2
```

Las imágenes usan atributos `width`/`height`, `img-fluid`, `object-fit-contain`, `rounded` y `bg-body-tertiary`. Los canales usan `badge rounded-pill text-bg-primary`; los facts y timelines usan `list-group` en lugar de `server-facts`, `channel-*` y `timeline-*`.

- [ ] **Step 3: Simplificar refresco de estado**

Eliminar `applyCards()` de `status.js`. Después del POST manual, refrescar el fragmento renderizado por Twig:

```javascript
.then(function (result) {
    if (!result.response.ok && !result.payload.busy) {
        throw new Error('Algunas comprobaciones fallaron.');
    }
    return refreshStatus().then(function () {
        if (label) { label.textContent = result.payload.busy ? 'Comprobación ocupada' : 'Estado actualizado'; }
    });
})
```

Así no se mantienen listas duplicadas de clases Bootstrap en JavaScript.

- [ ] **Step 4: Ajustar gráficos e historial a contenedores nativos**

En `dashboard.js` y `history.js`, seleccionar por `data-chart`/IDs existentes, calcular `height` en opciones de ApexCharts y no depender de `dashboard-chart`, `chart-container`, `history-graph` ni `history-panel`. Mantener `JSON.parse`, same-origin y accesibilidad actuales.

- [ ] **Step 5: Ejecutar tests de servidores**

```bash
vendor/bin/phpunit tests/Unit/Server/StatusDashboardTest.php tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
```

Expected: PASS para Estado, Estadísticas, Servidores, Historial y Registro; quedan fallos de otras familias de vistas.

- [ ] **Step 6: Commit de módulos de servidor**

```bash
git add src/templates/default/module/server src/templates/default/static/js/status.js src/templates/default/static/js/dashboard.js src/templates/default/static/js/history.js tests/Unit/Server tests/Unit/Ui
git commit -m "refactor: migrate server views to native Hope UI"
```

---

### Task 5: Migrar Configuración, Sistema, Usuarios, Perfil y Notificaciones

**Files:**
- Modify: `src/templates/default/module/config/config.tpl.html`
- Modify: `src/templates/default/module/config/system.tpl.html`
- Modify: `src/templates/default/module/config/system-updated.tpl.html`
- Modify: `src/templates/default/module/user/user/list.tpl.html`
- Modify: `src/templates/default/module/user/user/update.tpl.html`
- Modify: `src/templates/default/module/user/profile.tpl.html`
- Modify: `src/templates/default/module/user/notification/index.tpl.html`
- Modify: `src/templates/default/static/js/notifications.js`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/BrandingContractTest.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: forms/controllers existentes, uploads seguros, preferencias y repositorio de notificaciones.
- Produces: formularios y colecciones Bootstrap 5 sin fieldsets estilizados globalmente ni clases propias.

- [ ] **Step 1: Convertir el layout de Configuración**

Cambiar el wrapper principal a:

```twig
<div class="row g-4">
    <div class="col-12 col-xl-3">
        <ul class="nav nav-pills flex-xl-column flex-nowrap overflow-auto gap-2" id="config_tab" role="tablist" aria-orientation="vertical">...</ul>
    </div>
    <div class="col-12 col-xl-9">
        <div class="tab-content">...</div>
    </div>
</div>
```

Convertir cada bloque:

```twig
<fieldset class="card mb-4">
    <legend class="card-header h5 float-none w-100 mb-0">{{ label }}</legend>
    <div class="card-body">
        ...controles existentes...
    </div>
</fieldset>
```

El logo usa `row g-3 align-items-center`, imagen `img-thumbnail object-fit-contain` con `width="112" height="112"`, y el input permanece `form-control`. Eliminar `config-*`, `dropzone-*`, `identity-*`, `brand-*`, `appearance-*` y `push-device-card`.

- [ ] **Step 2: Convertir Sistema y diagnósticos**

Usar `row row-cols-1 row-cols-lg-2 g-4`, `card h-100`, `list-group list-group-flush`, `alert alert-*`, `accordion` y `overflow-auto`. El output preformateado queda:

```twig
<pre class="bg-dark text-light rounded p-3 overflow-auto mb-0" style="max-height:24rem">{{ output }}</pre>
```

No usar `style`; en vez de la altura inline, dejar únicamente `overflow-auto` y truncar el servicio a una longitud segura en el modelo existente. El plan no introduce ninguna regla CSS sustituta.

- [ ] **Step 3: Convertir usuarios y perfil**

Aplicar:

```text
user/list.tpl.html        -> row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4; card h-100
user/update.tpl.html      -> card; card-header; card-body; row g-3
profile.tpl.html          -> row g-4; identidad y preferencias en cards separadas
notification/index.tpl.html -> list-group; unread usa border-start border-4 border-primary
```

Avatares y previews usan `avatar-80` o atributos `width="112" height="112"`, `rounded-circle`, `img-thumbnail`, `object-fit-cover`. Contactos usan `list-group`; acciones usan `d-flex flex-wrap gap-2`. Eliminar `user-card`, `user-contact`, `dropzone-*`, `identity-*`, `appearance-*`, `notification-*` propios.

- [ ] **Step 4: Ajustar hooks de notificaciones**

`notifications.js` selecciona `[data-notification-item]`, `[data-notification-mark-read]` y `[data-notification-list]`; al marcar como leída elimina `border-primary` y actualiza `aria-label`, sin usar `notification-unread`.

- [ ] **Step 5: Ejecutar tests de configuración, branding y usuarios**

```bash
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/BrandingContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
```

Expected: PASS para estas familias; quedan únicamente autenticación/instalador y la eliminación física del CSS.

- [ ] **Step 6: Commit de configuración y usuarios**

```bash
git add src/templates/default/module/config src/templates/default/module/user src/templates/default/static/js/notifications.js tests/Unit/Ui
git commit -m "refactor: migrate settings and user views to Hope UI"
```

---

### Task 6: Migrar autenticación, instalador, errores y utilidades

**Files:**
- Modify: `src/templates/default/module/user/login/login.tpl.html`
- Modify: `src/templates/default/module/user/login/forgot.tpl.html`
- Modify: `src/templates/default/module/user/login/reset.tpl.html`
- Modify: `src/templates/default/module/user/login/register.tpl.html`
- Modify: `src/templates/default/module/install/index.tpl.html`
- Modify: `src/templates/default/module/install/main.tpl.html`
- Modify: `src/templates/default/module/install/config_new.tpl.html`
- Modify: `src/templates/default/module/install/config_new_user.tpl.html`
- Modify: `src/templates/default/module/install/config_upgrade.tpl.html`
- Modify: `src/templates/default/module/install/results.tpl.html`
- Modify: `src/templates/default/module/install/success.tpl.html`
- Modify: `src/templates/default/module/error/401.tpl.html`
- Modify: `src/templates/default/util/module/modal.tpl.html`
- Modify: `src/templates/default/util/module/sidebar.tpl.html`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: los mismos campos, CSRF, mensajes y acciones existentes.
- Produces: páginas públicas responsivas compuestas únicamente con layout Bootstrap/HOPE UI.

- [ ] **Step 1: Rehacer autenticación con row/cols nativos**

Usar esta estructura en login, registro, recuperación y reset:

```twig
<div class="container-fluid p-0 min-vh-100">
    <div class="row g-0 min-vh-100">
        <div class="col-12 col-lg-6 d-flex align-items-center justify-content-center p-4 p-md-5">
            <div class="w-100">
                <div class="row justify-content-center">
                    <div class="col-12 col-sm-10 col-md-8 col-lg-10 col-xl-8">
                        <div class="card border-0 shadow-none bg-transparent">
                            <div class="card-body p-0">...formulario...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center bg-primary p-5">
            <img src="src/templates/default/static/hope/images/auth/01.png" class="img-fluid" alt="">
        </div>
    </div>
</div>
```

Logo/avatar usa atributos y utilidades nativas. Eliminar todas las clases `auth-*` y `brand-*`.

- [ ] **Step 2: Rehacer instalador y stepper**

Wrapper:

```twig
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="card shadow-sm">...</div>
        </div>
    </div>
</div>
```

Stepper:

```twig
<ol class="row row-cols-2 row-cols-md-4 g-2 list-unstyled p-3 mb-0 border-bottom" data-install-stepper>
    ...cada paso como <li class="col"><span class="badge ...">...</span></li>...
</ol>
```

Eliminar `install-shell` e `install-stepper` como clases; conservar únicamente `data-install-stepper` para el test/comportamiento.

- [ ] **Step 3: Convertir errores, modal y sidebar de módulo**

401 usa `container py-5`, `row justify-content-center`, `col-md-8 col-xl-6`, `card text-center`. Modal usa atributos Bootstrap 5 existentes. El sidebar de módulo usa `card`, `nav nav-pills flex-column` y `d-flex flex-wrap gap-2`; no usa Bootstrap 4 ni clases respaldadas por el CSS eliminado.

- [ ] **Step 4: Ejecutar tests de todas las vistas**

```bash
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
```

Expected: todos los templates pasan el escaneo de clases; el único fallo restante es que el archivo CSS y sus referencias todavía no se han eliminado.

- [ ] **Step 5: Commit de páginas públicas y utilidades**

```bash
git add src/templates/default/module/user/login src/templates/default/module/install src/templates/default/module/error src/templates/default/util tests/Unit/Ui
git commit -m "refactor: migrate public views to native Hope UI"
```

---

### Task 7: Eliminar el CSS, rotar PWA y automatizar el release

**Files:**
- Delete: `src/templates/default/static/css/hs-monitor.css`
- Modify: `src/templates/default/main/body.tpl.html`
- Modify: `service-worker.js`
- Modify: `README.rst`
- Modify: `CHANGELOG.md`
- Create: `.github/workflows/release-4.3.2-hs.yml`
- Create: `docs/releases/v4.3.2-hs.md`
- Test: `tests/Unit/Pwa/PwaAssetTest.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: versión `PSM_VERSION = 4.3.2-hs` y el commit final de `main`.
- Produces: clientes PWA sin referencia obsoleta y workflow de tag/release reproducible.

- [ ] **Step 1: Borrar el stylesheet y sus referencias**

```bash
git rm src/templates/default/static/css/hs-monitor.css
```

Confirmar que `body.tpl.html` carga solamente:

```twig
<link href="src/templates/default/static/hope/css/hope-ui.min.css?v={{ version|url_encode }}" rel="stylesheet">
<link href="src/templates/default/static/hope/css/dark.min.css?v={{ version|url_encode }}" rel="stylesheet">
<link href="src/templates/default/static/hope/css/customizer.min.css?v={{ version|url_encode }}" rel="stylesheet">
```

- [ ] **Step 2: Rotar la caché PWA**

En `service-worker.js`:

```javascript
const STATIC_CACHE = 'psm-static-4.3.2-hs-r2';
```

Retirar la entrada de `hs-monitor.css` de `STATIC_ASSETS`. No alterar la política network-only de páginas privadas.

- [ ] **Step 3: Escribir notas y workflow de release**

`docs/releases/v4.3.2-hs.md` describe: eliminación del CSS personalizado, migración nativa, correcciones del selector de tema/buscador/sidebar, menú móvil, eliminación de sesión duplicada y verificación PHP 8.5.

Crear `.github/workflows/release-4.3.2-hs.yml`:

```yaml
name: Release 4.3.2-hs

on:
  push:
    branches: [main]

permissions:
  contents: write

jobs:
  release:
    if: ${{ github.event.head_commit.message == 'release: v4.3.2-hs' }}
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: ctype, curl, filter, gd, intl, json, libxml, mbstring, openssl, pdo_mysql, xml, zip
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/phpunit
      - name: Build release archive
        run: |
          mkdir -p dist
          zip -r dist/phpservermon-v4.3.2-hs.zip . \
            -x '.git/*' 'dist/*' '.phpunit.cache/*' 'src/config/config.php'
      - name: Tag and publish release
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          if ! git rev-parse v4.3.2-hs >/dev/null 2>&1; then
            git tag -a v4.3.2-hs "$GITHUB_SHA" -m "PHP Server Monitor v4.3.2-hs"
            git push origin v4.3.2-hs
          fi
          if ! gh release view v4.3.2-hs >/dev/null 2>&1; then
            gh release create v4.3.2-hs dist/phpservermon-v4.3.2-hs.zip \
              --title "PHP Server Monitor v4.3.2-hs" \
              --notes-file docs/releases/v4.3.2-hs.md
          fi
```

- [ ] **Step 4: Actualizar documentación pública**

Agregar a `CHANGELOG.md` una sección `4.3.2-hs` con los cambios de UI y PWA. En `README.rst`, declarar que la interfaz usa las hojas oficiales de HOPE UI sin stylesheet de compatibilidad propio.

- [ ] **Step 5: Ejecutar contratos finales de CSS/PWA**

```bash
vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php tests/Unit/Pwa/PwaAssetTest.php
```

Expected: PASS; no archivo ni referencia `hs-monitor.css`, caché `r2` y cero clases retiradas.

- [ ] **Step 6: Commit de eliminación y release infrastructure**

```bash
git add -A
git commit -m "refactor: remove custom stylesheet completely"
```

---

### Task 8: Verificación completa, despliegue VPS y publicación

**Files:**
- Modify only if verification finds a reproducible defect: files owned by Tasks 2–7.
- No production configuration file is committed.

**Interfaces:**
- Consumes: rama `feature/native-hope-ui-4.3.2-hs` verificada.
- Produces: `main`, VPS de prueba y release `v4.3.2-hs` alineados con el mismo commit.

- [ ] **Step 1: Ejecutar validaciones locales completas**

```bash
composer validate --strict
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit
! test -e src/templates/default/static/css/hs-monitor.css
! grep -R "hs-monitor.css\|sidebar-open\|sidebar-user" -n src service-worker.js tests
! grep -R -E "data-toggle=|data-dismiss=|class=\"[^\"]*(form-row|custom-select|input-group-prepend)" -n src/templates/default
```

Expected: todos los comandos terminan con código 0; PHPUnit reporta cero failures/errors.

- [ ] **Step 2: Revisar el diff de la rama**

```bash
git diff --check main...HEAD
git diff --stat main...HEAD
git log --oneline main..HEAD
```

Verificar que no aparecen `config.php`, credenciales, uploads, logs, vendor ni archivos de runtime.

- [ ] **Step 3: Descubrir DocumentRoot y respaldar producción de prueba**

Leer la contraseña SSH sin escribirla en historial:

```bash
read -rsp 'VPS password: ' SSHPASS; echo; export SSHPASS
VHOST_FILE=$(sshpass -e ssh -o StrictHostKeyChecking=accept-new -p 8615 root@147.135.112.18 \
  "grep -Ril 'ServerName[[:space:]]\+monitoreo.hostingsupremo.org' /etc/httpd/conf /etc/httpd/conf.d | head -n1")
APP_ROOT=$(sshpass -e ssh -p 8615 root@147.135.112.18 \
  "awk 'tolower(\$1)==\"documentroot\" {gsub(/\"/,\"\",\$2); print \$2; exit}' '$VHOST_FILE'")
test -n "$APP_ROOT"
STAMP=$(date +%Y%m%d%H%M%S)
sshpass -e ssh -p 8615 root@147.135.112.18 \
  "tar -C '$APP_ROOT' -czf '/root/phpservermon-before-$STAMP.tgz' ."
```

Expected: `APP_ROOT` contiene el DocumentRoot real y el tar existe en `/root/`.

- [ ] **Step 4: Desplegar preservando datos del VPS**

```bash
sshpass -e rsync -aH --delete \
  --exclude='src/config/config.php' \
  --exclude='logs/' \
  --exclude='uploads/' \
  --exclude='vendor/' \
  --exclude='.git/' \
  -e 'ssh -p 8615' ./ root@147.135.112.18:"$APP_ROOT"/

sshpass -e ssh -p 8615 root@147.135.112.18 "
  cd '$APP_ROOT' &&
  composer install --no-dev --classmap-authoritative --no-interaction &&
  find src -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/phpservermon-lint.log &&
  chown -R root:apache . &&
  find . -type d -exec chmod 0755 {} + &&
  find . -type f -exec chmod 0644 {} + &&
  test -f src/config/config.php &&
  systemctl restart php-fpm httpd &&
  systemctl is-active --quiet php-fpm httpd
"
```

Antes de ajustar permisos de directorios escribibles, registrar y restaurar los permisos previos de `logs`, uploads/media y updater; no otorgar `0777`.

- [ ] **Step 5: Validar HTTP y comportamiento autenticado**

Desde el VPS:

```bash
sshpass -e ssh -p 8615 root@147.135.112.18 "
  curl -fsS -o /tmp/monitor-login.html https://monitoreo.hostingsupremo.org/ &&
  grep -q 'hope-ui.min.css' /tmp/monitor-login.html &&
  ! grep -q 'hs-monitor.css' /tmp/monitor-login.html &&
  curl -fsS -o /dev/null https://monitoreo.hostingsupremo.org/manifest.webmanifest &&
  curl -fsS -o /dev/null https://monitoreo.hostingsupremo.org/service-worker.js
"
```

En navegador, comprobar con el usuario de prueba:

```text
Desktop light/dark: buscador, luna/sol alineados, engranaje, campana, usuario.
Desktop expanded/mini: flecha sobre borde exterior y rotación correcta.
Mobile 360/390/768 px: abrir, Escape, clic exterior, navegar y cerrar sidebar.
Sidebar: no aparece nombre/Perfil/Salir al pie.
Estado: cards, lista, refresh manual y auto refresh.
Estadísticas: rangos y gráficos.
Servidores/Registro/Usuarios/Configurar/Sistema/Actualizar: navegación y formularios.
Perfil/branding: preview y uploads.
PWA: service worker r2 sin asset inexistente.
```

- [ ] **Step 6: Fast-forward de `main` con commit de release**

Crear el commit final que activa el workflow:

```bash
git commit --allow-empty -m "release: v4.3.2-hs"
git push origin feature/native-hope-ui-4.3.2-hs
git push origin feature/native-hope-ui-4.3.2-hs:main
```

En la ejecución mediante el conector GitHub, usar `update_ref` solo después de comparar `main...feature/native-hope-ui-4.3.2-hs` y confirmar fast-forward.

- [ ] **Step 7: Verificar workflow, tag, release y asset**

Confirmar en GitHub Actions que `Release 4.3.2-hs` ejecutó `composer install`, PHPUnit, ZIP y publicación. Verificar:

```text
Tag: v4.3.2-hs apunta al commit de release.
Release: PHP Server Monitor v4.3.2-hs está publicado.
Asset: phpservermon-v4.3.2-hs.zip está disponible.
main: coincide con el commit desplegado en el VPS.
```

- [ ] **Step 8: Commit correctivo solo ante evidencia**

Si una comprobación falla, reproducirla, añadir o ajustar el test específico, implementar un solo cambio, volver a ejecutar el conjunto enfocado y luego toda la suite. No combinar correcciones no relacionadas ni publicar el release hasta recuperar todos los checks verdes.
