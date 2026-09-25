# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es este proyecto

**Monse Party Shop**: tienda en línea de plantillas digitales descargables (decoración de fiestas, invitaciones, etiquetas, kits) hecha en **PHP puro (procedural)**, sin frameworks, sin Composer/npm, sin autoloader y sin sistema de build. Cada `.php` es servido directamente por Apache (XAMPP en desarrollo). No hay tests automatizados, linter ni CI configurados en el repo.

## Comandos

No hay build/lint/test — el flujo de desarrollo es editar PHP y recargar en el navegador.

- **Servir localmente**: colocar/mantener el proyecto dentro de `htdocs` de XAMPP y visitar `http://localhost/TiendaMonsePalomino/` (Apache + PHP, sin `php -S` porque se depende de la reescritura/base de XAMPP tal cual).
- **Base de datos**: importar `database/schema.sql` directamente sobre una base ya creada (el script no hace `CREATE DATABASE`). Incluye datos semilla (categorías, productos de ejemplo, usuario admin `admin` / `MonseAdmin2026!`).
- **No ejecutar `database/schema.sql` dos veces** sobre la misma base: no es idempotente (`CREATE TABLE` sin `IF NOT EXISTS`, `INSERT` de semillas sin guard).

## Arquitectura

### Bootstrap y carga de dependencias

No hay autoloader. Todo se compone con `require_once` explícitos. El punto de entrada común es **`includes/init.php`**, que en orden: arranca la sesión (cookie `httponly`, `samesite=Lax`), y hace `require_once` de `config/database.php` → `includes/settings.php` → `includes/functions.php` → `includes/smtp_mailer.php` → `admin/includes/email_variables.php` → `includes/conekta_client.php` → `includes/stripe_client.php` → `includes/recaptcha_client.php` → `includes/cart.php`. Casi toda página pública/webhook empieza con `require_once __DIR__ . '/includes/init.php'` (directo) y `includes/header.php` también lo hace por su cuenta, así que basta incluir `header.php` en páginas de vitrina. El admin tiene su propio flujo (ver abajo).

### Config y credenciales

