<?php
/**
 * Pantallas del panel: inicio, lista («Mis …»), formulario.
 * El panel es un documento propio (no usa el tema), pensado para el celular.
 */

defined( 'ABSPATH' ) || exit;

function lp_e( $s ) {
	return esc_html( (string) $s );
}

// ---------- Documento ----------

function lp_render_documento( $titulo, $cuerpo ) {
	$c      = lp_config();
	$col    = $c['colores'];
	$iconos = lp_iconos();
	$vars   = array();
	foreach ( array( 'principal', 'acento', 'fondo', 'texto', 'error' ) as $k ) {
		$vars[] = '--lp-' . $k . ':' . ( sanitize_hex_color( $col[ $k ] ) ? sanitize_hex_color( $col[ $k ] ) : lp_config_defecto()['colores'][ $k ] );
	}
	if ( '' !== trim( (string) $c['fuente'] ) ) {
		$vars[] = '--lp-fuente:' . preg_replace( '/[^a-zA-Z0-9 ,\'"\-]/', '', $c['fuente'] ) . ',system-ui,sans-serif';
	}
	$js = array(
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'nonce'  => wp_create_nonce( 'lp_subir' ),
		'maxPx'  => max( 800, min( 4000, (int) $c['max_px'] ) ),
		'nonceBib' => wp_create_nonce( 'lp_biblioteca' ),
	);
	?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?php echo lp_e( $titulo . ' · ' . $c['nombre_app'] ); ?></title>
<link rel="manifest" href="<?php echo esc_url( lp_url() . 'manifest.webmanifest' ); ?>">
<meta name="theme-color" content="<?php echo esc_attr( $col['principal'] ); ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $c['nombre_app'] ); ?>">
<?php if ( ! empty( $iconos[180] ) ) : ?><link rel="apple-touch-icon" href="<?php echo esc_url( $iconos[180] ); ?>"><?php endif; ?>
<?php if ( ! empty( $iconos[192] ) ) : ?><link rel="icon" href="<?php echo esc_url( $iconos[192] ); ?>"><?php endif; ?>
<?php if ( '' !== trim( (string) $c['fuente_url'] ) && preg_match( '#^https://fonts\.(googleapis|bunny)\.(com|net)/#', $c['fuente_url'] ) ) : ?><link rel="stylesheet" href="<?php echo esc_url( $c['fuente_url'] ); ?>"><?php endif; ?>
<link rel="stylesheet" href="<?php echo esc_url( LP_URL . 'assets/panel.css?v=' . LP_VERSION ); ?>">
<style>:root{<?php echo esc_html( implode( ';', $vars ) ); ?>}</style>
</head>
<body>
<header class="lp-barra">
	<a class="lp-marca" href="<?php echo esc_url( lp_url() ); ?>"><?php echo lp_e( $c['nombre_app'] ); ?></a>
	<nav>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">Ver sitio ↗</a>
		<?php if ( is_user_logged_in() ) : ?><a href="<?php echo esc_url( wp_logout_url( lp_url() ) ); ?>">Salir</a><?php endif; ?>
	</nav>
</header>
<main class="lp-main">
<?php echo $cuerpo; // phpcs:ignore WordPress.Security -- armado y escapado en cada vista ?>
</main>
<script>window.LP=<?php echo wp_json_encode( $js ); ?>;</script>
<script src="<?php echo esc_url( LP_URL . 'assets/panel.js?v=' . LP_VERSION ); ?>"></script>
</body>
</html>
	<?php
}

function lp_render() {
	$v    = isset( $_GET['v'] ) ? sanitize_key( wp_unslash( $_GET['v'] ) ) : ''; // phpcs:ignore WordPress.Security
	$slug = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security
	$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['lp_t'], $_POST['lp_accion'] ) && 'guardar' === $_POST['lp_accion'] ) { // phpcs:ignore WordPress.Security
		// Volvemos a mostrar el formulario que se envió (con sus errores).
		$slug = sanitize_key( wp_unslash( $_POST['lp_t'] ) ); // phpcs:ignore WordPress.Security
		$id   = isset( $_POST['lp_id'] ) ? (int) $_POST['lp_id'] : 0; // phpcs:ignore WordPress.Security
		$v    = $id ? 'editar' : 'nuevo';
	}
	$tipo = lp_tipo( $slug );
	if ( $tipo && 'lista' === $v ) {
		lp_render_documento( lp_ucfirst( lp_txt( $tipo, 'mis' ) ), lp_vista_lista( $slug ) );
	} elseif ( $tipo && 'nuevo' === $v && lp_puede_crear( $slug ) ) {
		lp_render_documento( lp_ucfirst( lp_txt( $tipo, 'nueva' ) ), lp_vista_form( $slug, 0 ) );
	} elseif ( $tipo && 'editar' === $v && lp_puede_editar( $id, $slug ) ) {
		lp_render_documento( 'Editar ' . $tipo['singular'], lp_vista_form( $slug, $id ) );
	} else {
		lp_render_documento( 'Inicio', lp_vista_inicio() );
	}
}

