=== Liquen Panel ===
Versión: 1.0.10 · Requiere: WordPress 6.4+, PHP 8.0+, Advanced Custom Fields (gratis)

Back office en el front para cargar contenidos desde el celular, sin wp-admin.

== Instalar ==
1. Backup (JetBackup).
2. Plugins → Añadir nuevo → Subir plugin → liquen-panel.zip → Activar.
3. Ajustes → Liquen Panel: nombre, colores, ícono y qué tipos de contenido y campos se cargan.
4. LiteSpeed Cache (si está): Caché → Excluir → «URIs a no cachear» → ^/panel → Guardar → Purgar todo.
5. Si la dirección /panel/ ya la usa una página del sitio, el panel no la toma y queda apagado:
   elegir otra dirección en Ajustes → Liquen Panel.
6. Entrar a /panel/ (o la dirección elegida) desde el celular → menú del navegador → «Agregar a pantalla de inicio».

== Qué hace ==
* Inicio, «Mis …» (Próximas / Sin fecha / Pasadas si hay fechas), formulario de un paso.
* Editar, Ver, Duplicar (la copia sale en borrador y sin fechas), Publicar / Pasar a borrador, Borrar (papelera).
* Programar la publicación (opcional por tipo).
* Imágenes achicadas en el celular antes de subir; las que nunca se usan se borran solas a las 48 h.
* Campos ACF soportados: texto, área de texto, número, URL, email, select, radio, botones, casillas,
  verdadero/falso, fecha, hora, imagen, grupo de imágenes (galería) y wysiwyg (como texto simple).
* Textos con formato especial (listas, negritas, bloques) no se tocan: se editan en wp-admin.
* Acceso: administradores y los roles elegidos; cada uno ve lo que WordPress le deja editar.

== Cambios ==
1.0.10
* El panel ya no se cae si el servidor no tiene la extensión mbstring de PHP (pasa al cambiar de versión de PHP
  con pocas extensiones): usa reemplazos propios para mayúsculas y minúsculas con tildes y eñes.
* Actualizaciones: si la API de GitHub no responde (límite por IP en hostings compartidos), se busca la versión
  nueva por una segunda vía. Al activar el plugin aparece enseguida «Activar actualizaciones automáticas».
* Exportar / importar: el ícono de la app viaja con su dirección. Al importar en otro sitio, se baja a la biblioteca.
* Aviso de campos nuevos: en sitios que venían de antes de la 1.0.9, empieza a funcionar al actualizar,
  sin tener que guardar los ajustes primero.

1.0.9
* Exportar / importar ajustes (Ajustes → Liquen Panel, abajo): descarga un .json con toda la configuración
  y lo carga en otro sitio. Los campos de ACF se buscan por nombre; avisa lo que no existe en el sitio nuevo.
* Aviso de campos nuevos de ACF: si a un tipo activo se le agrega un campo en ACF y no se revisó en los ajustes,
  aparece un aviso (Escritorio, Plugins, ACF y Ajustes) y la marca «nuevo» junto al campo. Se va al guardar.
* Protección de pestaña vieja: si los ajustes cambiaron después de abrir la página, no se guardan y se avisa.

1.0.8
* Actualizaciones automáticas desde GitHub: cuando hay una versión nueva aparece el aviso normal
  en Plugins y se actualiza con un clic (o sola, con «Activar actualizaciones automáticas»).

1.0.7
* Celular: en la galería, botones más compactos («Subir», «Biblioteca», «Quitar») que no se parten en dos líneas,
  aun con letra grande; casillas vacías del mismo alto que las llenas.
* Confirmaciones más cortas: «¿Seguro?» (Quitar foto, Pasar a borrador, Borrar).

1.0.6
* Fotos desde la biblioteca de medios: botón «Biblioteca» en cada casilla de imagen (foto destacada,
  imágenes y galerías de ACF). Ventana con buscador por nombre y «Cargar más», pensada para el celular.
* Cada usuario ve solo las imágenes que puede usar: administradores y editores, todas; los demás, las suyas.
  Una imagen ajena no se guarda aunque se fuerce desde el navegador.
* Ajuste en General → Fotos: «Permitir elegir fotos de la biblioteca» (activado por defecto).
  Destildado, en el panel solo se pueden subir fotos nuevas.
* Filtro lp_biblioteca_args para ajustar qué imágenes se ofrecen.

1.0.5
* Kit de inicio: se elige el nombre (singular / plural / género) y la dirección de cada tipo
  (ej. Experiencias en /experiencias/, Cabañas en /cabanas/). La clave interna (actividad,
  alojamiento) y los nombres de los campos quedan iguales en todos los sitios.

1.0.4
* Kit de inicio (Ajustes → Liquen Panel, abajo): crea en ACF los tipos de contenido Actividades
  (/actividades/) y Alojamientos, la taxonomía Características y los grupos de campos del estándar
  Liquen, con los tipos de actividad elegidos y su lógica condicional; deja el panel configurado.
  No pisa nada que ya exista: lo saltea y avisa.
* Orden en el inicio: arranca en 1, 2, 3… según el orden de la lista.
* «se elige una sola» queda debajo de su taxonomía.

1.0.3
* Orden de los tipos en el inicio del panel (número por tipo, el más bajo primero).
* Taxonomías de una sola opción: «se elige una sola» muestra botones redondos y guarda un único término.

1.0.2
* Respeta la lógica condicional de ACF: los campos se muestran u ocultan según otros campos
  (ej. «Distancia» solo si el tipo es Caminata), igual que en wp-admin.
* Los campos ocultos no se validan ni se guardan: queda el valor que tenían.
* Filtro lp_condiciones para ajustar las condiciones de un campo.

1.0.1
* Dirección del panel segura: si ya la usa una página (o es de WordPress), no se toma; aviso en Ajustes.
  Solo se toma la dirección exacta: las páginas hijas nunca se tocan.
* «Sin fecha» y «Pasadas» plegadas, con las 10 primeras y «Ver todas».
* Buscador por título en «Mis …».
* Botón «Volver a los colores de fábrica».
* Sin el «·» al principio del resumen en «Mis …».

== Pendiente (próximas versiones) ==
Modo Bricks (1.1), modo con aprobación y fichas madre/hijas (Caminos Locales), colores tomados de Bricks.
