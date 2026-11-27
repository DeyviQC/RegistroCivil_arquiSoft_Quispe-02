# Acceso directo a Firma Perú

El botón «Abrir Firma Perú» usa `rcmunicipal-firma://abrir`. Windows abre el acceso directo ClickOnce oficial mediante su propio manejador rundll32 / dfshim.dll,ShOpenVerbShortcut. El comando tiene una ruta fija al acceso oficial del usuario, verificada por el instalador, y no interpola argumentos recibidos desde el navegador.

No hay ejecutable propio ni asistente, preparación de archivos, servidor local, tokens o carga automática. Los formularios web conservan la comprobación, revisión y publicación del PDF firmado.

Ejecutar `activar-firma-peru.cmd` desde el Explorador una vez por usuario para registrar el acceso o reemplazar la asociación antigua. No necesita administrador. `instalar-asistente-firma.cmd` conserva compatibilidad con el mismo registro, sin compilar ni instalar asistentes. El mensaje esperado es «ACCESO DIRECTO ACTUALIZADO».

El registro realizado desde una aplicación MSIX puede quedar aislado de Chrome: ejecutar el archivo desde el Explorador. La desinstalación retira únicamente el protocolo reconocido y conserva Firma Perú y los PDF.