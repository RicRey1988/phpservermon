# PHP Server Monitor 4.0.0-hs — Diseño técnico

Estado: diseño conversacional aprobado; especificación escrita pendiente de revisión final.

## Contexto

El fork `RicRey1988/phpservermon` parte de PHP Server Monitor 3.5.2 y actualmente publica `3.5.3-hs`, que conserva el comportamiento original y añade modo oscuro e imágenes para los servidores. La línea 4 inicia la modernización para PHP 8.5 y versiones 8.x posteriores.

La rama oficial `develop` contiene mejoras posteriores a 3.5.2 que deben aprovecharse selectivamente. No puede adoptarse sin ajustes: mantiene requisitos antiguos en el instalador, no incluye una suite automatizada suficiente y conserva dependencias incompatibles con PHP 8.5.

## Objetivos

- Publicar el código de `4.0.0-hs` en `main` sin crear etiqueta ni GitHub Release.
- Exigir PHP 8.5 como mínimo mediante `^8.5` y validar PHP 8.x posterior dentro de lo permitido por Composer.
- Adoptar correcciones y mejoras útiles de `upstream/develop` sin perder el tema oscuro, las imágenes ni la identidad `-hs`.
- Modernizar Composer, dependencias, instalador, cron y notificaciones.
- Mantener email, Telegram, Pushover, Discord, webhook y proveedores SMS vigentes.
- Retirar integraciones y paquetes obsoletos o incompatibles.
- Añadir información PHP segura y exclusiva para administradores.
- Probar una instalación nueva y una actualización desde `3.5.3-hs`.
- Actualizar el VPS a PHP 8.5 con respaldo, etapa aislada y reversión.

## Fuera de alcance

- No se rediseñará por completo la interfaz ni se reemplazará el patrón MVC existente.
- No se añadirá una función de negocio ajena a compatibilidad, mantenimiento o diagnóstico.
- No se prometerá compatibilidad con PHP 9 antes de probarlo; `^8.5` admite PHP 8.5 y versiones 8.x posteriores.
- No se creará etiqueta, archivo de lanzamiento ni GitHub Release en esta etapa.
- Las pruebas no enviarán alertas masivas ni contactarán servicios externos salvo la prueba controlada aprobada.

## Estrategia de base y control de versiones

La implementación partirá del `main` de `3.5.3-hs`. Las mejoras de `upstream/develop` se integrarán en una rama de trabajo y se resolverán explícitamente contra las personalizaciones `-hs`. Se conservará el historial Git; no se reemplazará el repositorio con una copia del VPS.

La constante de aplicación, las pantallas, el instalador y la documentación mostrarán `4.0.0-hs`. `CHANGELOG.md` abrirá la sección `4.0.0-hs — Unreleased`. La guía de contribución establecerá que todas las versiones propias posteriores deben conservar el sufijo `-hs`.

## Plataforma y dependencias

### Producción

- PHP: `^8.5`.
- Componentes Symfony: `^7.4`, rama LTS.
- Symfony HttpClient: `^7.4` para integraciones HTTP comprobables.
- Twig: `^3.28`, rama estable; no se usará Twig 4 mientras sea preliminar.
- PHPMailer: `^7.1`.
- Se conservarán las dependencias LDAP vigentes únicamente si resuelven y pasan las pruebas en PHP 8.5.

### Desarrollo

- PHPUnit 12 para pruebas focalizadas.
- PHPStan 2 para análisis estático de código propio.
- Una herramienta de estilo compatible con PHP 8.5 cuando aporte una comprobación distinta; no duplicará el análisis de PHPStan.

### Extensiones

Composer y el instalador declararán y comprobarán como requeridas `ctype`, `curl`, `filter`, `hash`, `json`, `libxml`, `mbstring`, `openssl`, `pdo`, `pdo_mysql` y `xml`. `intl`, OPcache y extensiones específicas de proveedores serán opcionales y aparecerán como recomendaciones. `sockets` dejará de ser obligatorio cuando se retire Jabber.

### Composer

`composer.lock` permanecerá versionado y se generará con Composer 2.10 o superior. El `composer.phar` antiguo se retirará del repositorio. La documentación usará el Composer instalado por el sistema y describirá una instalación de producción reproducible con `composer install --no-dev --classmap-authoritative`.

## Componentes retirados

