# Firma DNIe y entrega de actas

## Flujo elegido: firma manual en Firma Perú

El botón **Abrir Firma Perú** es únicamente un acceso al programa instalado. No prepara ni transfiere documentos, no abre carpetas y no muestra un asistente. El personal selecciona el PDF en Firma Perú y carga el resultado en el formulario web. Ver [configuración del acceso local](ASISTENTE-FIRMA-LOCAL.md).

La pantalla permite subir un PDF firmado directamente si el personal ya tiene el original. Descargar es opcional: la ruta autenticada sirve el archivo archivado sin copiarlo ni cambiar la versión de la solicitud. No se añade ninguna hoja o recuadro y no se requiere instalar una integración web.

Al cargar, se contrasta la firma con el acta original de esa solicitud, aunque no haya preparación ni descarga previa. Se conserva compatibilidad con borradores antiguos con recuadro cuando la firma corresponde exactamente a esa versión. Un archivo ajeno, sin firma o con modificaciones posteriores no se almacena ni habilita entrega. El documento firmado sigue sujeto a revisión antes de publicar.

El recorrido manual conserva el original archivado y guarda únicamente el nuevo PDF firmado; no crea PDFs intermedios. No se eliminaron copias históricas ni archivos existentes. Los borradores retirados siguen conservándose según la política de auditoría.

La interfaz no carga el SDK de Firma Perú ni muestra la integración pendiente. El adaptador web documentado debajo se conserva como implementación opcional futura, fuera del recorrido principal.

## Firma desde el sistema con Firma Perú

La aplicación incluye el adaptador al protocolo oficial del firmador web de Firma Perú. No está activado sin credenciales institucionales: instalar el firmador de escritorio no proporciona autorización para la integración web.

Fuente técnica: https://resources.firmaperu.gob.pe/documentos/firmador-componente-web.pdf (edición marzo 2025). Solicitud institucional: https://www.gob.pe/22273-solicitar-acceso-al-firmador-firma-peru-para-entidades-publicas.

### Activación pendiente

1. La entidad solicita a PCM las credenciales del **Firmador Web** por los canales y formato oficiales; no usar credenciales de ejemplos o de otra entidad.
2. Guardar `fwAuthorization.json` en `storage/app/private/firma/fwAuthorization.json`, fuera de `public/` y Git, o configurar `FIRMAPERU_CREDENTIALS_FILE` con su ruta absoluta. El archivo requiere `client_id`, `client_secret` y `token_url`.
3. Configurar `APP_URL` como la URL que el firmador local pueda alcanzar. El ejemplo oficial admite localhost para desarrollo en la misma computadora. En despliegues usar HTTPS. Revisar que proxies y logs no almacenen las capacidades que viajan en URLs; no compartir esas URLs.
4. Abrir Atención ciudadana en Microsoft Edge (recomendado en la guía actual por soporte ClickOnce nativo). El programa PC standalone y el cliente web pueden requerir instalaciones distintas; aceptar el inicio del componente oficial cuando corresponda. No desactivar antivirus ni firewall de forma general.
5. Usar «Firmar con DNIe · Firma Perú». La copia se toma directamente del acta original: no se agrega la tarjeta verde ni otra hoja. El firmador dibuja el logo PNG con la descripción del certificado y permite posicionarla visualmente.
6. Firmar un documento ficticio. El cliente devuelve el campo multipart `signed_file`. Solo después de que el servidor verifica y almacena el PDF aparece la revisión para publicar. `signatureOk()` por sí solo no habilita entrega ni cambia el estado a LISTA.

El adaptador usa jQuery 3.6.0 con SRI y el SDK oficial `https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js`, cargados exclusivamente cuando la credencial está disponible. El JWT se obtiene desde el servidor, sin enviar `client_secret` al navegador. La copia, las capacidades y el JWT se guardan dentro de los datos cifrados de la solicitud. Los parámetros son de un solo uso. Documento y retorno tienen capacidades distintas con vencimiento de 10 minutos, asociadas a solicitud, intento y usuario activo con rol interno; cancelar, comenzar otro intento, retirar el borrador o recibir la firma invalida las anteriores.