// ---------- Avisos ----------

function lp_aviso_msg( $tipo = null ) {
	$m = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : ''; // phpcs:ignore WordPress.Security
	$t = $tipo ? $tipo : array( 'genero' => 'm', 'singular' => 'contenido' );
	$ok = array(
		'publicado'  => '¡Listo! ' . lp_txt( $t, 'publicada' ) . ' ✓',
		'programado' => '¡Listo! ' . lp_txt( $t, 'programada' ) . ' ✓',
		'borrador'   => 'Guardado como borrador ✓ No se ve en la web.',
		'guardado'   => 'Guardado ✓',
		'revision'   => 'Guardado ✓ Queda pendiente de revisión.',
		'copia'      => 'Listo, es una copia (en borrador). Poné la fecha nueva, revisá el resto y publicá.',
		'borrado'    => lp_txt( $t, 'borrada' ),
	);
	$mal = array(
		'sinpermiso' => 'No tenés permiso para hacer eso.',
		'error'      => 'Algo salió mal. Probá de nuevo.',
		'faltan'     => 'Para publicar falta completar lo que está marcado.',
	);
	$h = '';
	if ( isset( $ok[ $m ] ) ) {
		$h .= '<div class="lp-aviso lp-aviso-ok" role="status">' . lp_e( $ok[ $m ] ) . '</div>';
	} elseif ( isset( $mal[ $m ] ) ) {
		$h .= '<div class="lp-aviso lp-aviso-error" role="alert">' . lp_e( $mal[ $m ] ) . '</div>';
	}
	if ( ! empty( $GLOBALS['lp_estado']['aviso'] ) ) {
		$h .= '<div class="lp-aviso lp-aviso-error" role="alert">' . lp_e( $GLOBALS['lp_estado']['aviso'] ) . '</div>';
	}
	return $h;
}

function lp_vista_sin_acceso() {
	$u = wp_get_current_user();
	return '<div class="lp-card"><h1>Sin acceso</h1><p>La cuenta <strong>' . lp_e( $u->user_login ) . '</strong> no tiene acceso a este panel.</p><p><a class="lp-btn" href="' . esc_url( wp_logout_url( lp_url() ) ) . '">Entrar con otra cuenta</a></p></div>';
}

// ---------- Inicio ----------

function lp_vista_inicio() {
	$c  = lp_config();
	$u  = wp_get_current_user();
	$h  = lp_aviso_msg();
	$h .= '<h1 class="lp-h1">Hola, ' . lp_e( $u->first_name ? $u->first_name : $u->display_name ) . '</h1>';
	$tipos = lp_tipos_activos();
	if ( ! $tipos ) {
		$h .= '<div class="lp-card">Todavía no hay nada para cargar desde el panel. ' . ( current_user_can( 'manage_options' ) ? '<a href="' . esc_url( admin_url( 'options-general.php?page=liquen-panel' ) ) . '">Configurarlo</a>.' : '' ) . '</div>';
		return $h;
	}
	foreach ( $tipos as $slug => $t ) {
		$ids  = lp_contenidos( $slug );
		$grp  = lp_agrupar( $slug, $ids );
		$h   .= '<section class="lp-card lp-tipo">';
		$h   .= '<h2>' . lp_e( lp_ucfirst( $t['plural'] ) ) . '</h2>';
		if ( lp_puede_crear( $slug ) ) {
			$h .= '<a class="lp-btn lp-btn-grande" href="' . esc_url( lp_url( array( 'v' => 'nuevo', 't' => $slug ) ) ) . '">+ ' . lp_e( lp_txt( $t, 'nueva' ) ) . '</a>';
		}
		$resumen = array();
		foreach ( $grp as $g ) {
			if ( $g['ids'] && empty( $g['plegado'] ) ) {
				$n         = count( $g['ids'] );
				$palabra   = mb_strtolower( $g['titulo'] );
				if ( 1 === $n ) {
					$palabra = 'borradores' === $palabra ? 'borrador' : preg_replace( '/s$/u', '', $palabra );
				}
				$resumen[] = $n . ' ' . $palabra;
			}
		}
		$h .= '<a class="lp-btn lp-btn-sec" href="' . esc_url( lp_url( array( 'v' => 'lista', 't' => $slug ) ) ) . '">' . lp_e( lp_txt( $t, 'mis' ) ) . ( $resumen ? ' <span class="lp-s">' . lp_e( implode( ' · ', $resumen ) ) . '</span>' : '' ) . '</a>';
		$h .= '</section>';
	}
	if ( '' !== trim( (string) $c['whatsapp'] ) ) {
		$num = preg_replace( '/\D+/', '', $c['whatsapp'] );
		$h  .= '<section class="lp-ayuda"><strong>¿Necesitás una mano?</strong><a class="lp-btn lp-btn-sec" href="' . esc_url( 'https://wa.me/' . $num . '?text=' . rawurlencode( 'Hola, necesito ayuda con el panel de ' . get_bloginfo( 'name' ) ) ) . '" target="_blank" rel="noopener">Escribir por WhatsApp</a></section>';
	}
	return $h;
}

