<?php
/**
 * Dirección del panel, acceso, sin índice, sin caché y manifiesto (ícono como app).
 * El panel no es una página de WordPress: es una dirección propia (/panel/), así no
 * aparece en menús ni en el mapa del sitio, y no depende de plantillas de Bricks.
 * Si la dirección ya la usa el sitio (una página, por ejemplo), el panel no la toma y avisa en Ajustes.
 */

defined( 'ABSPATH' ) || exit;

// Direcciones que nunca se toman (son de WordPress).
function lp_slugs_reservados() {
	return array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login', 'feed', 'page', 'comments', 'search', 'author', 'category', 'tag', 'embed', 'trackback' );
}

/**
 * ¿La dirección ya la usa el sitio?
 * Devuelve el ID de la página (o entrada) que la usa, -1 si es el archivo de un tipo de contenido,
 * -2 si es una dirección de WordPress, o 0 si está libre.
 * Solo cuenta la dirección exacta: las páginas hijas (/panel/algo/) nunca se tocan.
 */
function lp_slug_ocupado( $slug = null ) {
	$slug = null === $slug ? lp_config()['slug'] : $slug;
	$c    = get_transient( 'lp_ocupado' );
	$c    = is_array( $c ) ? $c : array();
	if ( isset( $c[ $slug ] ) ) {
		return (int) $c[ $slug ];
	}
	$v = 0;
	if ( in_array( $slug, lp_slugs_reservados(), true ) ) {
		$v = -2;
	}
	if ( ! $v ) {
		// Tipos que se publican en /slug/: páginas y tipos jerárquicos; entradas si el enlace es /%postname%/.
		$tipos = array_values( get_post_types( array( 'public' => true, 'hierarchical' => true ) ) );
		if ( '/%postname%/' === get_option( 'permalink_structure' ) ) {
			$tipos[] = 'post';
		}
		$p = $tipos ? get_page_by_path( $slug, OBJECT, $tipos ) : null;
		$v = $p && 'trash' !== $p->post_status ? (int) $p->ID : 0;
	}
	if ( ! $v ) {
		foreach ( get_post_types( array( 'has_archive' => true ), 'objects' ) as $pto ) {
			$a = true === $pto->has_archive ? ( ! empty( $pto->rewrite['slug'] ) ? $pto->rewrite['slug'] : $pto->name ) : $pto->has_archive;
			if ( trim( (string) $a, '/' ) === $slug ) {
				$v = -1;
				break;
			}
		}
	}
	$c[ $slug ] = $v;
	set_transient( 'lp_ocupado', $c, DAY_IN_SECONDS );
	return $v;
}

// Texto que explica qué usa la dirección.
function lp_slug_ocupado_txt( $slug = null ) {
	$slug = null === $slug ? lp_config()['slug'] : $slug;
	$v    = lp_slug_ocupado( $slug );
	if ( -2 === $v ) {
		return 'La dirección /' . $slug . '/ es de WordPress.';
	}
	if ( -1 === $v ) {
		return 'La dirección /' . $slug . '/ ya la usa el listado de un tipo de contenido.';
	}
	if ( $v > 0 ) {
		return 'La dirección /' . $slug . '/ ya la usa ' . ( 'page' === get_post_type( $v ) ? 'la página' : 'el contenido' ) . ' «' . get_the_title( $v ) . '».';
	}
	return '';
}

// Si se crea, cambia o borra una página, se vuelve a revisar la dirección.
foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $lp_h ) {
	add_action( $lp_h, function () {
		delete_transient( 'lp_ocupado' );
	} );
}
unset( $lp_h );

function lp_rutas() {
	if ( lp_slug_ocupado() ) {
		return; // No se toma una dirección que ya usa el sitio.
	}
	$slug = preg_quote( lp_config()['slug'], '#' );
	add_rewrite_rule( '^' . $slug . '/manifest\.webmanifest$', 'index.php?lp_panel=manifest', 'top' );
	add_rewrite_rule( '^' . $slug . '/?$', 'index.php?lp_panel=1', 'top' );
}

