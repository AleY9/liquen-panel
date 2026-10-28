<?php
/**
 * Configuración del panel: valores por defecto, lectura y textos.
 * Todo lo propio de cada sitio vive en la opción `lp_ajustes` (Ajustes → Liquen Panel).
 * Se puede ajustar por código con el filtro `lp_config`.
 */

defined( 'ABSPATH' ) || exit;

// Tipos de campo de ACF que el panel sabe mostrar y guardar.
function lp_tipos_acf_soportados() {
	return array( 'text', 'textarea', 'wysiwyg', 'number', 'url', 'email', 'select', 'radio', 'checkbox', 'button_group', 'true_false', 'date_picker', 'time_picker', 'image', 'group' );
}

// Grupo de ACF soportado = todos sus subcampos son imágenes (galería por casillas).
function lp_es_galeria( $campo ) {
	if ( empty( $campo['type'] ) || 'group' !== $campo['type'] || empty( $campo['sub_fields'] ) ) {
		return false;
	}
	foreach ( $campo['sub_fields'] as $s ) {
		if ( 'image' !== $s['type'] ) {
			return false;
		}
	}
	return true;
}

function lp_campo_soportado( $campo ) {
	if ( empty( $campo['type'] ) || ! in_array( $campo['type'], lp_tipos_acf_soportados(), true ) ) {
		return false;
	}
	return 'group' !== $campo['type'] || lp_es_galeria( $campo );
}

// Campos de ACF (de primer nivel) asignados a un tipo de contenido, en orden.
function lp_campos_acf_de_tipo( $post_type ) {
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		return array();
	}
	$out = array();
	foreach ( acf_get_field_groups( array( 'post_type' => $post_type ) ) as $g ) {
		foreach ( (array) acf_get_fields( $g ) as $f ) {
			$out[ $f['key'] ] = $f;
		}
	}
	return $out;
}

// Tipos de contenido que se pueden ofrecer en el panel.
function lp_tipos_disponibles() {
	$tipos = get_post_types( array( 'public' => true ), 'objects' );
	unset( $tipos['attachment'], $tipos['page'] );
	foreach ( array_keys( $tipos ) as $k ) {
		if ( 0 === strpos( $k, 'bricks' ) ) {
			unset( $tipos[ $k ] );
		}
	}
	return $tipos;
}

// Configuración por defecto de un tipo, armada con lo que hay en el sitio.
function lp_tipo_defecto( $slug, $activo = false ) {
	$pto   = get_post_type_object( $slug );
	$campos = lp_campos_acf_de_tipo( $slug );
	$keys   = array();
	$fechas = array();
	$imgs   = array();
	foreach ( $campos as $k => $f ) {
		if ( ! lp_campo_soportado( $f ) ) {
			continue;
		}
		$keys[] = $k;
		if ( 'date_picker' === $f['type'] ) {
			$fechas[] = $k;
		}
		if ( 'image' === $f['type'] ) {
			$imgs[] = $k;
		}
	}
	$nucleo = array( 'titulo' );
	if ( post_type_supports( $slug, 'thumbnail' ) ) {
		$nucleo[] = 'foto';
	}
	if ( post_type_supports( $slug, 'editor' ) ) {
		$nucleo[] = 'contenido';
	}
	$tax = array();
	foreach ( get_object_taxonomies( $slug, 'objects' ) as $t ) {
		if ( $t->hierarchical && $t->show_ui && 'post_format' !== $t->name ) {
			$tax[] = $t->name;
		}
	}
	$al_menos = ( in_array( 'foto', $nucleo, true ) && $imgs ) ? array_merge( array( 'foto' ), $imgs ) : array();
	return array(
		'activo'        => $activo ? 1 : 0,
		'singular'      => 'post' === $slug ? 'publicación' : mb_strtolower( $pto ? $pto->labels->singular_name : $slug ),
		'plural'        => 'post' === $slug ? 'publicaciones' : mb_strtolower( $pto ? $pto->labels->name : $slug ),
		'genero'        => 'post' === $slug ? 'f' : 'm',
		'crear'         => 1,
		'borrar'        => 1,
		'programar'     => 0,
		'nucleo'        => $nucleo,
		'taxonomias'    => $tax,
		'campos'        => $keys,
		'fecha_inicio'  => isset( $fechas[0] ) ? $fechas[0] : '',
		'fecha_fin'     => isset( $fechas[1] ) ? $fechas[1] : '',
		'al_menos_uno'  => $al_menos,
		'al_menos_msg'  => '',
		'orden'         => 99,
		'tax_unica'     => array(),
	);
}