- `jaxl/jaxl` y toda la integración Jabber/XMPP: configuración, interfaz, envío, etiquetas y esquema de instalaciones nuevas.
- `paragonie/random_compat`, innecesario en PHP 8.5.
- `php-pushover/php-pushover`; la función Pushover continuará mediante un adaptador HTTP propio.
- El `composer.phar` versionado.
- Proveedores o endpoints adicionales solo se retirarán si una fuente oficial confirma que el servicio dejó de existir o si no pueden operar en PHP 8.5. Cada retiro se documentará en el changelog.

Las actualizaciones existentes serán no destructivas: columnas y valores antiguos de Jabber podrán permanecer sin uso para permitir reversión y evitar pérdida de datos. Las instalaciones nuevas no crearán esos campos.

## Instalador y actualización

El preflight del instalador comprobará:

- PHP 8.5 o superior dentro del rango permitido.
- Extensiones requeridas y PDO MySQL.
- Requisitos reales del `composer.lock`.
- Permisos de escritura necesarios, zona horaria y conectividad de base de datos.
- Que una actualización no sea un downgrade.

La migración `3.5.3-hs` → `4.0.0-hs` conservará usuarios, contraseñas cifradas, servidores, imágenes, historial, registros y configuración compatible. Las operaciones de esquema estarán en transacción cuando MySQL lo permita y producirán mensajes claros si requieren intervención. Un fallo no debe dejar la versión declarada como actualizada.

## Información PHP segura

Se añadirá una pestaña `Configurar → Información PHP`, protegida por el mismo nivel administrativo del módulo de configuración. Un servicio de diagnóstico recopilará una lista explícita de datos permitidos:

- versión PHP, SAPI, sistema operativo y ruta de `php.ini`;
- versión `4.0.0-hs`;
- memoria, tamaño de carga, tiempo máximo y zona horaria;
- estado de OPcache;
- extensiones requeridas y opcionales;
- resultado de requisitos de plataforma de Composer.

La vista no ejecutará `phpinfo()` ni mostrará variables de entorno, superglobales, cookies, encabezados, tokens, credenciales o configuración de base de datos. La información tendrá estados correcto, advertencia y error, con textos en español e inglés.

## Arquitectura de notificaciones

La lógica de transporte se separará del coordinador de estados mediante unidades pequeñas:

- `NotificationMessage`: contenido y metadatos neutrales al canal.
- `Recipient`: datos del destinatario requeridos por el canal.
- `NotificationChannelInterface`: contrato único de envío.
- `DeliveryResult`: resultado `success`, `skipped`, `temporary_failure` o `permanent_failure`, con mensaje seguro.
- `StatusNotifier`: selecciona canales, destinatarios y mensajes, pero no realiza llamadas de red.

El envío manual desde Configurar y el envío automático del cron usarán los mismos adaptadores. Un error en un canal o destinatario no impedirá intentar los restantes.

### HTTP compartido

Telegram, Pushover, Discord y webhook usarán Symfony HttpClient con verificación TLS, límites de conexión y lectura, agente de usuario con versión `-hs` y redacción de secretos. Los timeouts, respuestas `429` y errores `5xx` admitirán como máximo dos reintentos breves. Los errores permanentes no se reintentarán.

### Telegram

- Se usará HTTPS `POST` conforme a Bot API.
- El token solo aparecerá en la ruta construida en memoria y se redactará en excepciones y registros.
- Se validarán `ok`, `description` y el código HTTP.
- El formato HTML se escapará de forma segura y los mensajes superiores al límite de Telegram se dividirán sin perder contenido.
- La opción de prueba enviará al administrador el prefijo `[PRUEBA 4.0.0-hs]`.

### Pushover

El adaptador llamará directamente a la API oficial, conservará prioridades, reintento y expiración aplicables, y validará la respuesta sin depender del paquete antiguo.

### Email, Discord, webhook y SMS

Email se adaptará a PHPMailer 7 y devolverá un `DeliveryResult` por destinatario. Discord y webhook validarán JSON y códigos HTTP mediante el cliente común. Cada proveedor SMS permanecerá aislado; se conservarán los vigentes y se retirarán únicamente los demostrablemente desaparecidos o incompatibles.

Los registros podrán guardar canal, servidor, destinatario interno, estado y explicación saneada. Nunca guardarán tokens, contraseñas SMTP ni URLs completas que contengan secretos.

## Cron

El cron tendrá CLI como modo principal. Su flujo será:

1. Cargar la aplicación y comprobar plataforma.
2. Adquirir un bloqueo atómico exclusivo.
3. Leer servidores y configuración.
4. Procesar cada servidor dentro de un límite de error propio.
5. Actualizar estado e historial.
6. Entregar notificaciones mediante el registro de canales.
7. Emitir resumen y código de salida.
8. Liberar el bloqueo en un bloque garantizado.

