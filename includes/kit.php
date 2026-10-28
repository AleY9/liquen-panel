<?php
/**
 * Kit de inicio: crea en ACF los tipos de contenido, la taxonomía y los grupos de campos
 * del «Estándar Liquen para sitios de emprendimientos», y deja el panel configurado.
 *
 * Todo queda guardado en ACF (ACF → Tipos de contenido / Grupos de campos), igual que si
 * se hubiera creado a mano: se edita ahí y no depende del plugin. Nunca pisa lo que ya existe.
 */

defined( 'ABSPATH' ) || exit;

// Catálogo de tipos de actividad: valor => etiqueta, y qué campos propios se ven.
function lp_kit_catalogo() {
	return apply_filters(
		'lp_kit_catalogo',
		array(
			'caminata'     => array( 'label' => 'Caminata / Senderismo', 'distancia' => true, 'nivel' => true, 'varios_dias' => false ),
			'cabalgata'    => array( 'label' => 'Cabalgata', 'distancia' => true, 'nivel' => true, 'varios_dias' => false ),
			'bicicleta'    => array( 'label' => 'Bicicleta', 'distancia' => true, 'nivel' => true, 'varios_dias' => false ),
			'canoa'        => array( 'label' => 'Canoa / Kayak', 'distancia' => true, 'nivel' => true, 'varios_dias' => false ),
			'avistamiento' => array( 'label' => 'Avistamiento de aves', 'distancia' => false, 'nivel' => true, 'varios_dias' => false ),
			'taller'       => array( 'label' => 'Taller', 'distancia' => false, 'nivel' => false, 'varios_dias' => false ),
			'retiro'       => array( 'label' => 'Retiro', 'distancia' => false, 'nivel' => false, 'varios_dias' => true ),
			'visita'       => array( 'label' => 'Visita guiada', 'distancia' => false, 'nivel' => false, 'varios_dias' => false ),
			'otra'         => array( 'label' => 'Otra', 'distancia' => false, 'nivel' => false, 'varios_dias' => false ),
		)
	);
}

// ¿Ya existe en ACF un tipo de contenido / taxonomía con esta clave?
function lp_kit_acf_existe( $clave, $acf_tipo ) {
	$campo = 'acf-post-type' === $acf_tipo ? 'post_type' : 'taxonomy';
	foreach ( acf_get_internal_post_type_posts( $acf_tipo ) as $p ) {
		if ( isset( $p[ $campo ] ) && $p[ $campo ] === $clave ) {
			return true;
		}
	}
	return false;
}

// Nombres de campo ya usados en grupos que apuntan a este tipo de contenido.
function lp_kit_nombres_usados( $post_type ) {
	$out = array();
	foreach ( acf_get_field_groups( array( 'post_type' => $post_type ) ) as $g ) {
		foreach ( (array) acf_get_fields( $g['key'] ) as $f ) {
			$out[ $f['name'] ] = $g['title'];
		}
	}
	return $out;
}

// Etiquetas completas para un tipo de contenido.
function lp_kit_labels_cpt( $singular, $plural, $f ) {
	$a  = $f ? 'a' : 'o';
	$la = $f ? 'la' : 'el';
	$S  = lp_ucfirst( $singular );
	$P  = lp_ucfirst( $plural );
	return array(
		'name'               => $P,
		'singular_name'      => $S,
		'menu_name'          => $P,
		'all_items'          => ( $f ? 'Todas las ' : 'Todos los ' ) . $plural,
		'add_new'            => 'Añadir nuev' . $a,
		'add_new_item'       => 'Añadir nuev' . $a . ' ' . $singular,
		'edit_item'          => 'Editar ' . $singular,
		'new_item'           => 'Nuev' . $a . ' ' . $singular,
		'view_item'          => 'Ver ' . $singular,
		'view_items'         => 'Ver ' . $plural,
		'search_items'       => 'Buscar ' . $plural,
		'not_found'          => 'No hay ' . $plural,
		'not_found_in_trash' => 'No hay ' . $plural . ' en la papelera',
		'archives'           => 'Archivo de ' . $plural,
		'featured_image'     => 'Foto destacada',
		'set_featured_image' => 'Elegir foto destacada',
		'item_published'     => $S . ' publicad' . $a . '.',
		'item_updated'       => $S . ' actualizad' . $a . '.',
		'item_link'          => 'Enlace a ' . $la . ' ' . $singular,
	);
}