El servidor acepta resultados de manera atómica, comprueba criptografía y no publica automáticamente. La revisión de confianza y autorización institucional sigue siendo necesaria. La integración se probó con respuestas HTTP ficticias; **la instalación del cliente web, emisión real de JWT y firma completa con DNIe quedan pendientes de disponer de credenciales institucionales válidas**. No se ha enviado un documento real ni iniciado una firma de forma automática durante desarrollo.

`FIRMAPERU_PORT` por defecto 48596 es el puerto del componente local, distinto del servidor Laravel. Ante un fallo de conexión o cierre del cliente, el botón para cancelar invalida el intento. La alternativa manual sigue disponible.

## Alternativa manual

En Atención ciudadana, una solicitud aprobada y pagada permite preparar una copia con el logo municipal, en una esquina de una página existente. El personal elige página y esquina, revisa la vista previa y descarga el archivo. No se agrega una hoja ni se modifica el archivo del archivo municipal.

La copia dice «Documento preparado para firma digital»: el recuadro no es una firma ni una certificación institucional. El nombre y la fecha auténticos se consultan en el certificado y la firma electrónica, no en un texto declarado por el administrador.

El personal firma el PDF completo en ReFirma con el DNIe, introduce su PIN únicamente en la aplicación local y carga el resultado. La web no accede al lector ni almacena claves privadas o PIN. No hay integración de firma directa en el navegador.

El servidor verifica CMS con OpenSSL, que la firma cubra el archivo completo, que no haya cambios posteriores, la vigencia temporal actual del certificado y que el PDF preparado sea el prefijo de la revisión firmada. Esto no acredita que los cambios incrementales anteriores a la firma sean legítimos: el personal debe comparar visualmente el contenido con el acta original. Tampoco valida automáticamente cadena de confianza, revocación ni facultades del firmante. La publicación requiere una declaración expresa y constancia de esa revisión. No se anuncia «certificado RENIEC válido» solo por superar la verificación criptográfica.

Después de publicar, el estado es LISTA y el ciudadano descarga el archivo exacto firmado desde su sesión de seguimiento con correo verificado y vigente. Se verifica SHA-256 al descargar. Los archivos son privados; no se crean URLs públicas ni se envían adjuntos por correo o WhatsApp. Se auditan preparación, carga, revisión/publicación y descarga. Los borradores firmados pueden retirarse antes de publicar con motivo auditado; los archivos privados se conservan sin sobrescribirlos.

## Dependencias

- PHP 8.4 con OpenSSL/CMS.
- Python con `pypdf` y `reportlab` para preparar copias. Configurar `FIRMA_PYTHON` con la ruta del ejecutable (en otra máquina no usar la ruta local de Codex).
- Windows, controlador de lector, middleware de DNIe y ReFirma en la computadora del firmante.

No hay cambios de esquema: la metadata de entrega se guarda dentro de `datos` cifrado de la solicitud. La transición LISTA solo se realiza por el controlador de entrega, no por el formulario de atención general. El estado indica disponibilidad, no prueba que el ciudadano haya descargado; las descargas quedan en auditoría.

## Prueba manual

1. Aprobar una solicitud de prueba pagada.
2. Preparar página y ubicación, abrir vista previa, corregir ubicación si tapa contenido.
3. Descargar y firmar con ReFirma; cargar `[R].pdf` sin editarlo.
4. Verificar certificado, revocación, contenido y autorización; registrar constancia y publicar.
5. Entrar por seguimiento con correo verificado y descargar; comprobar firma del archivo descargado.

Usar documentos ficticios para las pruebas. No marcar la constancia de confianza o autorización sin haber hecho esa revisión.