`config/database.php` define constantes `DB_HOST/DB_NAME/DB_USER/DB_PASS` **hardcodeadas en texto plano** (apuntan a un host de Hostinger, no a una BD local) y expone `get_db()`, un singleton PDO (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`). No existe `.env` ni variante local: correr el sitio en XAMPP significa conectarse a esa base tal como esté configurada en ese archivo. `config/`, `database/` e `includes/` tienen `.htaccess` con `Require all denied` porque solo se usan vía `require_once`, nunca por request HTTP directo.

### Settings dinámicos (branding, pasarelas, SMTP)

Toda la configuración editable desde el admin vive en la tabla `settings` (clave/valor) y se lee con `get_setting($key, $default)` / `get_all_settings()` (`includes/settings.php`), cacheada en una estática por request. `set_setting()` hace upsert. Aquí viven: branding (nombre, colores, logo), moneda/impuestos, límites de descarga (`download_expiration`, `max_downloads`), y flags/credenciales de SMTP, Conekta y Stripe. Cambiar comportamiento de pagos/correo casi siempre pasa por esta tabla, no por constantes en código.

### Rutas y URLs

`base_url($path)` (`includes/functions.php`) calcula el prefijo de ruta comparando `realpath` del proyecto contra `$_SERVER['DOCUMENT_ROOT']`, para que el sitio funcione igual si vive en la raíz del document root o en un subdirectorio (como en XAMPP: `/TiendaMonsePalomino/`). Todos los enlaces internos deben construirse con `base_url()`, nunca con rutas absolutas hardcodeadas.

### Carrito

El carrito vive en `$_SESSION['cart']` (`product_id => qty`), no en BD (`includes/cart.php`). `cart_items()` vuelve a consultar `products` por los IDs en sesión en cada llamada (para reflejar precio/estado activo actuales), así que un producto desactivado desaparece solo del carrito.

### Checkout y pasarelas de pago

`checkout.php` es un controlador grande de un solo archivo que maneja **dos pasarelas** en paralelo, seleccionables por radio button según lo que esté habilitado en `settings`:

- **Stripe**: crea una Checkout Session hospedada (`stripe_create_checkout_session()` en `includes/stripe_client.php`) y redirige ahí; el pedido queda `pending` hasta que Stripe confirme.
- **Conekta**: tokeniza la tarjeta en el navegador con el SDK JS de Conekta (`Conekta.Token.create`) y luego crea la orden server-side (`conekta_create_order()`); soporta tarjeta, OXXO y SPEI. OXXO/SPEI generan una `payment_reference` (referencia/CLABE) y quedan `pending` hasta pago.
- Si ninguna pasarela real está habilitada, cae a un **modo demo**: el pedido se marca `paid` de inmediato (para poder probar el flujo de descarga sin credenciales reales).

Cada método dentro de cada pasarela se puede activar/desactivar por separado desde Configuración → Pagos: `conekta_card_enabled` / `conekta_oxxo_enabled` / `conekta_spei_enabled` y `stripe_card_enabled` / `stripe_oxxo_enabled` / `stripe_spei_enabled` (todas `'1'` por defecto vía `get_setting($key, '1')` si la fila aún no existe en `settings`, así que no requieren migración). `checkout.php` arma `$payment_options` y, para Stripe, `$stripe_method_types` filtrando por estos toggles; si todos los métodos de una pasarela quedan desactivados, esa pasarela simplemente no aparece como opción.

Ambos clientes de pasarela (`includes/conekta_client.php`, `includes/stripe_client.php`) son wrappers mínimos sobre cURL crudo, sin SDKs oficiales. La inserción de `orders` + `order_items` ocurre dentro de una transacción PDO; el envío de correo de confirmación/pendiente es síncrono, justo después del commit.

### Webhooks = fuente de verdad para "pagado"

`webhook-conekta.php` y `webhook-stripe.php` son quienes realmente marcan una orden como `paid` en el caso general (el `gracias.php` solo hace una confirmación optimista extra cuando el cliente regresa de Stripe con `session_id`, como respaldo de UX, no como fuente de verdad).

- Conekta: **no confía en el payload del webhook**; al recibirlo, vuelve a consultar la API de Conekta con la llave privada (`conekta_get_order()`) para confirmar el estado real antes de marcar `paid`.
- Stripe: verifica la firma HMAC del header `Stripe-Signature` manualmente (`stripe_verify_webhook_signature()`, implementación propia, con tolerancia de repetición de 5 minutos) usando el secreto guardado en `settings`.

### Descargas protegidas

`descargar.php` no depende de sesión de usuario: exige `token` (columna `orders.download_token`, un random de 24 bytes) + `item` (id de `order_items`) + `file` (id de `product_files`), y valida en cadena: orden existe y `status='paid'` → el item pertenece a esa orden → el archivo pertenece a ese producto → no vencido (calculado con `NOW()` del propio servidor MySQL contra `created_at + download_expiration` días, para evitar desfases de reloj del servidor web) → no se superó `max_downloads` por `order_item` (contador `download_count`, incrementado en cada descarga exitosa). Los archivos reales están en `uploads/downloads/`, bloqueada por `.htaccess` (`Require all denied`) para que nadie pueda acceder directo por HTTP sin pasar por esa validación.

- **`rastrear-pedido.php`**: pantalla pública para que el cliente recupere su pedido con **número de pedido + correo** (ambos, no solo el código, para no depender de la entropía del `order_code`) y lo redirige a `gracias.php`. Enlazada desde el pie de página y desde el estado "pedido no encontrado" de `gracias.php`.
- **`admin/order_detail.php`**: el admin puede editar directamente `order_items.download_count` por producto del pedido (bajarlo da más descargas al cliente, subirlo se las bloquea), scoped por `order_id` para no poder tocar items de otro pedido vía POST manipulado.
- **`admin/product_file_preview.php`**: único punto por el que un archivo de `uploads/downloads/` se sirve fuera de `descargar.php` — requiere sesión de admin, y sirve PDF/imágenes rasterizadas `inline` (SVG se fuerza a `attachment` a propósito, por el riesgo de `<script>` embebido en SVGs abiertos como documento de nivel superior).

### Panel de administración (`/admin`)

Layout propio, no comparte `includes/header.php`/`footer.php` de la tienda. Cada página admin hace `require_once __DIR__ . '/includes/auth.php'` + `require_admin_login()` y luego `require_once .../admin_header.php` (que ya asume sesión admin válida) ... `admin_footer.php`. Auth (`admin/includes/auth.php`) usa `admin_users` con bloqueo tras 5 intentos fallidos (15 min, columnas `failed_attempts`/`locked_until`) y `session_regenerate_id(true)` al iniciar sesión con éxito.

Secciones: Productos (con imágenes de galería `product_images` y archivos descargables `product_files`, ambos 1-a-muchos por producto), Categorías (muchos-a-muchos vía `product_categories`), Pedidos, Configuración, **Plantillas de correo**, **Biblioteca de archivos** y **Mensajes de contacto**.

Los 4 campos de "Detalles para la ficha del producto" (`what_includes`, `what_for`, `what_you_need`, `how_to_use`) tienen cada uno una columna gemela `show_*` (TINYINT(1) DEFAULT 1) que el admin controla con un checkbox junto a cada campo en `product_form.php`. `producto.php` arma el array del acordeón con `$product['show_what_includes'] ? $product['what_includes'] : ''` (y análogo para los otros tres) antes del `if (!$content) continue;` que ya existía — así una sección con texto pero con su `show_*` en 0 se oculta igual que si estuviera vacía, sin duplicar la lógica de filtrado.

**Dashboard (`admin/index.php`)**: 4 de las 6 tarjetas KPI del `stat-grid` son enlaces (clase `stat-card-link`, definida en `assets/css/admin.css`) hacia su listado filtrado — Productos activos → `products.php?estado=active`, Categorías → `categories.php?estado=active`, Pedidos pagados/pendientes → `orders.php?estado=paid|pending`; Ingresos totales y Ticket promedio (`total_revenue / total_orders`, con guard contra división entre cero) no enlazan a nada. "Productos más vendidos" (top 5 por unidades, `order_items` `JOIN` `orders WHERE status='paid'`) y "Mejores clientes" no respetan el filtro de periodo de la gráfica de ingresos — son siempre acumulados históricos, a diferencia de la gráfica y el donut de estado que sí usan `$period_start`.

### Dos sistemas de email, unidos por `send_templated_email()`

1. **Plantillas administrables** (tabla `email_templates`, gestionadas en `admin/email_template*.php`): HTML con variables `{{variable}}` que se resuelven combinando un catálogo fijo en código (`admin/includes/email_variables.php`, con categorías como "Información del pedido": `total_pedido`, `productos_pedido`, `fecha_vencimiento_descarga`, `botones_descarga`, `url_rastreo_pedido`; y "Información de pago": `metodo_pago`, `referencia_pago`, `fecha_vencimiento_pago`) con variables custom en la tabla `email_custom_variables`. Editable visualmente (Quill) o como HTML crudo, con vista previa y envío de prueba usando datos de ejemplo (`email_variable_sample_data()`).
2. **Transaccionales en código** (`send_order_confirmation_email()`, `send_payment_pending_email()` en `includes/functions.php`): se disparan automáticamente desde checkout/webhooks/`gracias.php`.

**`send_templated_email($code, $to, $variables, $fallback_subject, $fallback_html)`** (`admin/includes/email_variables.php`) es el único punto de envío para ambos: busca con `email_active_template_by_code($code)` una plantilla **activa** con ese código exacto en `email_templates`; `$variables` acepta un array asociativo o un **string JSON** (se decodifica internamente, con error controlado si el JSON es inválido). Si hay plantilla, renderiza su `subject`/`content_html` con `email_render_variables()` y la envía con `send_html_email()`; si no hay ninguna publicada con ese código, usa `$fallback_subject`/`$fallback_html` (si se pasaron) como respaldo, para que el correo automático nunca dependa de que alguien haya configurado algo en el admin. Si no hay plantilla ni fallback, devuelve `['success'=>false,...]` sin intentar enviar.

Los dos correos transaccionales siguen el mismo patrón: arman sus datos con una función `_variable_data()` propia (`order_ready_email_variable_data()` / `payment_pending_email_variable_data()`) y su propio HTML de respaldo (`email_default_order_ready_html()` / `email_default_payment_pending_html()`), y delegan el envío a `send_templated_email()` con código `COMPRA_LISTA` / `PAGO_PENDIENTE` respectivamente. **Cualquier envío automático nuevo debe seguir este mismo patrón** en vez de armar el HTML o llamar a `send_html_email()` directamente.

`send_payment_reminder_email($order)` es un tercer correo con código `RECORDATORIO_PAGO`, pero **no es automático**: se dispara a mano desde el botón "⏰ Enviar recordatorio de pago" en `admin/order_detail.php` (solo visible si `status === 'pending'`). Reutiliza `payment_pending_email_variable_data()` (mismas variables que `PAGO_PENDIENTE`) y tiene su propio HTML de respaldo (`email_default_payment_reminder_html()`) con un botón "PAGAR MI PEDIDO" hacia `{{url_confirmacion}}`. Es el ejemplo a seguir para un correo pensado para envío manual desde el admin en vez de atado a un evento del sitio.

Un cuarto correo, código `RECUPERAR_PASSWORD_ADMIN`, es la recuperación de contraseña del panel: `admin/forgot_password.php` genera el token con `admin_create_password_reset()` y llama a `send_admin_password_reset_email($admin, $token)` (`includes/functions.php`), que sigue el mismo patrón (`admin_password_reset_email_variable_data()` + HTML de respaldo `email_default_admin_password_reset_html()`); el enlace resultante se consume en `admin/reset_password.php`. **Gotcha frecuente:** `email_active_template_by_code()` solo encuentra plantillas con `status = 'active'` — si en `admin/email_template_form.php` se guarda con "Guardar borrador" en vez de "Guardar plantilla" (publicar), el código sigue usando el HTML de respaldo aunque la plantilla ya tenga contenido editado, sin ningún aviso de que no se está usando.

`{{url_confirmacion}}` no siempre apunta a `gracias.php`: si `order.payment_gateway === 'stripe'`, `payment_pending_email_variable_data()` llama a `stripe_generate_pay_link_for_order($order)` (`includes/functions.php`), que arma los `line_items` desde `order_items` (no desde el carrito, ya no existe a esa altura) y crea una **Checkout Session de Stripe nueva** — la original guardada en `orders.stripe_session_id` puede haber expirado (Stripe las vence a las 24h) — y sobreescribe `stripe_session_id` con la nueva antes de devolver su URL. Para OXXO/SPEI de Conekta no aplica (se pagan fuera del sitio con solo la referencia), así que ahí sigue apuntando a `gracias.php`. Si la llamada a Stripe falla, cae de vuelta a `gracias.php` en vez de romper el envío del correo.

`stripe_create_checkout_session()` (`includes/stripe_client.php`) acepta un `$metadata` opcional (pares clave-valor) que se manda tanto en la sesión como en `payment_intent_data.metadata`, para poder identificar el pedido (código, cliente, productos) directamente desde el Dashboard de Stripe sin cruzarlo con la BD — el metadata de la sesión **no** se copia solo al PaymentIntent (que es lo que aparece en la lista de "Pagos"), por eso se manda por duplicado. Los dos puntos que crean sesiones (`checkout.php` y `stripe_generate_pay_link_for_order()`) arman ese metadata con `pedido` (order_code), `cliente` y `productos`; `stripe_generate_pay_link_for_order()` además agrega `pedido_id` (el id interno).

También acepta un `$description` opcional (vía `payment_intent_data.description`), que a diferencia del metadata sí aparece directo en la lista de "Pagos" del Dashboard sin entrar al detalle. Se calcula con `stripe_payment_description($order_code, $customer_name)` (`includes/stripe_client.php`) — la misma función se usa al crear la sesión y al mostrarla en `admin/order_detail.php`, para que el admin pueda cotejar visualmente el pedido contra Stripe; si se cambia el formato del texto, hay que tocarlo solo ahí y se refleja en ambos lados. Nota: Stripe crea el PaymentIntent de forma perezosa (no al crear la sesión, sino cuando el cliente llega a pagar), así que la descripción no es verificable contra la API sino hasta que eso pasa — el `stripe_request()` sí acepta el parámetro sin error al crear la sesión.

`admin/order_detail.php` tiene una tabla **"Rastreo del pago"** con la pasarela (`orders.payment_gateway`: Conekta/Stripe/demo), el código de rastreo correspondiente (`conekta_order_id` o `stripe_session_id`, según cuál aplique) y, si es Stripe, la descripción calculada arriba.

`admin/includes/email_variables.php` se incluye desde `includes/init.php` (no solo desde `admin/`), porque el flujo de envío automático del sitio público también necesita `send_templated_email()` / `email_render_variables()` / `email_active_template_by_code()`.

Ambos sistemas terminan llamando a `send_email()` / `send_html_email()` (`includes/smtp_mailer.php`), que si `smtp_enabled='1'` en settings usa un **cliente SMTP propio por socket** (EHLO/STARTTLS/AUTH LOGIN/DATA, sin PHPMailer ni ninguna librería) y si no, cae a `mail()` de PHP.

### Formulario de contacto (`contacto.php` → tabla `contact_messages`)

Cada uno de los 8 campos de la pestaña Contacto (`email`, `phone`, `whatsapp`, `business_hours`, `address`, `instagram`, `facebook`, `tiktok`) tiene su propio checkbox "Mostrar en la tienda" en `admin/settings.php`, guardado como `show_contact_<campo>` (`'1'`/`'0'`, default `'1'` vía `get_setting($key, '1')` — no requiere migración). `contacto.php` resuelve cada `$store_*` a `''` si su toggle está apagado, **antes** de los `if ($store_email): ... endif;` que ya existían para ocultar campos vacíos — así un campo con valor pero oculto se comporta exactamente igual que uno vacío, sin duplicar la lógica de renderizado condicional. El valor guardado no se borra al ocultarlo, solo deja de mostrarse.

`contacto.php` hace su propia lógica de POST **antes** de `require includes/header.php` (igual que `checkout.php`), porque puede responder JSON puro: si la petición trae el header `X-Requested-With: XMLHttpRequest` (lo manda el `fetch()` del JS de la página), responde `{success, message, errors}` y hace `exit` sin renderizar HTML; sin JS, cae al flujo normal (re-renderiza la página con los errores/éxito inline), como respaldo de accesibilidad/progresividad.

Antispam en capas dentro del POST handler, evaluadas en este orden (cada una corta antes de intentar la siguiente si ya hay `$errors`):
1. **Honeypot**: campo `website` (input real, oculto por CSS con `.hp-field` — nunca `display:none`, para no delatarlo a bots que sí revisan eso) — si viene lleno, se responde éxito pero **no se inserta nada** en la BD, y se saltan las demás capas (ya se sabe que es un bot).
2. **Sello de tiempo por sesión**: `$_SESSION['contact_form_ts']` se fija al primer `GET` de la página y se compara en el `POST`; menos de 3 segundos = rechazado (server-side, no depende de nada que mande el cliente).
3. **reCAPTCHA v2** (`includes/recaptcha_client.php`, opcional): igual patrón que Stripe/Conekta — toggle + llaves en `settings` (`recaptcha_enabled`, `recaptcha_site_key`, `recaptcha_secret_key`), configurables en Configuración → Contacto. `recaptcha_is_enabled()` exige el toggle **y** ambas llaves no vacías. Si está activo, `contacto.php` carga `https://www.google.com/recaptcha/api.js` y renderiza `<div class="g-recaptcha" data-sitekey="...">`; el JS valida con `grecaptcha.getResponse()` antes de enviar y llama a `grecaptcha.reset()` si el servidor rechaza (el token es de un solo uso). En el backend, `recaptcha_verify($token, $ip)` llama a `POST https://www.google.com/recaptcha/api/siteverify` con cURL crudo (sin SDK, mismo estilo que `stripe_client.php`/`conekta_client.php`). Si el toggle está apagado (por defecto), el formulario sigue protegido por las otras tres capas, solo sin la casilla visible.
4. **Límite por IP**: máximo 3 mensajes por `ip_address` (`$_SERVER['REMOTE_ADDR']`) cada 10 minutos, contra la propia tabla `contact_messages`.

Catálogos compartidos (`contact_reason_options()`, `contact_preferred_contact_options()` en `includes/functions.php`) — los usan tanto el `<select>` público como `admin/contact_messages*.php` para traducir el valor guardado a su etiqueta, así que un motivo/medio nuevo solo se agrega ahí.

Panel admin: **`admin/contact_messages.php`** (listado con filtros/stat cards, mismo patrón que `orders.php`) y **`admin/contact_message_detail.php`** — abrir un mensaje en estado `new` lo pasa a `read` automáticamente (como un inbox), y desde ahí también se puede pasar a `archived`. Notificaciones en `admin/includes/admin_header.php`: badge en el ítem "Mensajes" del sidebar (mismo patrón `notif-dot` que "Pedidos") + una segunda campana en el topnav (`#notifMessagesDropdown`, junto a la de pedidos pendientes) — ambas cuentan `status = 'new'`.

`business_hours` y `address` son settings nuevos (editables en Configuración → Contacto, junto a los que ya existían) que alimentan la columna izquierda de `contacto.php`; si están vacíos, esa fila del bloque de información simplemente no se muestra.

### Biblioteca de archivos vs. archivos de producto

`admin/includes/media_library.php` es un repositorio reutilizable de imágenes/documentos (tabla `media_library`, carpeta `uploads/media/`) pensado para reusar el mismo archivo en varios lugares (config, plantillas de correo, etc.), separado de:
- `uploads/products/` — imágenes propias de cada producto (portada + galería).
- `uploads/downloads/` — los archivos digitales que el cliente compra y descarga.

La subida a la biblioteca valida extensión contra una whitelist (`media_allowed_extensions()`) para bloquear cualquier script ejecutable.

### Convenciones a mantener

- Sin namespaces/clases de dominio: todo son funciones globales definidas una vez y usadas donde haga falta; seguir ese estilo en vez de introducir OOP/autoload.
- Salida siempre escapada con `e()` (`htmlspecialchars` con `ENT_QUOTES`) en las vistas.
- Consultas SQL con PDO preparado (`:param`) en todos lados; no hay ORM.
- CSRF: formularios sensibles (checkout, forms del admin) usan `csrf_token()`/`csrf_check()` contra `$_SESSION['csrf_token']`.
- Mensajes flash vía `flash_set()`/`flash_get()` en sesión, consumidos una vez por `header.php`/`admin_header.php`.