add_action( 'init', function () {
	lp_rutas();
	// Si cambió la dirección del panel (o se ocupó / se liberó), se regeneran las reglas una vez.
	$marca = lp_config()['slug'] . ( lp_slug_ocupado() ? '|ocupada' : '' );
	if ( get_option( 'lp_rutas_slug' ) !== $marca ) {
		flush_rewrite_rules( false );
		update_option( 'lp_rutas_slug', $marca, false );
	}
} );

add_filter( 'query_vars', function ( $v ) {
	$v[] = 'lp_panel';
	return $v;
} );

// Sin redirección canónica en el panel (evita saltos raros con los parámetros).
add_filter( 'redirect_canonical', function ( $url ) {
	return get_query_var( 'lp_panel' ) ? false : $url;
} );

add_action( 'template_redirect', function () {
	$q = get_query_var( 'lp_panel' );
	if ( ! $q || lp_slug_ocupado() ) {
		return;
	}
	if ( 'manifest' === $q ) {
		lp_manifiesto();
		exit;
	}

	// Nunca en caché (LiteSpeed y otros) ni en buscadores.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'X-LiteSpeed-Cache-Control: no-cache', true );

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( lp_url_actual() ) );
		exit;
	}
	status_header( 200 );
	if ( ! lp_puede_usar() ) {
		lp_render_documento( 'Sin acceso', lp_vista_sin_acceso() );
		exit;
	}
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		lp_render_documento( 'Falta ACF', '<div class="lp-aviso lp-aviso-error">El panel necesita el plugin Advanced Custom Fields activo.</div>' );
		exit;
	}
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security
		lp_manejar_post(); // redirige o deja errores para mostrar
	}
	lp_render();
	exit;
}, 0 );

// Dirección actual del panel con sus parámetros (para volver después del login).
function lp_url_actual() {
	$args = array();
	foreach ( array( 'v', 't', 'id' ) as $k ) {
		if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security
			$args[ $k ] = sanitize_key( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore WordPress.Security
		}
	}
	return lp_url( $args );
}

// En el acceso de WordPress, «Recuérdame» marcado cuando se viene del panel.
add_action( 'login_footer', function () {
	$r = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security
	if ( false === strpos( (string) $r, '/' . lp_config()['slug'] . '/' ) ) {
		return;
	}
	echo '<script>(function(){var c=document.getElementById("rememberme");if(c){c.checked=true;}})();</script>';
} );

// ---------- Manifiesto ----------

function lp_iconos() {
	$c     = lp_config();
	$out   = array();
	$icono = (int) $c['icono'];
	if ( $icono && wp_attachment_is_image( $icono ) ) {
		foreach ( array( 192, 512 ) as $t ) {
			$src = wp_get_attachment_image_src( $icono, array( $t, $t ) );
			if ( $src ) {
				$out[ $t ] = $src[0];
			}
		}
		$full = wp_get_attachment_image_src( $icono, 'full' );
		if ( $full && empty( $out[512] ) ) {
			$out[512] = $full[0];
		}
	}
	foreach ( array( 192, 512, 180 ) as $t ) {
		if ( empty( $out[ $t ] ) ) {
			$u = get_site_icon_url( $t );
			if ( $u ) {
				$out[ $t ] = $u;
			}
		}
	}
	return $out;
}

function lp_manifiesto() {
	$c     = lp_config();
	$icons = array();
	foreach ( lp_iconos() as $t => $u ) {
		if ( 180 === $t ) {
			continue;
		}
		$icons[] = array( 'src' => $u, 'sizes' => $t . 'x' . $t, 'type' => wp_check_filetype( $u )['type'] ? wp_check_filetype( $u )['type'] : 'image/png', 'purpose' => 'any' );
	}
	$m = array(
		'name'             => $c['nombre'],
		'short_name'       => $c['nombre_app'],
		'start_url'        => lp_url(),
		'scope'            => lp_url(),
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'background_color' => $c['colores']['fondo'],
		'theme_color'      => $c['colores']['principal'],
		'lang'             => 'es',
		'icons'            => $icons,
	);
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo wp_json_encode( $m, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