// ---------- Lista ----------

// Contenidos que el usuario puede ver en el panel (los suyos, o todos si puede editar los de otros).
function lp_contenidos( $slug ) {
	$pto  = get_post_type_object( $slug );
	$args = array(
		'post_type'      => $slug,
		'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'posts_per_page' => 300,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	if ( ! current_user_can( $pto->cap->edit_others_posts ) ) {
		$args['author'] = get_current_user_id();
	}
	return array_map( 'intval', get_posts( $args ) );
}

// Fechas de un contenido: array( inicio Ymd, fin Ymd ) o ''.
function lp_fechas( $slug, $id ) {
	$t   = lp_tipo( $slug );
	$ini = $t['fecha_inicio'] ? preg_replace( '/\D/', '', (string) get_post_meta( $id, lp_nombre_campo( $t['fecha_inicio'] ), true ) ) : '';
	$fin = $t['fecha_fin'] ? preg_replace( '/\D/', '', (string) get_post_meta( $id, lp_nombre_campo( $t['fecha_fin'] ), true ) ) : '';
	$ini = 8 === strlen( $ini ) ? $ini : '';
	$fin = 8 === strlen( $fin ) && $ini && $fin >= $ini ? $fin : '';
	return array( $ini, $fin );
}

function lp_nombre_campo( $key ) {
	static $cache = array();
	if ( ! isset( $cache[ $key ] ) ) {
		$f             = function_exists( 'acf_get_field' ) ? acf_get_field( $key ) : null;
		$cache[ $key ] = $f ? $f['name'] : '';
	}
	return $cache[ $key ];
}

/**
 * Agrupa los contenidos. Con fechas: Próximas / Sin fecha / Pasadas. Sin fechas: Publicados / Borradores.
 * Devuelve lista de array( titulo, ids, plegado ).
 */
function lp_agrupar( $slug, $ids ) {
	$t = lp_tipo( $slug );
	if ( ! $t['fecha_inicio'] ) {
		$pub = array();
		$bor = array();
		foreach ( $ids as $id ) {
			if ( in_array( get_post_status( $id ), array( 'publish', 'future', 'private' ), true ) ) {
				$pub[] = $id;
			} else {
				$bor[] = $id;
			}
		}
		return array(
			array( 'titulo' => 'f' === $t['genero'] ? 'Publicadas' : 'Publicados', 'ids' => $pub ),
			array( 'titulo' => 'Borradores', 'ids' => $bor ),
		);
	}
	$hoy = current_time( 'Ymd' );
	$prox = array();
	$sin  = array();
	$pas  = array();
	foreach ( $ids as $id ) {
		list( $ini, $fin ) = lp_fechas( $slug, $id );
		if ( ! $ini ) {
			$sin[] = $id;
		} elseif ( ( $fin ? $fin : $ini ) >= $hoy ) {
			$prox[ $id ] = $ini;
		} else {
			$pas[ $id ] = $ini;
		}
	}
	asort( $prox );
	arsort( $pas );
	return array(
		array( 'titulo' => 'f' === $t['genero'] ? 'Próximas' : 'Próximos', 'ids' => array_keys( $prox ) ),
		array( 'titulo' => 'Sin fecha', 'ids' => $sin, 'plegado' => true ),
		array( 'titulo' => 'f' === $t['genero'] ? 'Pasadas' : 'Pasados', 'ids' => array_keys( $pas ), 'plegado' => true ),
	);
}

function lp_fecha_corta( $ini, $fin ) {
	if ( ! $ini ) {
		return '';
	}
	$ti = strtotime( $ini . ' 12:00' );
	$y  = substr( $ini, 0, 4 ) !== current_time( 'Y' ) ? ' Y' : '';
	if ( ! $fin || $fin === $ini ) {
		return date_i18n( 'D j M' . $y, $ti );
	}
	$tf = strtotime( $fin . ' 12:00' );
	if ( substr( $ini, 0, 6 ) === substr( $fin, 0, 6 ) ) {
		return date_i18n( 'j', $ti ) . '–' . date_i18n( 'j M' . $y, $tf );
	}
	return date_i18n( 'j M', $ti ) . ' – ' . date_i18n( 'j M' . $y, $tf );
}

function lp_chip_estado( $tipo, $id ) {
	$e = get_post_status( $id );
	$m = array(
		'publish' => array( lp_txt( $tipo, 'publicada' ), 'ok' ),
		'future'  => array( lp_txt( $tipo, 'programada' ) . ' · ' . date_i18n( 'j M H:i', strtotime( get_post_field( 'post_date', $id ) ) ), 'info' ),
		'pending' => array( 'En revisión', 'info' ),
		'private' => array( 'Privado', 'info' ),
		'draft'   => array( 'Borrador', 'gris' ),
	);
	$x = isset( $m[ $e ] ) ? $m[ $e ] : $m['draft'];
	return '<span class="lp-chip lp-chip-' . $x[1] . '">' . lp_e( $x[0] ) . '</span>';
}