function lp_kit_cpt( $clave, $singular, $plural, $f, $archivo, $icono, $taxs ) {
	return array(
		'key'                 => 'post_type_lp_' . $clave,
		'title'               => lp_ucfirst( $plural ),
		'post_type'           => $clave,
		'menu_order'          => 0,
		'active'              => true,
		'labels'              => lp_kit_labels_cpt( $singular, $plural, $f ),
		'description'         => '',
		'public'              => true,
		'hierarchical'        => false,
		'show_in_rest'        => true,
		'menu_icon'           => array( 'type' => 'dashicons', 'value' => $icono ),
		'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions' ),
		'taxonomies'          => $taxs,
		'has_archive'         => true,
		'has_archive_slug'    => $archivo,
		'rewrite'             => array(
			'permalink_rewrite' => $archivo !== $clave ? 'custom_permalink' : 'post_type_key',
			'slug'              => $archivo !== $clave ? $archivo : '',
			'feeds'             => false,
			'pages'             => true,
			'with_front'        => true,
		),
		'query_var'           => 'post_type_key',
		'delete_with_user'    => false,
	);
}

// Campo con valores por defecto del estándar.
function lp_kit_campo( $pre, $nombre, $label, $type, $extra = array() ) {
	return array_merge(
		array(
			'key'               => 'field_' . $pre . '_' . $nombre,
			'label'             => $label,
			'name'              => $nombre,
			'type'              => $type,
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array( 'width' => '', 'class' => '', 'id' => '' ),
		),
		$extra
	);
}

function lp_kit_galeria( $pre ) {
	$subs = array();
	for ( $i = 1; $i <= 6; $i++ ) {
		$subs[] = lp_kit_campo( $pre, 'galeria_foto' . $i, 'Foto ' . $i, 'image', array( 'name' => 'foto' . $i, 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'wrapper' => array( 'width' => '33', 'class' => '', 'id' => '' ) ) );
	}
	return lp_kit_campo( $pre, 'galeria', 'Galería', 'group', array( 'layout' => 'block', 'sub_fields' => $subs, 'instructions' => 'Hasta 6 fotos.' ) );
}

