# Criterios de interfaz municipal

Se conserva el portal ciudadano y su fotografía de la plaza aceptados por el usuario. El sistema del personal y el acceso usan `municipal-sistema.css`; no se cargan las propuestas descartadas de EQ Women o Renaper.

## Reglas comunes

- Verde municipal para acciones principales, blanco para contenido y grises plateados para superficies y separación.
- La información conserva colores semánticos: nacimientos en azul, matrimonios en verde, defunciones en gris; total en verde institucional. Los estados mantienen advertencias ámbar y errores rojos. El color acompaña etiquetas e iconos y no sustituye el texto.
- La barra superior identifica el sistema; el título de cada pantalla aparece una sola vez en su contenido para evitar duplicación.
- Títulos jerárquicos, formularios de 44 px, textos de ayuda discretos y etiquetas claras.
- Tablas con encabezados consistentes, acciones reconocibles, estados con texto y desplazamiento horizontal cuando sea necesario. Los códigos y estados en tablas no se fragmentan letra por letra.
- Menú por tareas, sección administrativa visible según permisos, perfil accesible y acceso directo al contenido con teclado.
- Avisos persistentes hasta su cierre manual; errores y confirmaciones no desaparecen por temporizador.
- El visor permite desplazar un documento ampliado sin desbordar toda la pantalla. Sus controles de zoom tienen nombre accesible y no intentan renderizar antes de cargar el PDF.

`municipal-interfaz.js` asocia etiquetas antiguas con controles, asigna encabezados a tablas y permite enfocar sus regiones para desplazarlas con teclado. No cambia nombres ni valores de campos.

Los cambios de presentación se aplican a inicio, actas, atención ciudadana, firma y entrega, reportes, incidencias, usuarios, perfil, historial, solicitudes y acceso/recuperación. El portal público mantiene su capa `portal-cliente.css`. No se modifican los diseños de actas firmadas ni los PDF de reportes.

## Verificación

Suite funcional completa en SQLite temporal. Revisión adicional de once rutas con usuarios, solicitudes y actas ficticios, sin importar registros ni enviar correos. Las muestras HTML públicas se eliminan al terminar. No se modifica el proyecto entregado a la municipalidad ni su base de datos.
