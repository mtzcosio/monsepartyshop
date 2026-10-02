<?php
$page_title = 'Aviso de privacidad';
require_once __DIR__ . '/includes/header.php';

// Datos del responsable: salen de Configuración (no dependen de los toggles "Mostrar en
// la tienda" de contacto.php, porque el aviso de privacidad debe identificar siempre al
// responsable y un medio para ejercer derechos ARCO).
$privacy_store = get_setting('store_name', 'Monse Party Shop');
$privacy_email = get_setting('email', '');
$privacy_address = get_setting('address', '');
$privacy_whatsapp = get_setting('whatsapp', '');
$privacy_recaptcha = recaptcha_is_enabled();
$privacy_updated = '29 de septiembre de 2026';
$contact_link = base_url('contacto.php');
?>
<style>
    .legal-doc h2 { font-size: 22px; margin: 36px 0 12px; scroll-margin-top: 90px; }
    .legal-doc h2:first-of-type { margin-top: 8px; }
    .legal-doc h3 { font-size: 17px; margin: 20px 0 8px; }
    .legal-doc p, .legal-doc li { line-height: 1.7; }
    .legal-doc ul, .legal-doc ol { padding-left: 22px; margin: 8px 0 14px; }
    .legal-doc li { margin-bottom: 6px; }
    .legal-meta { font-size: 14px; opacity: 0.75; margin-bottom: 20px; }
    .legal-toc { background: linear-gradient(135deg, #FFF0F4, #F3ECFB); border-radius: var(--radius-md, 14px); padding: 18px 22px; margin: 0 0 28px; }
    .legal-toc strong { display: block; margin-bottom: 8px; }
    .legal-toc ol { margin: 0; columns: 2; column-gap: 28px; }
    .legal-toc a { text-decoration: none; }
    .legal-toc a:hover { text-decoration: underline; }
    .legal-table-wrap { overflow-x: auto; margin: 12px 0 18px; }
    .legal-table { width: 100%; border-collapse: collapse; font-size: 14.5px; min-width: 520px; }
    .legal-table th, .legal-table td { text-align: left; vertical-align: top; padding: 10px 12px; border-bottom: 1px solid rgba(75, 68, 83, 0.12); }
    .legal-table th { background: rgba(75, 68, 83, 0.05); font-weight: 600; }
    .legal-note { border-left: 4px solid var(--primary-color); background: rgba(255, 111, 145, 0.07); padding: 12px 16px; border-radius: 6px; margin: 14px 0; }
    @media (max-width: 640px) {
        .legal-toc ol { columns: 1; }
        .legal-doc h2 { font-size: 20px; }
    }
</style>

<section class="section">
    <div class="container" style="max-width:860px;">
        <h1 class="section-title">Aviso de privacidad integral</h1>
        <div class="card-box legal-doc">
            <p class="legal-meta">Última actualización: <?= e($privacy_updated) ?></p>

            <p>En <strong><?= e($privacy_store) ?></strong> cuidamos tu información tanto como cuidamos el diseño de cada plantilla. Este aviso de privacidad explica, de forma clara y completa, qué datos personales recabamos, para qué los usamos, con quién los compartimos y cómo puedes ejercer tus derechos, en cumplimiento de la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (en adelante, la "Ley") y demás normativa aplicable en los Estados Unidos Mexicanos.</p>

            <nav class="legal-toc" aria-label="Contenido del aviso">
                <strong>Contenido</strong>
                <ol>
                    <li><a href="#responsable">Responsable de tus datos</a></li>
                    <li><a href="#datos">Datos que recabamos</a></li>
                    <li><a href="#finalidades">Para qué usamos tus datos</a></li>
                    <li><a href="#pagos">Datos de pago</a></li>
                    <li><a href="#terceros">Con quién compartimos tus datos</a></li>
                    <li><a href="#cookies">Cookies y tecnologías similares</a></li>
                    <li><a href="#conservacion">Cuánto tiempo conservamos tus datos</a></li>
                    <li><a href="#seguridad">Cómo protegemos tus datos</a></li>
                    <li><a href="#arco">Tus derechos (ARCO)</a></li>
                    <li><a href="#revocacion">Revocar tu consentimiento o limitar el uso</a></li>
                    <li><a href="#menores">Menores de edad</a></li>
                    <li><a href="#cambios">Cambios a este aviso</a></li>
                    <li><a href="#autoridad">Autoridad y consentimiento</a></li>
                </ol>
            </nav>

            <h2 id="responsable">1. Responsable de tus datos personales</h2>
            <p><strong><?= e($privacy_store) ?></strong> es responsable del tratamiento de los datos personales que nos proporcionas a través de este sitio web.</p>
            <ul>
                <?php if ($privacy_address): ?>
                    <li><strong>Domicilio:</strong> <?= nl2br(e($privacy_address)) ?></li>
                <?php endif; ?>
                <?php if ($privacy_email): ?>
                    <li><strong>Correo para asuntos de privacidad:</strong> <a href="mailto:<?= e($privacy_email) ?>"><?= e($privacy_email) ?></a></li>
                <?php endif; ?>
                <?php if ($privacy_whatsapp): ?>
                    <li><strong>WhatsApp:</strong> <?= e($privacy_whatsapp) ?></li>
                <?php endif; ?>
                <li><strong>Formulario de contacto:</strong> <a href="<?= $contact_link ?>"><?= e($contact_link) ?></a></li>
            </ul>

            <h2 id="datos">2. Datos personales que recabamos</h2>
            <p>Solo pedimos la información necesaria para venderte y entregarte tus plantillas digitales o atender tus solicitudes. La obtenemos directamente de ti cuando la capturas en el sitio, y de forma automática cuando navegas en él.</p>
            <div class="legal-table-wrap">
                <table class="legal-table">
                    <thead><tr><th>Cuándo</th><th>Datos que recabamos</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Al realizar una compra</td>
                            <td>Nombre completo, correo electrónico, productos adquiridos, importe, método de pago elegido (tarjeta, OXXO o SPEI), referencia de pago y estado del pedido.</td>
                        </tr>
                        <tr>
                            <td>Al descargar tus archivos</td>
                            <td>Número de descargas realizadas por producto y fecha del pedido, para aplicar los límites de descarga.</td>
                        </tr>
                        <tr>
                            <td>Al escribirnos por el formulario de contacto o solicitar una cotización</td>
                            <td>Nombre completo, correo electrónico, teléfono y empresa (opcionales), motivo de contacto, medio de contacto preferido, el contenido de tu mensaje y la dirección IP desde la que se envía.</td>
                        </tr>
                        <tr>
                            <td>Al rastrear un pedido</td>
                            <td>Número de pedido y correo electrónico, solo para localizarlo; no se guardan de nuevo.</td>
                        </tr>
                        <tr>
                            <td>Al navegar en el sitio</td>
                            <td>Identificador de sesión (cookie técnica), contenido de tu carrito y datos técnicos de la conexión (dirección IP, tipo de navegador), que se registran de forma automática por motivos de funcionamiento y seguridad.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="legal-note">
                <strong>No recabamos datos personales sensibles</strong> (como origen étnico, estado de salud, creencias religiosas, preferencias sexuales u opiniones políticas). Te pedimos no incluirlos en los mensajes que nos envíes.
            </div>

            <h2 id="finalidades">3. Para qué usamos tus datos</h2>
            <h3>Finalidades primarias (necesarias para la relación contigo)</h3>
            <ol>
                <li>Procesar tu pedido y el pago correspondiente.</li>
                <li>Enviarte por correo la confirmación de compra, las instrucciones de pago (en el caso de OXXO o SPEI), recordatorios de pagos pendientes y los enlaces de descarga de tus plantillas.</li>
                <li>Permitirte descargar tus archivos de forma segura y controlar la vigencia y el número de descargas.</li>
                <li>Permitirte recuperar tu pedido con su número y tu correo.</li>
                <li>Responder tus mensajes, dudas, solicitudes de cotización de servicios y brindarte soporte.</li>
                <li>Prevenir fraudes, spam y usos indebidos del sitio.</li>
                <li>Cumplir obligaciones legales, fiscales y contables, y atender requerimientos de autoridades competentes.</li>
            </ol>
            <h3>Finalidades secundarias</h3>
            <p>Actualmente <strong>no utilizamos tus datos para finalidades secundarias</strong>, como mercadotecnia, publicidad o envío de boletines. No vendemos, rentamos ni intercambiamos tu información con fines comerciales.</p>
            <p>Si en el futuro quisiéramos usar tus datos para alguna finalidad distinta a las anteriores, actualizaremos este aviso y, cuando la Ley lo requiera, te pediremos tu consentimiento previo. Siempre podrás negarte sin que ello afecte tus compras ni tus descargas.</p>

            <h2 id="pagos">4. Datos de pago</h2>
            <p><strong>No almacenamos los datos de tu tarjeta</strong> (número, fecha de vencimiento ni código de seguridad). Los pagos se procesan directamente por proveedores especializados que cumplen el estándar de seguridad de la industria de tarjetas de pago (PCI DSS):</p>
            <ul>
                <li><strong>Stripe:</strong> capturas tus datos de pago en una página segura alojada por Stripe.</li>
                <li><strong>Conekta:</strong> los datos de tu tarjeta se convierten en un identificador cifrado (token) desde tu navegador antes de enviarse, de forma que nunca pasan por nuestros servidores. Los pagos en efectivo en OXXO y por transferencia SPEI se realizan con una referencia que te proporcionamos.</li>
            </ul>
            <p>De estos proveedores solo recibimos la información necesaria para identificar tu pago y saber si fue aprobado (por ejemplo, el identificador de la operación y su estado). El tratamiento que ellos hacen de tus datos se rige también por sus propios avisos de privacidad.</p>

            <h2 id="terceros">5. Con quién compartimos tus datos</h2>
            <h3>Proveedores que nos ayudan a operar la tienda (encargados)</h3>
            <p>Para que la tienda funcione, algunos proveedores tratan tus datos exclusivamente por nuestra cuenta, siguiendo nuestras instrucciones y con la obligación de protegerlos:</p>
            <div class="legal-table-wrap">
                <table class="legal-table">
                    <thead><tr><th>Proveedor</th><th>Para qué</th></tr></thead>
                    <tbody>
                        <tr><td>Procesadores de pago (Stripe y Conekta)</td><td>Cobrar tus compras de forma segura.</td></tr>
                        <tr><td>Servicio de alojamiento web y base de datos</td><td>Guardar la información del sitio, de tus pedidos y de tus mensajes.</td></tr>
                        <tr><td>Servicio de envío de correo electrónico</td><td>Enviarte confirmaciones, instrucciones de pago y enlaces de descarga.</td></tr>
                        <?php if ($privacy_recaptcha): ?>
                        <tr><td>Google reCAPTCHA</td><td>Proteger el formulario de contacto contra spam y bots.</td></tr>
                        <?php endif; ?>
                        <tr><td>Google Fonts</td><td>Mostrar las tipografías del sitio. Tu navegador se conecta a los servidores de Google para descargarlas, por lo que Google puede conocer tu dirección IP.</td></tr>
                    </tbody>
                </table>
            </div>
            <p>Algunos de estos proveedores pueden estar ubicados o almacenar información fuera de México. En esos casos procuramos que ofrezcan niveles de protección equivalentes a los que exige la Ley.</p>
            <h3>Transferencias</h3>
            <p>Solo transferiremos tus datos personales a terceros sin tu consentimiento en los casos previstos por la Ley, por ejemplo, cuando lo requiera una autoridad competente mediante mandato fundado y motivado, cuando sea necesario para el reconocimiento, ejercicio o defensa de un derecho en un proceso judicial, o cuando sea necesario para cumplir la relación jurídica que tenemos contigo. No realizamos transferencias que requieran tu consentimiento.</p>

            <h2 id="cookies">6. Cookies y tecnologías similares</h2>
            <p>Usamos únicamente una <strong>cookie técnica de sesión</strong>, indispensable para que el sitio funcione: recuerda el contenido de tu carrito mientras navegas, protege los formularios contra envíos falsificados y ayuda a prevenir el spam. Esta cookie no te identifica por tu nombre, no se usa con fines publicitarios y se elimina al cerrar tu navegador o al expirar la sesión.</p>
            <p><strong>No utilizamos cookies de publicidad, de perfilamiento ni herramientas de analítica de terceros.</strong></p>
            <?php if ($privacy_recaptcha): ?>
                <p>En el formulario de contacto utilizamos Google reCAPTCHA, que puede instalar sus propias cookies y recabar información técnica de tu dispositivo para distinguir personas de bots. Su uso se rige por la <a href="https://policies.google.com/privacy?hl=es" target="_blank" rel="noopener">política de privacidad</a> y los <a href="https://policies.google.com/terms?hl=es" target="_blank" rel="noopener">términos de servicio</a> de Google.</p>
            <?php endif; ?>
            <p>Puedes bloquear o eliminar las cookies desde la configuración de tu navegador. Ten en cuenta que, si bloqueas la cookie de sesión, no podrás usar el carrito ni completar tus compras.</p>

            <h2 id="conservacion">7. Cuánto tiempo conservamos tus datos</h2>
            <ul>
                <li><strong>Pedidos:</strong> durante el tiempo necesario para darte acceso a tus descargas y soporte, y después durante los plazos que exigen las disposiciones fiscales y mercantiles aplicables (por regla general, cinco años).</li>
                <li><strong>Mensajes de contacto:</strong> mientras atendemos tu solicitud y por un periodo razonable posterior para dar seguimiento; después los eliminamos o archivamos, salvo que exista una razón legal para conservarlos.</li>
                <li><strong>Cookie de sesión:</strong> hasta que cierras tu navegador o expira tu sesión.</li>
            </ul>
            <p>Concluidos estos plazos, bloquearemos y posteriormente suprimiremos tus datos de forma segura.</p>

            <h2 id="seguridad">8. Cómo protegemos tus datos</h2>
            <p>Mantenemos medidas de seguridad administrativas, técnicas y físicas para proteger tus datos contra daño, pérdida, alteración, destrucción o uso, acceso o tratamiento no autorizado. Entre ellas:</p>
            <ul>
                <li>Conexión cifrada (HTTPS) en el sitio.</li>
                <li>Acceso al panel de administración restringido a personal autorizado, con contraseñas almacenadas de forma cifrada y bloqueo automático ante intentos fallidos.</li>
                <li>Enlaces de descarga protegidos con códigos únicos y aleatorios, con vigencia y número de descargas limitados.</li>
                <li>Los archivos que compras no son accesibles de forma directa: solo se entregan después de validar tu pedido.</li>
                <li>Protección de formularios contra envíos automatizados y falsificados.</li>
            </ul>
            <p>Ningún sistema es completamente infalible. Si llegáramos a detectar una vulneración de seguridad que afecte de forma significativa tus derechos, te lo informaremos sin demora para que puedas tomar las medidas correspondientes.</p>

            <h2 id="arco">9. Tus derechos: Acceso, Rectificación, Cancelación y Oposición (ARCO)</h2>
            <p>Tienes derecho a:</p>
            <ul>
                <li><strong>Acceso:</strong> conocer qué datos personales tenemos de ti y cómo los usamos.</li>
                <li><strong>Rectificación:</strong> corregir tus datos si son inexactos o están incompletos.</li>
                <li><strong>Cancelación:</strong> pedir que eliminemos tus datos cuando consideres que no se están usando conforme a la Ley (salvo que debamos conservarlos por una obligación legal).</li>
                <li><strong>Oposición:</strong> oponerte al uso de tus datos para fines específicos.</li>
            </ul>
            <h3>Cómo ejercerlos</h3>
            <p>Envía tu solicitud <?php if ($privacy_email): ?>al correo <a href="mailto:<?= e($privacy_email) ?>"><?= e($privacy_email) ?></a> o <?php endif; ?>por medio de nuestro <a href="<?= $contact_link ?>">formulario de contacto</a>, eligiendo el motivo "Privacidad y datos personales (ARCO)". Tu solicitud debe incluir:</p>
            <ol>
                <li>Tu nombre completo y un correo electrónico u otro medio para comunicarte la respuesta.</li>
                <li>Una copia de una identificación oficial o, en su caso, los documentos que acrediten la representación legal de quien presente la solicitud a tu nombre.</li>
                <li>La descripción clara del derecho que deseas ejercer y de los datos a los que se refiere.</li>
                <li>Cualquier dato que nos ayude a localizar tu información, como el número de pedido o el correo con el que compraste.</li>
                <li>En el caso de rectificación, la corrección que solicitas y, si aplica, la documentación que la respalde.</li>
            </ol>
            <p>Te responderemos en un plazo máximo de <strong>20 días hábiles</strong> contados a partir de que recibamos tu solicitud completa. Si resulta procedente, la haremos efectiva dentro de los <strong>15 días hábiles</strong> siguientes a nuestra respuesta. Estos plazos podrán ampliarse una sola vez por un periodo igual cuando las circunstancias lo justifiquen, lo cual te informaremos. El ejercicio de tus derechos es gratuito; solo podrían cobrarse, en su caso, gastos justificados de envío o de reproducción de documentos.</p>

            <h2 id="revocacion">10. Revocar tu consentimiento o limitar el uso de tus datos</h2>
            <p>Puedes revocar en cualquier momento el consentimiento que nos hayas otorgado, o pedirnos que limitemos el uso o divulgación de tus datos, siguiendo el mismo procedimiento descrito para los derechos ARCO. Ten en cuenta que no siempre podremos atender tu solicitud de forma inmediata o total, por ejemplo, cuando debamos conservar cierta información por una obligación legal, y que revocar tu consentimiento para las finalidades primarias puede impedirnos completar tu compra o entregarte tus descargas.</p>

            <h2 id="menores">11. Menores de edad</h2>
            <p>Nuestra tienda está dirigida a personas mayores de 18 años. No recabamos de forma intencional datos de menores de edad. Si eres madre, padre o tutor y crees que un menor nos proporcionó sus datos, escríbenos para eliminarlos.</p>

            <h2 id="cambios">12. Cambios a este aviso de privacidad</h2>
            <p>Podemos modificar este aviso para reflejar cambios legales, en nuestros servicios o en la forma en que tratamos tus datos. Cualquier cambio se publicará en esta misma página, indicando la fecha de la última actualización al inicio del documento. Te recomendamos revisarlo periódicamente.</p>

            <h2 id="autoridad">13. Autoridad y consentimiento</h2>
            <p>Si consideras que tu derecho a la protección de datos personales ha sido vulnerado, puedes acudir ante la autoridad competente en materia de protección de datos personales en México.</p>
            <p>Al proporcionarnos tus datos personales mediante la compra de productos, el formulario de contacto o cualquier otro medio del sitio, reconoces haber leído este aviso de privacidad y consientes el tratamiento de tus datos conforme a lo aquí establecido.</p>

            <p style="margin-top:28px;">¿Tienes dudas sobre este aviso? Escríbenos desde nuestra página de <a href="<?= $contact_link ?>">contacto</a> y con gusto te ayudamos.</p>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