// Campos de Actividad según los tipos elegidos.
function lp_kit_campos_actividad( $tipos, $nombre_emp, $na = array( 'singular' => 'actividad' ) ) {
	$cat    = lp_kit_catalogo();
	$pre    = 'lp_act';
	$k_tipo = 'field_' . $pre . '_tipo_de_actividad';
	$k_org  = 'field_' . $pre . '_organiza';
	$choices = array();
	foreach ( $tipos as $t ) {
		$choices[ $t ] = $cat[ $t ]['label'];
	}
	// Una regla «Tipo = x» por grupo (O) para los tipos que cumplen.
	$si = function ( $prop ) use ( $tipos, $cat, $k_tipo ) {
		$g = array();
		foreach ( $tipos as $t ) {
			if ( ! empty( $cat[ $t ][ $prop ] ) ) {
				$g[] = array( array( 'field' => $k_tipo, 'operator' => '==', 'value' => $t ) );
			}
		}
		return $g;
	};
	$medio = array( 'width' => '50', 'class' => '', 'id' => '' );

	$f   = array();
	$f[] = lp_kit_campo( $pre, 'tipo_de_actividad', 'Tipo de ' . $na['singular'], 'button_group', array( 'choices' => $choices, 'default_value' => reset( $tipos ), 'return_format' => 'label', 'layout' => 'horizontal', 'allow_null' => 0 ) );
	$f[] = lp_kit_campo( $pre, 'fecha_actividad', 'Fecha de inicio', 'date_picker', array( 'display_format' => 'd/m/Y', 'return_format' => 'Ymd', 'first_day' => 1, 'instructions' => 'Vacía = todo el año.', 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'fecha_actividad_fin', 'Fecha de fin', 'date_picker', array( 'display_format' => 'd/m/Y', 'return_format' => 'Ymd', 'first_day' => 1, 'instructions' => 'Solo si dura más de un día.', 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'hora_inicio', 'Hora de inicio', 'time_picker', array( 'display_format' => 'H:i', 'return_format' => 'H:i', 'wrapper' => $medio ) );

	// Duración: se oculta en los tipos de varios días.
	$cond_dur = array();
	foreach ( $tipos as $t ) {
		if ( ! empty( $cat[ $t ]['varios_dias'] ) ) {
			$cond_dur[] = array( 'field' => $k_tipo, 'operator' => '!=', 'value' => $t );
		}
	}
	$f[] = lp_kit_campo( $pre, 'duracion', 'Duración', 'text', array( 'placeholder' => '8 h aprox.', 'maxlength' => 60, 'wrapper' => $medio, 'conditional_logic' => $cond_dur ? array( $cond_dur ) : 0 ) );

	$c = $si( 'distancia' );
	if ( $c ) {
		$f[] = lp_kit_campo( $pre, 'distancia', 'Distancia', 'number', array( 'append' => 'km', 'min' => 0, 'step' => '0.1', 'wrapper' => $medio, 'conditional_logic' => $c ) );
	}
	$c = $si( 'nivel' );
	if ( $c ) {
		$f[] = lp_kit_campo( $pre, 'nivel', 'Nivel', 'select', array( 'choices' => array( 'facil' => 'Fácil', 'moderado' => 'Moderado', 'exigente' => 'Exigente' ), 'allow_null' => 1, 'return_format' => 'label', 'wrapper' => $medio, 'conditional_logic' => $c ) );
	}
	$f[] = lp_kit_campo( $pre, 'incluye', 'Incluye', 'textarea', array( 'rows' => 3, 'placeholder' => 'Guía, almuerzo y merienda' ) );
	$f[] = lp_kit_campo( $pre, 'incluye_alimentacion', 'Incluye alimentación', 'true_false', array( 'ui' => 1, 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'incluye_alojamiento', 'Incluye alojamiento', 'true_false', array( 'ui' => 1, 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'precio', 'Precio', 'text', array( 'maxlength' => 80, 'instructions' => 'Opcional. Si queda vacío, la ficha muestra «Consultar».', 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'maximo_personas', 'Máximo de personas', 'number', array( 'append' => 'personas', 'min' => 1, 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'inscripcion', 'Enlace de inscripción', 'url', array( 'instructions' => 'Opcional. Si queda vacío, la inscripción es por WhatsApp.' ) );
	$f[] = lp_kit_campo( $pre, 'organiza', 'Organiza', 'button_group', array( 'choices' => array( 'propio' => $nombre_emp, 'terceros' => 'Otra organización' ), 'default_value' => 'propio', 'return_format' => 'value', 'allow_null' => 0 ) );
	$terceros = array( array( array( 'field' => $k_org, 'operator' => '==', 'value' => 'terceros' ) ) );
	$f[] = lp_kit_campo( $pre, 'organizador', 'Quién organiza', 'text', array( 'conditional_logic' => $terceros, 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'organizador_enlace', 'Enlace de quien organiza', 'url', array( 'instructions' => 'Web o Instagram.', 'conditional_logic' => $terceros, 'wrapper' => $medio ) );
	$f[] = lp_kit_campo( $pre, 'afiche', 'Afiche', 'image', array( 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'instructions' => 'Vertical (4:5). Se muestra si no hay foto destacada.' ) );
	$f[] = lp_kit_galeria( $pre );
	return $f;
}

function lp_kit_campos_alojamiento() {
	$pre   = 'lp_aloj';
	$medio = array( 'width' => '50', 'class' => '', 'id' => '' );
	return array(
		lp_kit_campo( $pre, 'capacidad', 'Capacidad', 'number', array( 'append' => 'personas', 'min' => 1, 'wrapper' => $medio ) ),
		lp_kit_campo( $pre, 'camas', 'Camas', 'text', array( 'placeholder' => '1 doble + 2 simples', 'wrapper' => $medio ) ),
		lp_kit_campo( $pre, 'bano', 'Baño', 'select', array( 'choices' => array( 'privado' => 'Privado', 'compartido' => 'Compartido' ), 'allow_null' => 1, 'return_format' => 'label', 'wrapper' => $medio ) ),
		lp_kit_campo( $pre, 'precio', 'Precio', 'text', array( 'maxlength' => 80, 'instructions' => 'Opcional. Si queda vacío, la ficha muestra «Consultar».', 'wrapper' => $medio ) ),
		lp_kit_campo( $pre, 'url_reserva', 'Enlace de reserva', 'url', array( 'instructions' => 'Booking, Airbnb o propio.' ) ),
		lp_kit_galeria( $pre ),
	);
}

/**
 * Crea lo elegido. $op: actividad (bool), alojamiento (bool), tipos (string[]), nombre (string).
 * Devuelve array( 'hecho' => string[], 'salteado' => string[] ).
 */
function lp_kit_crear( $op ) {
	$hecho    = array();
	$salteado = array();
	$cat      = lp_kit_catalogo();
	$tipos    = array_values( array_intersect( (array) $op['tipos'], array_keys( $cat ) ) );
	$nombre   = '' !== trim( (string) $op['nombre'] ) ? trim( $op['nombre'] ) : get_bloginfo( 'name' );
	$ajustes  = get_option( 'lp_ajustes', array() );
	$ajustes  = is_array( $ajustes ) ? $ajustes : array();
	$cambio   = false;

	// Nombres visibles y direcciones (la clave interna queda fija: actividad / alojamiento).
	$n = array();
	foreach ( array( 'actividad' => array( 'actividad', 'actividades', 'f', 'actividades' ), 'alojamiento' => array( 'alojamiento', 'alojamientos', 'm', 'alojamiento' ) ) as $k => $d ) {
		$x   = isset( $op['nombres'][ $k ] ) ? (array) $op['nombres'][ $k ] : array();
		$sg  = mb_strtolower( trim( (string) ( $x['singular'] ?? '' ) ) );
		$pl  = mb_strtolower( trim( (string) ( $x['plural'] ?? '' ) ) );
		$dir = sanitize_title( (string) ( $x['slug'] ?? '' ) );
		$n[ $k ] = array(
			'singular' => '' !== $sg ? $sg : $d[0],
			'plural'   => '' !== $pl ? $pl : $d[1],
			'genero'   => in_array( $x['genero'] ?? '', array( 'f', 'm' ), true ) ? $x['genero'] : $d[2],
			'slug'     => '' !== $dir ? $dir : $d[3],
		);
		$n[ $k ]['de'] = 'f' === $n[ $k ]['genero'] ? 'de la' : 'del';
		if ( ! empty( $op[ $k ] ) && function_exists( 'lp_slug_ocupado' ) && in_array( true, array( lp_slug_ocupado( $n[ $k ]['slug'] ) > 0, -2 === lp_slug_ocupado( $n[ $k ]['slug'] ) ), true ) ) {
			$salteado[] = 'La dirección /' . $n[ $k ]['slug'] . '/ ya la usa una página: elegí otra para ' . $n[ $k ]['plural'] . '.';
			$op[ $k ]   = false;
		}
	}
	$na = $n['actividad'];
	$nl = $n['alojamiento'];

	if ( ! empty( $op['actividad'] ) && ! $tipos ) {
		$salteado[] = 'Actividades: elegí al menos un tipo de actividad.';
		$op['actividad'] = false;
	}

	// ---- Actividad ----
	if ( ! empty( $op['actividad'] ) ) {
		if ( post_type_exists( 'actividad' ) || lp_kit_acf_existe( 'actividad', 'acf-post-type' ) ) {
			$salteado[] = 'Tipo de contenido «actividad»: ya existe, no se tocó.';
		} else {
			acf_import_internal_post_type( lp_kit_cpt( 'actividad', $na['singular'], $na['plural'], 'f' === $na['genero'], $na['slug'], 'dashicons-palmtree', array() ), 'acf-post-type' );
			$hecho[] = 'Tipo de contenido «' . lp_ucfirst( $na['plural'] ) . '» (clave interna actividad, dirección /' . $na['slug'] . '/).';
		}
		$usados = lp_kit_nombres_usados( 'actividad' );
		if ( acf_get_field_group( 'group_lp_actividad' ) ) {
			$salteado[] = 'Grupo de datos de ' . $na['plural'] . ': ya existe, no se tocó.';
		} elseif ( $usados ) {
			$salteado[] = 'Grupo de datos de ' . $na['plural'] . ': ya hay campos en ACF (' . implode( ', ', array_slice( array_keys( $usados ), 0, 5 ) ) . '…). No se creó para no duplicar.';
		} else {
			acf_import_field_group(
				array(
					'key'                   => 'group_lp_actividad',
					'title'                 => 'Datos ' . $na['de'] . ' ' . $na['singular'],
					'fields'                => lp_kit_campos_actividad( $tipos, $nombre, $na ),
					'location'              => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'actividad' ) ) ),
					'position'              => 'acf_after_title',
					'style'                 => 'default',
					'label_placement'       => 'top',
					'instruction_placement' => 'label',
					'active'                => true,
					'show_in_rest'          => 1,
				)
			);
			$hecho[] = 'Grupo de campos «Datos ' . $na['de'] . ' ' . $na['singular'] . '» con ' . count( $tipos ) . ' tipos: ' . implode( ', ', wp_list_pluck( array_intersect_key( $cat, array_flip( $tipos ) ), 'label' ) ) . '.';

			// Panel.
			$pre = 'field_lp_act_';
			$campos = array( 'tipo_de_actividad', 'fecha_actividad', 'fecha_actividad_fin', 'hora_inicio', 'duracion', 'distancia', 'nivel', 'incluye', 'incluye_alimentacion', 'incluye_alojamiento', 'precio', 'maximo_personas', 'inscripcion', 'organiza', 'organizador', 'organizador_enlace', 'afiche', 'galeria' );
			$ajustes['tipos']['actividad'] = array(
				'activo'       => 1,
				'singular'     => $na['singular'],
				'plural'       => $na['plural'],
				'genero'       => $na['genero'],
				'crear'        => 1,
				'borrar'       => 1,
				'programar'    => 0,
				'nucleo'       => array( 'titulo', 'foto', 'contenido', 'extracto' ),
				'taxonomias'   => array(),
				'tax_unica'    => array(),
				'campos'       => array_map( function ( $n ) use ( $pre ) {
					return $pre . $n;
				}, $campos ),
				'fecha_inicio' => $pre . 'fecha_actividad',
				'fecha_fin'    => $pre . 'fecha_actividad_fin',
				'al_menos_uno' => array(),
				'al_menos_msg' => '',
				'orden'        => 1,
			);
			$cambio  = true;
			$hecho[] = 'Panel: ' . lp_ucfirst( $na['plural'] ) . ' activado (primero en el inicio), con todos los campos y las fechas.';
		}
	}

	// ---- Alojamiento ----
	if ( ! empty( $op['alojamiento'] ) ) {
		if ( taxonomy_exists( 'caracteristica' ) || lp_kit_acf_existe( 'caracteristica', 'acf-taxonomy' ) ) {
			$salteado[] = 'Taxonomía «caracteristica»: ya existe, no se tocó.';
		} else {
			acf_import_internal_post_type(
				array(
					'key'          => 'taxonomy_lp_caracteristica',
					'title'        => 'Características',
					'taxonomy'     => 'caracteristica',
					'object_type'  => array( 'alojamiento' ),
					'active'       => true,
					'labels'       => array(
						'name'          => 'Características',
						'singular_name' => 'Característica',
						'menu_name'     => 'Características',
						'all_items'     => 'Todas las características',
						'add_new_item'  => 'Añadir característica',
						'edit_item'     => 'Editar característica',
						'search_items'  => 'Buscar características',
						'not_found'     => 'No hay características',
					),
					'public'            => true,
					'hierarchical'      => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array(
						'permalink_rewrite'    => 'taxonomy_key',
						'slug'                 => '',
						'with_front'           => true,
						'rewrite_hierarchical' => false,
					),
				),
				'acf-taxonomy'
			);
			$hecho[] = 'Taxonomía «Características» (equipamiento y servicios del alojamiento).';
		}
		if ( post_type_exists( 'alojamiento' ) || lp_kit_acf_existe( 'alojamiento', 'acf-post-type' ) ) {
			$salteado[] = 'Tipo de contenido «alojamiento»: ya existe, no se tocó.';
		} else {
			acf_import_internal_post_type( lp_kit_cpt( 'alojamiento', $nl['singular'], $nl['plural'], 'f' === $nl['genero'], $nl['slug'], 'dashicons-admin-home', array( 'caracteristica' ) ), 'acf-post-type' );
			$hecho[] = 'Tipo de contenido «' . lp_ucfirst( $nl['plural'] ) . '» (clave interna alojamiento, dirección /' . $nl['slug'] . '/).';
		}
		$usados = lp_kit_nombres_usados( 'alojamiento' );
		if ( acf_get_field_group( 'group_lp_alojamiento' ) ) {
			$salteado[] = 'Grupo de datos de ' . $nl['plural'] . ': ya existe, no se tocó.';
		} elseif ( $usados ) {
			$salteado[] = 'Grupo de datos de ' . $nl['plural'] . ': ya hay campos en ACF. No se creó para no duplicar.';
		} else {
			acf_import_field_group(
				array(
					'key'                   => 'group_lp_alojamiento',
					'title'                 => 'Datos ' . $nl['de'] . ' ' . $nl['singular'],
					'fields'                => lp_kit_campos_alojamiento(),
					'location'              => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'alojamiento' ) ) ),
					'position'              => 'acf_after_title',
					'label_placement'       => 'top',
					'instruction_placement' => 'label',
					'active'                => true,
					'show_in_rest'          => 1,
				)
			);
			$hecho[] = 'Grupo de campos «Datos ' . $nl['de'] . ' ' . $nl['singular'] . '».';
			$pre = 'field_lp_aloj_';
			$ajustes['tipos']['alojamiento'] = array(
				'activo'       => 1,
				'singular'     => $nl['singular'],
				'plural'       => $nl['plural'],
				'genero'       => $nl['genero'],
				'crear'        => 1,
				'borrar'       => 0,
				'programar'    => 0,
				'nucleo'       => array( 'titulo', 'foto', 'contenido', 'extracto' ),
				'taxonomias'   => array( 'caracteristica' ),
				'tax_unica'    => array(),
				'campos'       => array_map( function ( $n ) use ( $pre ) {
					return $pre . $n;
				}, array( 'capacidad', 'camas', 'bano', 'precio', 'url_reserva', 'galeria' ) ),
				'fecha_inicio' => '',
				'fecha_fin'    => '',
				'al_menos_uno' => array(),
				'al_menos_msg' => '',
				'orden'        => 2,
			);
			$cambio  = true;
			$hecho[] = 'Panel: ' . lp_ucfirst( $nl['plural'] ) . ' activado (segundo en el inicio).';
		}
	}

	if ( $cambio ) {
		// Las Entradas no se activan solas: si no estaban configuradas, quedan fuera del panel.
		if ( empty( $ajustes['tipos']['post'] ) ) {
			$ajustes['tipos']['post'] = array_merge( lp_tipo_defecto( 'post', false ), array( 'orden' => 3 ) );
		}
		update_option( 'lp_ajustes', $ajustes );
	}
	if ( $hecho ) {
		update_option( 'lp_kit_flush', 1, false ); // Regenerar direcciones en la próxima carga.
	}
	return array( 'hecho' => $hecho, 'salteado' => $salteado );
}

// Direcciones de los tipos nuevos (después de que ACF los registre).
add_action( 'init', function () {
	if ( get_option( 'lp_kit_flush' ) ) {
		delete_option( 'lp_kit_flush' );
		flush_rewrite_rules( false );
	}
}, 99 );

// Envío del formulario del kit.
add_action( 'admin_post_lp_kit', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'lp_kit' );
	if ( ! function_exists( 'acf_import_field_group' ) || ! function_exists( 'acf_import_internal_post_type' ) ) {
		set_transient( 'lp_kit_res', array( 'hecho' => array(), 'salteado' => array( 'Hace falta ACF 6.1 o más nuevo, activo.' ) ), 300 );
	} else {
		$res = lp_kit_crear(
			array(
				'actividad'   => ! empty( $_POST['lp_kit_actividad'] ),
				'alojamiento' => ! empty( $_POST['lp_kit_alojamiento'] ),
				'tipos'       => array_map( 'sanitize_key', (array) wp_unslash( $_POST['lp_kit_tipos'] ?? array() ) ), // phpcs:ignore WordPress.Security
				'nombre'      => sanitize_text_field( wp_unslash( $_POST['lp_kit_nombre'] ?? '' ) ),
				'nombres'     => map_deep( (array) wp_unslash( $_POST['lp_kit_n'] ?? array() ), 'sanitize_text_field' ), // phpcs:ignore WordPress.Security
			)
		);
		set_transient( 'lp_kit_res', $res, 300 );
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=liquen-panel#lp-kit' ) );
	exit;
} );