El bloqueo evitará ejecuciones simultáneas y se liberará aunque ocurra una excepción. El fallo de un servidor no detendrá los demás. Los argumentos existentes, como estado y timeout, se conservarán cuando sean compatibles.

El webcron quedará desactivado por defecto. Si se activa, requerirá una clave no vacía comparada con `hash_equals()` y una lista IP validada contra la dirección remota. No se confiará en `X-Forwarded-For` salvo que exista configuración explícita de proxy confiable.

## Manejo de errores y seguridad

- Las excepciones de infraestructura se convertirán en resultados controlados en los límites de cada canal o tarea.
- Los mensajes para el usuario serán claros; el registro técnico tendrá contexto sin secretos.
- Los datos remotos se validarán antes de decodificarlos o mostrarlos.
- Las salidas HTML se escaparán salvo fragmentos internos controlados.
- Composer y el código se auditarán contra vulnerabilidades conocidas.
- Se ejecutará un escaneo de secretos antes de publicar.

## Estrategia de pruebas eficiente

Cada prueba cubrirá un riesgo distinto; no se repetirán suites completas sin una razón:

1. **Dependencias:** `composer validate`, instalación desde lock, `composer audit` y `check-platform-reqs` con PHP 8.5.
2. **Código:** una pasada de sintaxis PHP y análisis estático del código propio.
3. **Unidades focalizadas:** adaptadores de notificación, clasificación de errores/reintentos, bloqueo del cron y redacción del diagnóstico PHP.
4. **Integración:** una instalación nueva y una actualización desde una copia saneada de `3.5.3-hs` sobre MariaDB.
5. **Cron:** una ejecución que cubra éxito, servidor fallido y prevención de concurrencia.
6. **Interfaz:** un recorrido por login, estado, imágenes, modo oscuro, configuración e Información PHP.
7. **Externa:** una notificación Telegram real dirigida solo al administrador con el prefijo de prueba. Otros canales solo se probarán realmente si están configurados y no afectan a terceros.

Tras corregir un fallo se repetirá primero la prueba afectada. Al final se realizará una sola verificación completa de la matriz anterior.

## VPS, despliegue y reversión

Antes de cambiar producción se crearán y verificarán respaldos de base de datos, aplicación, configuración Apache y cron. PHP 8.5 se instalará en paralelo y `4.0.0-hs` se desplegará primero en una ubicación y base de datos aisladas.

La prueba aislada confirmará instalador, actualización, interfaz, imágenes, cron y Telegram. Solo entonces se cambiará producción mediante una operación reversible. PHP 7.4, la aplicación anterior y el respaldo de base de datos se conservarán temporalmente hasta terminar las comprobaciones posteriores.

La reversión restaurará la aplicación anterior, la configuración PHP/Apache y, si la migración modificó datos incompatibles, el respaldo de base de datos. No se retirará el camino de reversión en el mismo cambio que activa PHP 8.5.

## Changelog y publicación

`CHANGELOG.md` usará las secciones `Added`, `Changed`, `Fixed`, `Removed` y `Security` bajo `4.0.0-hs — Unreleased`. Incluirá dependencias, requisitos, cambios de instalador, notificaciones, cron, diagnóstico PHP y funciones retiradas.

Después de superar la matriz de pruebas y el escaneo de secretos, los commits se integrarán en `main` y se publicarán en `RicRey1988/phpservermon`. No se creará tag ni Release. El informe final incluirá commit publicado, comprobaciones ejecutadas, versión PHP del VPS y resultado de los smoke tests.

## Criterios de aceptación

- La aplicación se instala y actualiza sin errores en PHP 8.5.
- Composer no informa requisitos de plataforma incumplidos ni vulnerabilidades conocidas.
- El modo oscuro, las imágenes y las funciones principales de monitorización siguen operativas.
- El cron evita concurrencia, procesa fallos de forma aislada y devuelve estados útiles.
- Telegram funciona mediante la ruta compartida entre prueba y cron.
- Email, Pushover, Discord, webhook y SMS vigentes cargan y producen resultados controlados.
- Jabber/JAXL, `random_compat`, el wrapper Pushover antiguo y `composer.phar` ya no forman parte del producto.
- Información PHP solo es accesible por administradores y no expone información sensible.
- La actualización conserva datos de `3.5.3-hs`.
- El VPS ejecuta PHP 8.5 y `4.0.0-hs` con un camino de reversión verificado.
- `main` contiene `CHANGELOG.md` y el código final, sin tag ni GitHub Release.