function lp_config_defecto() {
	return array(
		'slug'        => 'panel',
		'nombre'      => get_bloginfo( 'name' ) . ' · Panel',
		'nombre_app'  => 'Panel',
		'roles'       => array( 'administrator', 'editor' ),
		'whatsapp'    => '',
		'max_px'      => 2048,
		'biblioteca'  => 1,
		'icono'       => 0,
		'colores'     => array(
			'principal' => '#1f4645',
			'acento'    => '#9a6055',
			'fondo'     => '#f6f4ef',
			'texto'     => '#23302f',
			'error'     => '#b3261e',
		),
		'fuente'      => '',
		'fuente_url'  => '',
		'tipos'       => array(),
	);
}

// Configuración completa (guardada + valores por defecto).
function lp_config() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$def = lp_config_defecto();
	$g   = get_option( 'lp_ajustes', array() );
	$g   = is_array( $g ) ? $g : array();
	$c   = array_merge( $def, $g );
	$c['colores'] = array_merge( $def['colores'], isset( $g['colores'] ) && is_array( $g['colores'] ) ? $g['colores'] : array() );
	$c['roles']   = array_values( array_filter( (array) $c['roles'] ) );
	$c['slug']    = trim( sanitize_title( $c['slug'] ), '/' );
	$c['slug']    = '' === $c['slug'] ? 'panel' : $c['slug'];
	// Sin tipos configurados: entradas activas con todo lo que tengan.
	if ( empty( $g['tipos'] ) ) {
		$c['tipos'] = array( 'post' => lp_tipo_defecto( 'post', true ) );
	}
	$cache = apply_filters( 'lp_config', $c );
	return $cache;
}

// Tipos activos en el panel (que existen en el sitio).
function lp_tipos_activos() {
	$out = array();
	foreach ( lp_config()['tipos'] as $slug => $t ) {
		if ( ! empty( $t['activo'] ) && post_type_exists( $slug ) ) {
			$out[ $slug ] = array_merge( array( 'orden' => 99, 'tax_unica' => array() ), $t );
		}
	}
	// Orden del inicio del panel (a igual número, el orden de siempre).
	$i = 0;
	foreach ( $out as $slug => $t ) {
		$out[ $slug ]['_i'] = $i++;
	}
	uasort( $out, function ( $a, $b ) {
		return ( (int) $a['orden'] <=> (int) $b['orden'] ) ?: ( $a['_i'] <=> $b['_i'] );
	} );
	return $out;
}

function lp_tipo( $slug ) {
	$a = lp_tipos_activos();
	return isset( $a[ $slug ] ) ? $a[ $slug ] : null;
}

// Textos que cambian con el género del nombre del tipo.
function lp_txt( $tipo, $clave ) {
	$f = ( isset( $tipo['genero'] ) && 'f' === $tipo['genero'] );
	$s = isset( $tipo['singular'] ) ? $tipo['singular'] : '';
	$p = isset( $tipo['plural'] ) ? $tipo['plural'] : '';
	switch ( $clave ) {
		case 'nueva':
			return ( $f ? 'Nueva ' : 'Nuevo ' ) . $s;
		case 'mis':
			return 'Mis ' . $p;
		case 'publicada':
			return $f ? 'Publicada' : 'Publicado';
		case 'programada':
			return $f ? 'Programada' : 'Programado';
		case 'la':
			return ( $f ? 'la ' : 'el ' ) . $s;
		case 'ninguna':
			return $f ? 'Todavía no hay ninguna.' : 'Todavía no hay ninguno.';
		case 'borrada':
			return ( $f ? 'Se borró la ' : 'Se borró el ' ) . $s . '. Si fue un error, se recupera desde la papelera de WordPress.';
	}
	return '';
}

function lp_ucfirst( $s ) {
	return mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
}

// Dirección del panel.
function lp_url( $args = array() ) {
	$base = home_url( '/' . lp_config()['slug'] . '/' );
	return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $base ) : $base;
}

// ¿El usuario actual puede usar el panel?
function lp_puede_usar( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}
	return (bool) array_intersect( lp_config()['roles'], (array) $user->roles );
}
