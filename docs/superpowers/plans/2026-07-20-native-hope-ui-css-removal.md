# PHP Server Monitor 4.3.2-hs Native HOPE UI Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar completamente `src/templates/default/static/css/hs-monitor.css`, migrar todas las vistas a contratos nativos de HOPE UI/Bootstrap 5 y corregir el buscador, selector de tema, flecha del sidebar, sesión duplicada y navegación móvil.

**Architecture:** HOPE UI seguirá siendo la única capa visual mediante `hope-ui.min.css`, `dark.min.css` y `customizer.min.css`. Las plantillas compondrán tarjetas, grids, formularios, navegación, estados, imágenes y vistas responsivas con clases nativas; los hooks de comportamiento pasarán a atributos `data-*`. `app-shell.js` complementará el toggle nativo solo para sincronizar accesibilidad y cierre móvil.

**Tech Stack:** PHP 8.5+, Twig 3.28, Symfony 7.4, PHPUnit 12, HOPE UI, Bootstrap 5, vanilla JavaScript, Apache y MariaDB.

## Global Constraints

- La versión permanece en `4.3.2-hs`; tag y release usan `v4.3.2-hs`.
- Solo se cargan `hope-ui.min.css`, `dark.min.css` y `customizer.min.css`.
- `src/templates/default/static/css/hs-monitor.css` deja de existir.
- No se agrega otro CSS, un bloque `<style>` ni atributos `style` como sustituto.
- Clases usadas solo por JavaScript se reemplazan por `data-*`.
- No hay cambios de esquema de base de datos.
- Se preservan autenticación, PWA, notificaciones, gráficos, updater, branding, avatar, diagnósticos y apariencia.
- Credenciales y configuración específica del VPS nunca se escriben en Git ni en logs de CI.
- Todas las páginas funcionan en claro/oscuro y en escritorio, tableta y móvil.
- Implementación en `feature/native-hope-ui-4.3.2-hs`; `main` se actualiza únicamente con toda la verificación verde.

---

### Task 1: Crear contratos de regresión para CSS y shell

**Files:**
- Create: `tests/Unit/Ui/NativeHopeUiContractTest.php`
- Modify: `tests/Unit/Ui/ModernViewContractTest.php`
- Modify: `tests/Unit/Server/StatusDashboardTest.php`
- Modify: `tests/Unit/Pwa/PwaAssetTest.php`

**Interfaces:**
- Consumes: archivos Twig, `app-shell.js` y `service-worker.js`.
- Produces: contratos que bloquean el CSS eliminado, las clases retiradas y el sidebar móvil antiguo.

- [ ] **Step 1: Escribir el test fallido principal**

