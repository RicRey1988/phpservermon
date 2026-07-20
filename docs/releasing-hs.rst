Publicación firmada de Hosting Supremo
======================================

Los releases ``-hs`` se crean únicamente mediante el flujo manual ``Release HS``. El flujo toma un tag existente,
ejecuta Composer, PHPUnit y PHPStan, construye el paquete de producción y publica tres assets con nombres exactos:
``phpservermon-VERSION.zip``, ``phpservermon-VERSION.json`` y ``phpservermon-VERSION.json.sig``.

El JSON usa UTF-8, claves en el orden documentado, sin espacios y con un salto de línea final. La firma separada es
RSA-3072/SHA-256 en Base64. La clave privada existe sólo en el secreto de Actions
``RELEASE_SIGNING_PRIVATE_KEY``; nunca debe copiarse al repositorio ni al VPS. La clave pública fijada en el código
se usa para verificar los bytes exactos del manifiesto antes de aceptar el SHA-256 del archivo.

Rotación de clave
-----------------

Para rotar la clave, genere un nuevo par RSA-3072 fuera del repositorio, compruebe una firma local, actualice primero
la clave pública mediante una versión instalada de confianza y después reemplace el secreto de Actions. No elimine la
clave anterior hasta completar la transición. Ante sospecha de exposición, deshabilite el flujo, revoque el secreto,
retire cualquier release afectado y publique el incidente antes de emitir una nueva clave.