// Imagen para la tarjeta: foto destacada o la primera imagen cargada.
function lp_miniatura( $slug, $id ) {
	$att = (int) get_post_thumbnail_id( $id );
	if ( ! $att ) {
		foreach ( lp_items( $slug ) as $it ) {
			if ( in_array( $it['tipo'], array( 'image', 'galeria' ), true ) ) {
				$v = lp_leer( $it, $id );
				$v = is_array( $v ) ? ( $v[0] ?? 0 ) : $v;
				if ( $v ) {
					$att = (int) $v;
					break;
				}
			}
		}
	}
	return $att ? wp_get_attachment_image_url( $att, 'thumbnail' ) : '';
}

function lp_url_ver( $id ) {
	return 'publish' === get_post_status( $id ) ? get_permalink( $id ) : get_preview_post_link( $id );
}

// Botón que envía una acción (formulario chico con nonce).
function lp_boton_accion( $slug, $id, $accion, $texto, $extra = array(), $clase = '', $confirma = '' ) {
	$h  = '<form method="post" class="lp-f-accion">';
	$h .= '<input type="hidden" name="lp_accion" value="' . esc_attr( $accion ) . '"><input type="hidden" name="lp_t" value="' . esc_attr( $slug ) . '"><input type="hidden" name="lp_id" value="' . (int) $id . '">';
	foreach ( $extra as $k => $v ) {
		$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	$h .= wp_nonce_field( 'lp_' . $accion . '_' . $slug . '_' . $id, '_lpnonce', false, false );
	$h .= '<button type="submit" class="lp-mini ' . esc_attr( $clase ) . '"' . ( $confirma ? ' data-confirma="' . esc_attr( $confirma ) . '"' : '' ) . '>' . lp_e( $texto ) . '</button></form>';
	return $h;
}

function lp_tarjeta( $slug, $id ) {
	$tipo = lp_tipo( $slug );
	list( $ini, $fin ) = $tipo['fecha_inicio'] ? lp_fechas( $slug, $id ) : array( '', '' );
	$img  = lp_miniatura( $slug, $id );
	$est  = get_post_status( $id );
	$res  = isset( $_GET['id'] ) && (int) $_GET['id'] === $id ? ' lp-item-resaltado' : ''; // phpcs:ignore WordPress.Security
	$h    = '<article class="lp-item' . $res . '" id="lp-' . (int) $id . '" data-t="' . esc_attr( get_the_title( $id ) ) . '">';
	$h   .= '<a class="lp-item-cab" href="' . esc_url( lp_url( array( 'v' => 'editar', 't' => $slug, 'id' => $id ) ) ) . '">';
	$h   .= '<span class="lp-item-img"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '>' . ( $img ? '' : 'Sin imagen' ) . '</span>';
	$h   .= '<span class="lp-item-info"><strong>' . lp_e( get_the_title( $id ) ? get_the_title( $id ) : '(sin título)' ) . '</strong>';
	if ( $ini ) {
		$h .= '<span class="lp-item-fecha">' . lp_e( lp_fecha_corta( $ini, $fin ) ) . '</span>';
	}
	$h .= lp_chip_estado( $tipo, $id ) . '</span></a>';
	$h .= '<div class="lp-item-acc">';
	$h .= '<a class="lp-mini" href="' . esc_url( lp_url( array( 'v' => 'editar', 't' => $slug, 'id' => $id ) ) ) . '">Editar</a>';
	$h .= '<a class="lp-mini" href="' . esc_url( lp_url_ver( $id ) ) . '" target="_blank" rel="noopener">Ver</a>';
	if ( lp_puede_crear( $slug ) ) {
		$h .= lp_boton_accion( $slug, $id, 'duplicar', 'Duplicar' );
	}
	if ( in_array( $est, array( 'publish', 'future', 'private' ), true ) ) {
		$h .= lp_boton_accion( $slug, $id, 'estado', 'Pasar a borrador', array( 'lp_a' => 'borrador' ), '', '¿Seguro?' );
	} else {
		$h .= lp_boton_accion( $slug, $id, 'estado', 'Publicar', array( 'lp_a' => 'publicar' ), 'lp-mini-prin' );
	}
	if ( ! empty( $tipo['borrar'] ) && current_user_can( 'delete_post', $id ) ) {
		$h .= lp_boton_accion( $slug, $id, 'borrar', 'Borrar', array(), 'lp-mini-peligro', '¿Seguro?' );
	}
	return $h . '</div></article>';
}

function lp_vista_lista( $slug ) {
	$t  = lp_tipo( $slug );
	$h  = '<a class="lp-volver" href="' . esc_url( lp_url() ) . '">← Inicio</a>';
	$h .= '<h1 class="lp-h1">' . lp_e( lp_ucfirst( lp_txt( $t, 'mis' ) ) ) . '</h1>';
	$h .= lp_aviso_msg( $t );
	if ( lp_puede_crear( $slug ) ) {
		$h .= '<a class="lp-btn lp-btn-grande" href="' . esc_url( lp_url( array( 'v' => 'nuevo', 't' => $slug ) ) ) . '">+ ' . lp_e( lp_txt( $t, 'nueva' ) ) . '</a>';
	}
	$ids = lp_contenidos( $slug );
	if ( ! $ids ) {
		return $h . '<p class="lp-vacio">' . lp_e( lp_txt( $t, 'ninguna' ) ) . '</p>';
	}
	$ver = 'f' === $t['genero'] ? 'Ver todas' : 'Ver todos';
	$max = (int) apply_filters( 'lp_lista_max_plegado', 10, $slug );
	if ( count( $ids ) > 5 ) {
		$h .= '<input type="search" class="lp-buscar" placeholder="Buscar por título" aria-label="Buscar por título" autocomplete="off">';
	}
	$h .= '<div class="lp-listas">';
	foreach ( lp_agrupar( $slug, $ids ) as $g ) {
		if ( ! $g['ids'] ) {
			continue;
		}
		$plegado = ! empty( $g['plegado'] );
		$lista   = '';
		foreach ( array_values( $g['ids'] ) as $i => $id ) {
			$tar = lp_tarjeta( $slug, $id );
			// En los grupos plegados se ven las primeras; el resto con «Ver todas».
			if ( $plegado && $i >= $max && false === strpos( $tar, 'lp-item-resaltado' ) ) {
				$tar = str_replace( '<article class="lp-item', '<article hidden class="lp-mas lp-item', $tar );
			}
			$lista .= $tar;
		}
		$n     = count( $g['ids'] );
		$boton = $plegado && $n > $max ? '<button type="button" class="lp-btn lp-btn-sec lp-ver-todas">' . lp_e( $ver ) . ' (' . $n . ')</button>' : '';
		if ( $plegado ) {
			$abierto = false !== strpos( $lista, 'lp-item-resaltado' ) ? ' open' : '';
			$h      .= '<details class="lp-grupo"' . $abierto . '><summary>' . lp_e( $g['titulo'] ) . ' <span class="lp-s">(' . $n . ')</span></summary>' . $lista . $boton . '</details>';
		} else {
			$h .= '<section class="lp-grupo"><h2>' . lp_e( $g['titulo'] ) . ' <span class="lp-s">(' . $n . ')</span></h2>' . $lista . '</section>';
		}
	}
	$h .= '<p class="lp-vacio lp-sin-resultados" hidden></p></div>';
	return $h;
}

// ---------- Formulario ----------

function lp_vista_form( $slug, $id ) {
	$t       = lp_tipo( $slug );
	$estado  = $GLOBALS['lp_estado'];
	$vals    = ( $estado && isset( $estado['vals'] ) ) ? $estado['vals'] : ( $id ? lp_valores_post( $slug, $id ) : lp_valores_nuevos( $slug ) );
	$err     = ( $estado && isset( $estado['err'] ) ) ? $estado['err'] : array();
	$post_st = $id ? get_post_status( $id ) : '';

	// Faltantes al venir de «Publicar» en la lista.
	if ( $id && ! $err && isset( $_GET['msg'] ) && 'faltan' === $_GET['msg'] ) { // phpcs:ignore WordPress.Security
		list( , $err ) = lp_validar( $slug, $vals, $vals, true, $id );
	}

	$h  = '<a class="lp-volver" href="' . esc_url( lp_url( array( 'v' => 'lista', 't' => $slug ) ) ) . '">← ' . lp_e( lp_ucfirst( lp_txt( $t, 'mis' ) ) ) . '</a>';
	$h .= '<div class="lp-form-cab"><h1 class="lp-h1">' . lp_e( $id ? 'Editar ' . $t['singular'] : lp_ucfirst( lp_txt( $t, 'nueva' ) ) ) . '</h1>' . ( $id ? lp_chip_estado( $t, $id ) : '' ) . '</div>';
	$h .= lp_aviso_msg( $t );
	if ( $err ) {
		$h .= '<div class="lp-aviso lp-aviso-error" role="alert"><strong>Revisá:</strong><ul>';
		foreach ( $err as $k => $m ) {
			$h .= '<li><a href="#' . esc_attr( lp_dom_id( $k ) ) . '">' . lp_e( $m ) . '</a></li>';
		}
		$h .= '</ul></div>';
	}

	$h .= '<form method="post" class="lp-form" novalidate>';
	$h .= '<input type="hidden" name="lp_accion" value="guardar"><input type="hidden" name="lp_t" value="' . esc_attr( $slug ) . '"><input type="hidden" name="lp_id" value="' . (int) $id . '">';
	$h .= wp_nonce_field( 'lp_guardar_' . $slug . '_' . $id, '_lpnonce', false, false );

	foreach ( lp_items( $slug ) as $it ) {
		$h .= lp_campo_html( $slug, $it, $vals[ $it['id'] ] ?? null, $err[ $it['id'] ] ?? '', $id, $vals );
	}

	// Programar la publicación.
	if ( ! empty( $t['programar'] ) && ! in_array( $post_st, array( 'publish' ), true ) ) {
		$prog = '';
		if ( 'future' === $post_st ) {
			$prog = mysql2date( 'Y-m-d\TH:i', get_post_field( 'post_date', $id ) );
		} elseif ( isset( $_POST['lp_programar'] ) ) { // phpcs:ignore WordPress.Security
			$prog = sanitize_text_field( wp_unslash( $_POST['lp_programar'] ) ); // phpcs:ignore WordPress.Security
		}
		$h .= '<details class="lp-campo lp-programar"' . ( $prog ? ' open' : '' ) . '><summary>Programar la publicación (opcional)</summary>';
		$h .= '<p class="lp-ayuda">Si elegís día y hora, al tocar «Publicar» queda programada y aparece sola en la web en ese momento.</p>';
		$h .= '<input type="datetime-local" name="lp_programar" value="' . esc_attr( $prog ) . '" min="' . esc_attr( current_time( 'Y-m-d\TH:i' ) ) . '"></details>';
	}

	// Botones.
	$h .= '<div class="lp-botonera">';
	if ( in_array( $post_st, array( 'publish', 'private' ), true ) ) {
		$h .= '<button type="submit" name="lp_boton" value="guardar" class="lp-btn">Guardar cambios</button>';
	} elseif ( 'future' === $post_st ) {
		$h .= '<button type="submit" name="lp_boton" value="guardar" class="lp-btn lp-btn-sec">Guardar</button>';
		$h .= '<button type="submit" name="lp_boton" value="publicar" class="lp-btn">Publicar</button>';
	} else {
		$h .= '<button type="submit" name="lp_boton" value="borrador" class="lp-btn lp-btn-sec">Guardar borrador</button>';
		$h .= '<button type="submit" name="lp_boton" value="publicar" class="lp-btn">Publicar</button>';
	}
	$h .= '</div>';
	if ( $id ) {
		$h .= '<p class="lp-ver"><a href="' . esc_url( lp_url_ver( $id ) ) . '" target="_blank" rel="noopener">Ver cómo queda ↗</a></p>';
	}
	return $h . '</form>';
}

function lp_dom_id( $id ) {
	return 'lp-c-' . preg_replace( '/[^a-z0-9_-]/i', '-', $id );
}

function lp_nombre( $id ) {
	return 'lp[' . $id . ']';
}

function lp_campo_html( $slug, $it, $v, $error, $post_id, $vals = array() ) {
	$tipo   = lp_tipo( $slug );
	$id     = $it['id'];
	$dom    = lp_dom_id( $id );
	$f      = isset( $it['acf'] ) ? $it['acf'] : array();
	$req    = $it['requerido'] ? ' <span class="lp-req" aria-hidden="true">*</span>' : '';
	$ayuda  = $it['ayuda'] ? '<p class="lp-ayuda">' . lp_e( $it['ayuda'] ) . '</p>' : '';
	$clase  = 'lp-campo lp-campo-' . $it['tipo'] . ( $error ? ' lp-con-error' : '' );
	if ( ! empty( $f['wrapper']['width'] ) && (int) $f['wrapper']['width'] < 100 ) {
		$clase .= ' lp-medio';
	}
	$err    = $error ? '<p class="lp-error">' . lp_e( $error ) . '</p>' : '';
	$label  = '<label class="lp-label" for="' . esc_attr( $dom ) . '">' . lp_e( $it['label'] ) . $req . '</label>';
	$max    = ! empty( $f['maxlength'] ) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '';
	$ph     = isset( $f['placeholder'] ) && '' !== (string) $f['placeholder'] ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
	$name   = esc_attr( lp_nombre( $id ) );
	$campo  = '';

	switch ( $it['tipo'] ) {
		case 'titulo':
			$campo = '<input type="text" id="' . $dom . '" name="' . $name . '" value="' . esc_attr( $v ) . '" maxlength="150" required autocomplete="off">';
			break;

		case 'contenido':
		case 'wysiwyg':
			$html = '';
			if ( $post_id ) {
				$html = 'contenido' === $it['tipo'] ? get_post_field( 'post_content', $post_id ) : get_post_meta( $post_id, $f['name'], true );
			}
			if ( $post_id && '' !== trim( (string) $html ) && ! lp_texto_editable( $html ) ) {
				$campo = '<div class="lp-bloqueado">Este texto tiene formato especial (negritas, listas, imágenes o bloques). Para no romperlo, editalo desde el escritorio de WordPress. <a href="' . esc_url( get_edit_post_link( $post_id, 'url' ) ) . '" target="_blank" rel="noopener">Abrir en WordPress ↗</a></div>';
				$campo .= '<div class="lp-bloqueado-texto">' . nl2br( lp_e( wp_trim_words( lp_html_a_texto( $html ), 60 ) ) ) . '</div>';
				$label = '<span class="lp-label">' . lp_e( $it['label'] ) . '</span>';
			} else {
				$campo = '<textarea id="' . $dom . '" name="' . $name . '" rows="8">' . esc_textarea( (string) $v ) . '</textarea>';
				$ayuda .= '<p class="lp-ayuda">Separá los párrafos con una línea en blanco. Los enlaces (https://…) quedan activos solos.</p>';
			}
			break;

		case 'extracto':
		case 'textarea':
			$rows  = ! empty( $f['rows'] ) ? (int) $f['rows'] : 3;
			$campo = '<textarea id="' . $dom . '" name="' . $name . '" rows="' . max( 2, $rows ) . '"' . ( 'extracto' === $it['tipo'] ? ' maxlength="400"' : $max ) . $ph . '>' . esc_textarea( (string) $v ) . '</textarea>';
			break;

		case 'text':
		case 'url':
		case 'email':
			$attr  = 'url' === $it['tipo'] ? ' inputmode="url" autocapitalize="off"' : ( 'email' === $it['tipo'] ? ' inputmode="email" autocapitalize="off"' : '' );
			$campo = lp_con_extremos( $f, '<input type="' . ( 'email' === $it['tipo'] ? 'email' : 'text' ) . '" id="' . $dom . '" name="' . $name . '" value="' . esc_attr( $v ) . '"' . $max . $ph . $attr . '>' );
			break;

		case 'number':
			$step  = ! empty( $f['step'] ) ? $f['step'] : 'any';
			$campo = lp_con_extremos( $f, '<input type="number" inputmode="decimal" step="' . esc_attr( $step ) . '" id="' . $dom . '" name="' . $name . '" value="' . esc_attr( $v ) . '"' . ( '' !== ( $f['min'] ?? '' ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '' ) . ( '' !== ( $f['max'] ?? '' ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '' ) . $ph . '>' );
			break;

		case 'date_picker':
			$min = '';
			if ( $tipo['fecha_fin'] === $id && $tipo['fecha_inicio'] ) {
				$min = ' data-desde="' . esc_attr( lp_dom_id( $tipo['fecha_inicio'] ) ) . '"';
			}
			$campo = '<input type="date" id="' . $dom . '" name="' . $name . '" value="' . esc_attr( $v ) . '"' . $min . '>';
			if ( (string) $v ) {
				$campo .= '<button type="button" class="lp-limpiar" data-limpiar="' . esc_attr( $dom ) . '">Quitar fecha</button>';
			}
			break;

		case 'time_picker':
			$campo = '<input type="time" id="' . $dom . '" name="' . $name . '" value="' . esc_attr( $v ) . '">';
			break;

		case 'true_false':
			$texto = ! empty( $f['message'] ) ? $f['message'] : 'Sí';
			$label = '<span class="lp-label">' . lp_e( $it['label'] ) . '</span>';
			$campo = '<input type="hidden" name="' . $name . '" value="0"><label class="lp-switch"><input type="checkbox" id="' . $dom . '" name="' . $name . '" value="1"' . checked( '1', (string) $v, false ) . '><span class="lp-switch-ui" aria-hidden="true"></span><span>' . lp_e( $texto ) . '</span></label>';
			break;

		case 'select':
		case 'radio':
		case 'button_group':
			$choices = (array) $f['choices'];
			$label   = '<span class="lp-label" id="' . $dom . '-l">' . lp_e( $it['label'] ) . $req . '</span>';
			if ( count( $choices ) <= 6 ) {
				$campo = '<div class="lp-opciones" role="radiogroup" aria-labelledby="' . $dom . '-l" id="' . $dom . '">';
				if ( ! empty( $f['allow_null'] ) || 'select' === $it['tipo'] ) {
					$campo .= '<label class="lp-op"><input type="radio" name="' . $name . '" value=""' . checked( '', (string) $v, false ) . '><span>Sin indicar</span></label>';
				}
				foreach ( $choices as $val => $txt ) {
					$campo .= '<label class="lp-op"><input type="radio" name="' . $name . '" value="' . esc_attr( $val ) . '"' . checked( (string) $val, (string) $v, false ) . '><span>' . lp_e( $txt ) . '</span></label>';
				}
				$campo .= '</div>';
			} else {
				$label  = '<label class="lp-label" for="' . esc_attr( $dom ) . '">' . lp_e( $it['label'] ) . $req . '</label>';
				$campo  = '<select id="' . $dom . '" name="' . $name . '"><option value="">Elegí…</option>';
				foreach ( $choices as $val => $txt ) {
					$campo .= '<option value="' . esc_attr( $val ) . '"' . selected( (string) $val, (string) $v, false ) . '>' . lp_e( $txt ) . '</option>';
				}
				$campo .= '</select>';
			}
			break;

		case 'checkbox':
			$label = '<span class="lp-label">' . lp_e( $it['label'] ) . $req . '</span>';
			$campo = '<div class="lp-checks" id="' . $dom . '">';
			foreach ( (array) $f['choices'] as $val => $txt ) {
				$campo .= '<label class="lp-op"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr( $val ) . '"' . ( in_array( (string) $val, (array) $v, true ) ? ' checked' : '' ) . '><span>' . lp_e( $txt ) . '</span></label>';
			}
			$campo .= '</div>';
			break;

		case 'tax':
			$label = '<span class="lp-label">' . lp_e( $it['label'] ) . $req . '</span>';
			$campo = '<div class="lp-checks" id="' . $dom . '">' . lp_terminos_html( $it['tax'], (array) $v, $name, 0, 0, ! empty( $it['unica'] ) ) . '</div>';
			break;

		case 'foto':
		case 'image':
			$label = '<span class="lp-label">' . lp_e( $it['label'] ) . $req . '</span>';
			$campo = '<div id="' . $dom . '">' . lp_slot_html( $name, (int) $v, $it['label'] ) . '</div>';
			break;

		case 'galeria':
			$n     = count( $f['sub_fields'] );
			$v     = (array) $v;
			$label = '<span class="lp-label">' . lp_e( $it['label'] ) . ' <span class="lp-s">(hasta ' . $n . ')</span></span>';
			$campo = '<div class="lp-galeria" id="' . $dom . '" data-max="' . $n . '" data-name="' . $name . '[]">';
			for ( $i = 0; $i < min( $n, count( $v ) + 1 ); $i++ ) {
				$campo .= lp_slot_html( $name . '[]', isset( $v[ $i ] ) ? (int) $v[ $i ] : 0, 'Foto ' . ( $i + 1 ), true );
			}
			$campo .= '</div>';
			break;
	}
	// Lógica condicional de ACF: el navegador la reevalúa al cambiar otros campos.
	$cond = lp_cond_js( $slug, $it, $vals, $post_id );
	$attr = '';
	if ( $cond ) {
		$attr = ' data-lp-cond="' . esc_attr( $cond ) . '"' . ( lp_cond_visible( $it, $vals, $post_id ) ? '' : ' hidden' );
	}
	return '<div class="' . esc_attr( $clase ) . '"' . $attr . '>' . $label . $ayuda . $campo . $err . '</div>';
}

// Texto antes / después del campo (ACF prepend / append), ej. «personas», «km».
function lp_con_extremos( $f, $input ) {
	$pre = ! empty( $f['prepend'] ) ? '<span class="lp-extremo">' . lp_e( $f['prepend'] ) . '</span>' : '';
	$pos = ! empty( $f['append'] ) ? '<span class="lp-extremo">' . lp_e( $f['append'] ) . '</span>' : '';
	return ( $pre || $pos ) ? '<div class="lp-con-extremos">' . $pre . $input . $pos . '</div>' : $input;
}

function lp_terminos_html( $tax, $sel, $name, $padre = 0, $nivel = 0, $unica = false ) {
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'parent' => $padre, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return 0 === $nivel ? '<p class="lp-s">No hay opciones cargadas.</p>' : '';
	}
	$h = '';
	foreach ( $terms as $t ) {
		$h .= '<label class="lp-op' . ( $nivel ? ' lp-op-hijo' : '' ) . '"><input type="' . ( $unica ? 'radio' : 'checkbox' ) . '" name="' . $name . '[]" value="' . (int) $t->term_id . '"' . ( in_array( (int) $t->term_id, array_map( 'intval', $sel ), true ) ? ' checked' : '' ) . '><span>' . lp_e( $t->name ) . '</span></label>';
		if ( $nivel < 2 ) {
			$h .= lp_terminos_html( $tax, $sel, $name, $t->term_id, $nivel + 1, $unica );
		}
	}
	return $h;
}

// Casilla de imagen: vista previa, subir/cambiar, quitar. El valor (ID) va en un campo oculto.
function lp_slot_html( $name, $att, $label, $corto = false ) {
	$url = $att ? wp_get_attachment_image_url( $att, 'medium_large' ) : '';
	$h   = '<div class="lp-slot' . ( $url ? ' lp-slot-lleno' : '' ) . '">';
	$h  .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . ( $att ? (int) $att : '' ) . '">';
	$h  .= '<div class="lp-slot-img">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<span class="lp-slot-vacio">Sin imagen</span>' ) . '<span class="lp-slot-estado" hidden></span></div>';
	$h  .= '<div class="lp-slot-btns"><label class="lp-mini lp-mini-prin"><input type="file" accept="image/*" hidden aria-label="' . esc_attr( 'Subir ' . $label ) . '"><span>' . ( $url ? 'Cambiar' : ( $corto ? 'Subir' : 'Subir imagen' ) ) . '</span></label>';
	if ( lp_biblioteca_activa() ) {
		$h .= '<button type="button" class="lp-mini lp-elegir">Biblioteca</button>';
	}
	$h  .= '<button type="button" class="lp-mini lp-quitar"' . ( $url ? '' : ' hidden' ) . '>Quitar</button></div>';
	$h  .= '<p class="lp-error lp-slot-error" hidden></p></div>';
	return $h;
}