Crear `tests/Unit/Ui/NativeHopeUiContractTest.php`:

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
        foreach (['hope-ui.min.css', 'dark.min.css', 'customizer.min.css'] as $asset) {
            self::assertStringContainsString($asset, $body);
        }
        self::assertStringNotContainsString('hs-monitor.css', $body . $worker);
        self::assertStringNotContainsString('<style', $body);
        self::assertStringNotContainsString(' style=', $this->allTemplateContents());
    }

    public function testShellUsesNativeSidebarSearchAndThemeContracts(): void
    {
        $body = $this->read('src/templates/default/main/body.tpl.html');
        $navbar = $this->read('src/templates/default/main/app-navbar.tpl.html');
        $menu = $this->read('src/templates/default/main/menu.tpl.html');
        $javascript = $this->read('src/templates/default/static/js/app-shell.js');

        self::assertStringContainsString('sidebar sidebar-default sidebar-white sidebar-base', $body);
        self::assertStringContainsString('class="sidebar-toggle" data-toggle="sidebar"', $body);
        self::assertStringContainsString('input-group search-input', $navbar);
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

    public function testCustomPresentationClassesAreGone(): void
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
                "/class=[\"'][^\"']*\\b" . preg_quote($class, '/') . "\\b[^\"']*[\"']/",
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

- [ ] **Step 2: Cambiar tests existentes al nuevo resultado**

En `ModernViewContractTest.php`, retirar lecturas/aserciones de `hs-monitor.css` y exigir que `icons.tpl.html` use `icon-20` y no `hope-icon`.

En `StatusDashboardTest.php`, sustituir aserciones de `server-image-box`, `status-card-title`, `status-banner` y CSS fijo por:

```php
self::assertStringContainsString('data-server-card', $cards);
self::assertStringContainsString('data-server-image', $cards);
self::assertStringContainsString('border-4', $cards);
self::assertStringContainsString('data-server-status-label', $cards);
self::assertStringNotContainsString('status-banner', $cards);
self::assertStringNotContainsString('hs-monitor.css', $body);
```

En `PwaAssetTest.php` exigir:

```php
self::assertStringContainsString("psm-static-4.3.2-hs-r2", $worker);
self::assertStringNotContainsString('hs-monitor.css', $worker);
```

- [ ] **Step 3: Ejecutar y confirmar fallo**

```bash
vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Server/StatusDashboardTest.php tests/Unit/Pwa/PwaAssetTest.php
```

Expected: FAIL porque el CSS y las clases aún existen.

- [ ] **Step 4: Commit de tests**

```bash
git add tests/Unit/Ui tests/Unit/Server/StatusDashboardTest.php tests/Unit/Pwa/PwaAssetTest.php
git commit -m "test: define native Hope UI contracts"
```

---

### Task 2: Migrar shell, buscador, tema y sidebar móvil

**Files:**
- Modify: `src/templates/default/main/icons.tpl.html`
- Modify: `src/templates/default/main/body.tpl.html`
- Modify: `src/templates/default/main/app-navbar.tpl.html`
- Modify: `src/templates/default/main/menu.tpl.html`
- Modify: `src/templates/default/static/js/app-shell.js`
- Modify: `src/psm/Module/AbstractController.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: `appearance.*`, datos de navbar y el toggle de `hope-ui.js`.
- Produces: `sidebar-mini` como único estado; iconos de tema con `d-none`; sidebar sin sesión duplicada.

- [ ] **Step 1: Convertir el macro de iconos**

En `icons.tpl.html`:

```twig
<svg class="icon-20 {{ className|default('') }}" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
```

- [ ] **Step 2: Adoptar sidebar upstream en `body.tpl.html`**

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
        <button type="button" class="sidebar-toggle" data-toggle="sidebar" data-active="true" aria-label="Contraer menú" aria-expanded="true"><i class="icon">{{ hope.icon('arrow-left') }}</i></button>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar"><div class="sidebar-list">{{ html_menu|raw }}</div></div>
    <div class="sidebar-footer"></div>
</aside>
```

Eliminar el link a `hs-monitor.css` en una tarea posterior, pero desde ahora retirar `no-menu`, `skip-link`, `app-footer`, `app-content` y `module-actions`; usar `visually-hidden-focusable`, `d-flex`, `flex-column`, `min-vh-100`, `py-4`, `border-top` y `bg-body`.

- [ ] **Step 3: Corregir navbar, buscador y selector de tema**

En `app-navbar.tpl.html`:

```twig
<button type="button" class="sidebar-toggle" data-toggle="sidebar" aria-label="Abrir menú" aria-expanded="false"><i class="icon">{{ hope.icon('menu') }}</i></button>
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

Usar `avatar-40`, `rounded-pill`, `object-fit-cover`, `d-flex`, `align-items-center` y `justify-content-center` para avatar/logo.

- [ ] **Step 4: Eliminar sesión inferior del sidebar**

Dejar `menu.tpl.html` con un único `<ul id="sidebar-menu">` de navegación primaria. En `AbstractController::createHTMLMenu()` retirar `label_profile`, `label_logout`, `url_profile`, `url_logout` y `label_usermenu`.

- [ ] **Step 5: Sincronizar accesibilidad y cierre móvil**

Agregar a `app-shell.js`:

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
    sidebar.querySelectorAll('#sidebar-menu a').forEach(function (link) { link.addEventListener('click', closeMobile); });
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
        var lightAction = resolved === 'dark';
        var darkIcon = button.querySelector('[data-theme-icon="dark"]');
        var lightIcon = button.querySelector('[data-theme-icon="light"]');
        if (darkIcon) { darkIcon.classList.toggle('d-none', lightAction); }
        if (lightIcon) { lightIcon.classList.toggle('d-none', !lightAction); }
        var label = lightAction ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    });
}
```

Llamar `syncThemeIcons(resolved)` en `initializeTheme().apply()` e `initializeSidebarAccessibility()` en `DOMContentLoaded`. Eliminar `sidebar-open`.

- [ ] **Step 6: Verificar y commit**

```bash
vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php --filter Shell
git add src/templates/default/main src/templates/default/static/js/app-shell.js src/psm/Module/AbstractController.php tests/Unit/Ui/NativeHopeUiContractTest.php
git commit -m "fix: adopt native Hope UI application shell"
```

---

### Task 3: Migrar macros, componentes y personalizador

**Files:**
- Modify: `src/templates/default/main/components.tpl.html`
- Modify: `src/templates/default/main/macros.tpl.html`
- Modify: `src/templates/default/main/appearance-customizer.tpl.html`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/NativeHopeUiContractTest.php`

**Interfaces:**
- Consumes: macros Twig y eventos `hope:setting-selected`.
- Produces: controles reutilizables sin clases respaldadas por CSS propio.

- [ ] **Step 1: Convertir dropzone y empty state**

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

- [ ] **Step 2: Retirar Bootstrap 4 de macros**

`input_select_monitoring` usa `row g-3 align-items-center mb-3`, label `col-md-3` y control `col-md-9`. En `input_field`, reemplazar `input-group-prepend` por un `<span class="input-group-text">` posterior al input. `table_search` queda como `input-group mb-3` con `data-card-search`, sin `searchbar` ni `search_input`.

- [ ] **Step 3: Rehacer personalizador con grid Bootstrap**

Cada grupo usa `row g-2`, `col-*`, `btn btn-outline-secondary w-100 h-100` e imágenes `img-fluid rounded border mb-2`. El color usa botones de texto:

```twig
{% set tones = {'default':'primary','blue':'info','gray':'secondary','red':'danger','yellow':'warning','pink':'primary'} %}
<div class="row row-cols-2 row-cols-sm-3 g-2">
{% for value, label in {'default':'Original','blue':'Azul','gray':'Gris','red':'Rojo','yellow':'Naranja','pink':'Rosa'} %}
<div class="col"><button type="button" class="btn btn-outline-{{ tones[value] }} w-100{% if appearance.accent == value %} active{% endif %}" data-preference="accent" data-value="{{ value }}" aria-pressed="{{ appearance.accent == value ? 'true' : 'false' }}">{{ label }}</button></div>
{% endfor %}
</div>
```

Conservar el botón oficial `btn-fixed-end`; retirar `btn-setting` y todas las clases propias del personalizador.

- [ ] **Step 4: Verificar y commit**

```bash
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
git add src/templates/default/main/components.tpl.html src/templates/default/main/macros.tpl.html src/templates/default/main/appearance-customizer.tpl.html tests/Unit/Ui
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
- Consumes: `server.status_tone`, `server.status_label`, imágenes, estadísticas y endpoints XHR.
- Produces: tarjetas semánticas y refresco por HTML renderizado por Twig.

- [ ] **Step 1: Reescribir tarjeta de estado**

Usar mapa Twig `online→success`, `offline→danger`, `warning→warning`, `paused→secondary`; outer card `card h-100 overflow-hidden border-top border-4 border-{{ tone }}` con `data-server-card`; header `card-header bg-{{ tone }}`; imagen dentro de `d-inline-flex ... bg-body-tertiary p-3` con `data-server-image`; facts como `list-group list-group-flush`. Eliminar todas las clases `status-*`, `server-image-box`, `server-facts`, `status-card-title` y `min-w-0`.

- [ ] **Step 2: Convertir layouts de servidor**

```text
status/index.tpl.html      row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4
server/list.tpl.html       row row-cols-1 row-cols-lg-2 row-cols-xxl-3 g-4; card h-100
server/update.tpl.html     row g-4; cada data-form-section dentro de card mb-4
server/view.tpl.html       row g-4; imagen col-12 col-xl-4; tabs col-12 col-xl-8
history.tpl.html           card + nav nav-pills + ratio ratio-21x9 + overflow-auto
log.tpl.html               nav nav-pills + tab-content + list-group
statistics/index.tpl.html  row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3; gráficos en card
statistics/header.tpl.html d-flex flex-wrap align-items-center gap-2
```

Imágenes: atributos `width`/`height`, `img-fluid`, `object-fit-contain`, `rounded`, `bg-body-tertiary`. Canales: `badge rounded-pill text-bg-primary`. Timelines/facts: `list-group`.

- [ ] **Step 3: Simplificar refresco**

Eliminar `applyCards()` de `status.js`. Después del POST manual, ejecutar `refreshStatus()` y reemplazar `[data-status-board]` con el fragmento Twig. Mantener labels de ocupado/error y `aria-busy`.

En `dashboard.js` y `history.js`, seleccionar por IDs o `data-chart`, configurar altura desde ApexCharts y no depender de `dashboard-chart`, `chart-container`, `history-graph` o `history-panel`.

- [ ] **Step 4: Verificar y commit**

```bash
vendor/bin/phpunit tests/Unit/Server/StatusDashboardTest.php tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
git add src/templates/default/module/server src/templates/default/static/js/status.js src/templates/default/static/js/dashboard.js src/templates/default/static/js/history.js tests/Unit
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
- Consumes: forms/controllers, uploads, preferencias y repositorio de notificaciones.
- Produces: formularios y colecciones Bootstrap 5 sin clases propias.

- [ ] **Step 1: Convertir Configuración**

Wrapper `row g-4`; navegación en `col-12 col-xl-3` con `nav nav-pills flex-xl-column flex-nowrap overflow-auto gap-2`; tab-content en `col-12 col-xl-9`. Cada fieldset:

```twig
<fieldset class="card mb-4">
    <legend class="card-header h5 float-none w-100 mb-0">{{ label }}</legend>
    <div class="card-body">...controles...</div>
</fieldset>
```

Logo: `row g-3 align-items-center`, imagen `img-thumbnail object-fit-contain` con `width="112" height="112"`. Retirar `config-*`, `dropzone-*`, `identity-*`, `brand-*`, `appearance-*` y `push-device-card`.

- [ ] **Step 2: Convertir Sistema**

Diagnósticos usan `row row-cols-1 row-cols-lg-2 g-4`, `card h-100`, `list-group`, `alert`, `accordion` y `pre class="bg-dark text-light rounded p-3 overflow-auto mb-0"`. No añadir altura inline; el contenido permanece desplazable según el comportamiento nativo del viewport.

- [ ] **Step 3: Convertir usuarios/perfil/notificaciones**

```text
user/list.tpl.html          row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4; card h-100
user/update.tpl.html        card + card-header + card-body + row g-3
profile.tpl.html            row g-4; identidad y preferencias en cards separadas
notification/index.tpl.html list-group; no leída con border-start border-4 border-primary
```

Avatares: `avatar-80` o `width="112" height="112"`, `rounded-circle`, `img-thumbnail`, `object-fit-cover`. Contactos: `list-group`. Acciones: `d-flex flex-wrap gap-2`.

`notifications.js` usa `[data-notification-item]`, `[data-notification-mark-read]` y `[data-notification-list]`; al marcar leída elimina `border-primary`, sin `notification-unread`.

- [ ] **Step 4: Verificar y commit**

```bash
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/BrandingContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
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
- Consumes: mismos campos, CSRF, mensajes y acciones.
- Produces: páginas públicas responsivas sin clases propias.

- [ ] **Step 1: Rehacer autenticación con grid nativo**

```twig
<div class="container-fluid p-0 min-vh-100">
    <div class="row g-0 min-vh-100">
        <div class="col-12 col-lg-6 d-flex align-items-center justify-content-center p-4 p-md-5">
            <div class="w-100"><div class="row justify-content-center"><div class="col-12 col-sm-10 col-md-8 col-lg-10 col-xl-8">
                <div class="card border-0 shadow-none bg-transparent"><div class="card-body p-0">...formulario...</div></div>
            </div></div></div>
        </div>
        <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center bg-primary p-5">
            <img src="src/templates/default/static/hope/images/auth/01.png" class="img-fluid" alt="">
        </div>
    </div>
</div>
```

Aplicar a login, registro, forgot y reset; retirar `auth-*` y `brand-*`.

- [ ] **Step 2: Rehacer instalador**

Wrapper `container py-5 > row justify-content-center > col-12 col-xl-10 > card shadow-sm`. Stepper:

```twig
<ol class="row row-cols-2 row-cols-md-4 g-2 list-unstyled p-3 mb-0 border-bottom" data-install-stepper>...</ol>
```

No usar clases `install-shell` o `install-stepper`.

- [ ] **Step 3: Convertir 401/modal/sidebar de módulo**

401 usa `container py-5`, `row justify-content-center`, `col-md-8 col-xl-6`, `card text-center`. Modal conserva atributos Bootstrap 5. Sidebar de módulo usa `card`, `nav nav-pills flex-column` y `d-flex flex-wrap gap-2`.

- [ ] **Step 4: Verificar y commit**

```bash
vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/NativeHopeUiContractTest.php
git add src/templates/default/module/user/login src/templates/default/module/install src/templates/default/module/error src/templates/default/util tests/Unit/Ui
git commit -m "refactor: migrate public views to native Hope UI"
```

---

### Task 7: Borrar CSS, rotar PWA y preparar release

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
- Consumes: `PSM_VERSION = 4.3.2-hs`.
- Produces: PWA sin asset obsoleto y workflow reproducible de tag/release.

- [ ] **Step 1: Borrar stylesheet y link**

```bash
git rm src/templates/default/static/css/hs-monitor.css
```

`body.tpl.html` conserva solo:

```twig
<link href="src/templates/default/static/hope/css/hope-ui.min.css?v={{ version|url_encode }}" rel="stylesheet">
<link href="src/templates/default/static/hope/css/dark.min.css?v={{ version|url_encode }}" rel="stylesheet">
<link href="src/templates/default/static/hope/css/customizer.min.css?v={{ version|url_encode }}" rel="stylesheet">
```

- [ ] **Step 2: Rotar service worker**

Cambiar a `const STATIC_CACHE = 'psm-static-4.3.2-hs-r2';` y retirar la entrada `hs-monitor.css` de `STATIC_ASSETS`. No cambiar la política network-only de páginas privadas.

- [ ] **Step 3: Crear notas y workflow**

`docs/releases/v4.3.2-hs.md` documenta migración nativa, selector de tema/buscador/sidebar, móvil, sesión duplicada y PHP 8.5.

`.github/workflows/release-4.3.2-hs.yml`:

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
        with: {fetch-depth: 0}
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: ctype, curl, filter, gd, intl, json, libxml, mbstring, openssl, pdo_mysql, xml, zip
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/phpunit
      - run: |
          mkdir -p dist
          zip -r dist/phpservermon-v4.3.2-hs.zip . -x '.git/*' 'dist/*' '.phpunit.cache/*' 'src/config/config.php'
      - name: Tag and publish
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          if ! git rev-parse v4.3.2-hs >/dev/null 2>&1; then
            git tag -a v4.3.2-hs "$GITHUB_SHA" -m "PHP Server Monitor v4.3.2-hs"
            git push origin v4.3.2-hs
          fi
          if ! gh release view v4.3.2-hs >/dev/null 2>&1; then
            gh release create v4.3.2-hs dist/phpservermon-v4.3.2-hs.zip --title "PHP Server Monitor v4.3.2-hs" --notes-file docs/releases/v4.3.2-hs.md
          fi
```

- [ ] **Step 4: Documentar, verificar y commit**

```bash
vendor/bin/phpunit tests/Unit/Ui/NativeHopeUiContractTest.php tests/Unit/Pwa/PwaAssetTest.php
git add -A
git commit -m "refactor: remove custom stylesheet completely"
```

Expected: ambos tests PASS; no archivo ni referencia `hs-monitor.css`.

---

### Task 8: Verificación completa, despliegue VPS y publicación

**Files:**
- Modify only after a reproducible failed check: files owned by Tasks 2–7.
- Never modify/commit production `src/config/config.php`.

**Interfaces:**
- Consumes: rama verificada.
- Produces: `main`, VPS y release `v4.3.2-hs` en el mismo commit.

- [ ] **Step 1: Validación completa**

```bash
composer validate --strict
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit
! test -e src/templates/default/static/css/hs-monitor.css
! grep -R "hs-monitor.css\|sidebar-open\|sidebar-user" -n src service-worker.js tests
! grep -R -E "data-toggle=|data-dismiss=|class=\"[^\"]*(form-row|custom-select|input-group-prepend)" -n src/templates/default
```

Expected: código 0 y cero failures/errors.

- [ ] **Step 2: Revisar diff**

```bash
git diff --check main...HEAD
git diff --stat main...HEAD
git log --oneline main..HEAD
```

No deben aparecer config, credenciales, uploads, logs, vendor ni runtime.

- [ ] **Step 3: Descubrir DocumentRoot y respaldar VPS**

```bash
read -rsp 'VPS password: ' SSHPASS; echo; export SSHPASS
VHOST_FILE=$(sshpass -e ssh -o StrictHostKeyChecking=accept-new -p 8615 root@147.135.112.18 "grep -Ril 'ServerName[[:space:]]\+monitoreo.hostingsupremo.org' /etc/httpd/conf /etc/httpd/conf.d | head -n1")
APP_ROOT=$(sshpass -e ssh -p 8615 root@147.135.112.18 "awk 'tolower(\$1)==\"documentroot\" {gsub(/\"/,\"\",\$2); print \$2; exit}' '$VHOST_FILE'")
test -n "$APP_ROOT"
STAMP=$(date +%Y%m%d%H%M%S)
sshpass -e ssh -p 8615 root@147.135.112.18 "tar -C '$APP_ROOT' -czf '/root/phpservermon-before-$STAMP.tgz' ."
```

- [ ] **Step 4: Desplegar preservando datos**

```bash
sshpass -e rsync -aH --delete --exclude='src/config/config.php' --exclude='logs/' --exclude='uploads/' --exclude='vendor/' --exclude='.git/' -e 'ssh -p 8615' ./ root@147.135.112.18:"$APP_ROOT"/
sshpass -e ssh -p 8615 root@147.135.112.18 "cd '$APP_ROOT' && composer install --no-dev --classmap-authoritative --no-interaction && find src -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/phpservermon-lint.log && test -f src/config/config.php && systemctl restart php-fpm httpd && systemctl is-active --quiet php-fpm httpd"
```

Registrar antes y restaurar después propietario/modo de directorios escribibles; nunca usar `0777`.

- [ ] **Step 5: Validar HTTP y navegador**

```bash
sshpass -e ssh -p 8615 root@147.135.112.18 "curl -fsS -o /tmp/monitor-login.html https://monitoreo.hostingsupremo.org/ && grep -q 'hope-ui.min.css' /tmp/monitor-login.html && ! grep -q 'hs-monitor.css' /tmp/monitor-login.html && curl -fsS -o /dev/null https://monitoreo.hostingsupremo.org/manifest.webmanifest && curl -fsS -o /dev/null https://monitoreo.hostingsupremo.org/service-worker.js"
```

Comprobar en navegador: claro/oscuro, alineación luna/sol, buscador, flecha expanded/mini, menú 360/390/768 px, Escape/clic exterior/navegación, ausencia de sesión inferior, Estado/refrescos, Estadísticas, Servidores, Registro, Usuarios, Configuración, Sistema, Actualizar, Perfil/branding y PWA.

- [ ] **Step 6: Publicar main y release**

Crear commit final `release: v4.3.2-hs`, comparar `main...feature/native-hope-ui-4.3.2-hs` y hacer fast-forward de `main` mediante GitHub. El workflow crea tag, release y `phpservermon-v4.3.2-hs.zip`.

- [ ] **Step 7: Verificar publicación**

Confirmar que tag, release, asset, `main` y despliegue apuntan al mismo SHA. Ante cualquier fallo: reproducir, añadir/ajustar test, implementar un solo cambio y repetir suite completa antes de volver a publicar.