// Caja del kit en Ajustes (fuera del formulario de ajustes).
function lp_kit_caja() {
	$res = get_transient( 'lp_kit_res' );
	if ( $res ) {
		delete_transient( 'lp_kit_res' );
	}
	$hay_act  = post_type_exists( 'actividad' );
	$hay_aloj = post_type_exists( 'alojamiento' );
	?>
	<div class="lp-box" id="lp-kit">
		<h2>Kit de inicio</h2>
		<p>Para un sitio nuevo: crea en ACF los tipos de contenido y los campos del estándar Liquen, y deja el panel configurado. Después todo se edita en ACF como siempre. <strong>No pisa nada que ya exista.</strong></p>
		<?php if ( $res ) : ?>
			<div class="notice <?php echo $res['hecho'] ? 'notice-success' : 'notice-warning'; ?> inline">
				<?php if ( $res['hecho'] ) : ?><p><strong>Creado:</strong></p><ul style="list-style:disc;margin-left:20px"><?php foreach ( $res['hecho'] as $l ) : ?><li><?php echo esc_html( $l ); ?></li><?php endforeach; ?></ul><?php endif; ?>
				<?php if ( $res['salteado'] ) : ?><p><strong>No se tocó:</strong></p><ul style="list-style:disc;margin-left:20px"><?php foreach ( $res['salteado'] as $l ) : ?><li><?php echo esc_html( $l ); ?></li><?php endforeach; ?></ul><?php endif; ?>
				<?php if ( $res['hecho'] ) : ?><p>Revisalo en <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=acf-field-group' ) ); ?>">ACF → Grupos de campos</a>. Si una dirección nueva da error, guardá Ajustes → Enlaces permanentes.</p><?php endif; ?>
			</div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lp_kit">
			<?php wp_nonce_field( 'lp_kit' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>Qué crear</th><td>
					<?php
					$filas = array(
						'actividad'   => array( 'Actividades', $hay_act, 'actividad', 'actividades', 'f', 'actividades', 'experiencia / experiencias, salida / salidas…' ),
						'alojamiento' => array( 'Alojamientos', $hay_aloj, 'alojamiento', 'alojamientos', 'm', 'alojamiento', 'cabaña / cabañas, habitación / habitaciones…' ),
					);
					foreach ( $filas as $k => $r ) :
						?>
						<p style="margin:0 0 4px"><label><input type="checkbox" name="lp_kit_<?php echo esc_attr( $k ); ?>" value="1" <?php checked( ! $r[1] ); ?>> <strong><?php echo esc_html( $r[0] ); ?></strong></label><?php echo $r[1] ? ' <span class="lp-off">(ya existe)</span>' : ''; ?></p>
						<p style="margin:0 0 14px 24px">
							Se llama <input type="text" name="lp_kit_n[<?php echo esc_attr( $k ); ?>][singular]" value="<?php echo esc_attr( $r[2] ); ?>" style="width:130px"> /
							<input type="text" name="lp_kit_n[<?php echo esc_attr( $k ); ?>][plural]" value="<?php echo esc_attr( $r[3] ); ?>" style="width:130px">
							<select name="lp_kit_n[<?php echo esc_attr( $k ); ?>][genero]"><option value="f" <?php selected( $r[4], 'f' ); ?>>femenino</option><option value="m" <?php selected( $r[4], 'm' ); ?>>masculino</option></select>
							· dirección <code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" name="lp_kit_n[<?php echo esc_attr( $k ); ?>][slug]" value="<?php echo esc_attr( $r[5] ); ?>" style="width:120px"><code>/</code>
							<br><span class="description">Ej.: <?php echo esc_html( $r[6] ); ?> Solo cambia lo que se ve; la clave interna y los nombres de los campos quedan iguales en todos los sitios.</span>
						</p>
					<?php endforeach; ?>
				</td></tr>
				<tr><th>Tipos que ofrece</th><td>
					<p class="lp-cols">
						<?php foreach ( lp_kit_catalogo() as $v => $t ) : ?>
							<label><input type="checkbox" name="lp_kit_tipos[]" value="<?php echo esc_attr( $v ); ?>" <?php checked( in_array( $v, array( 'caminata', 'taller', 'retiro' ), true ) ); ?>> <?php echo esc_html( $t['label'] ); ?></label>
						<?php endforeach; ?>
					</p>
					<p class="description">Según el tipo, el formulario muestra o esconde Distancia, Nivel y Duración. Después se agregan o quitan tipos en ACF (opciones del campo «Tipo de …»).</p>
				</td></tr>
				<tr><th><label for="lp-kit-nombre">Nombre del emprendimiento</label></th><td><input id="lp-kit-nombre" type="text" class="regular-text" name="lp_kit_nombre" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><p class="description">Para la opción «Organiza: …».</p></td></tr>
			</table>
			<?php submit_button( 'Crear', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
