<?php
/**
 * Actualizaciones automáticas desde GitHub (repo público).
 *
 * WordPress pregunta cada ~12 h si hay versiones nuevas. Acá le contestamos con la última
 * release de GitHub: si su número es mayor que LP_VERSION, aparece el aviso normal de
 * «Hay una nueva versión» y se actualiza con un clic (o solo, si se activan las automáticas).
 *
 * Se usa el zip adjunto a la release (liquen-panel.zip, lo arma GitHub Actions), que trae
 * la carpeta con el nombre correcto. Si la release no tiene ese zip, no se ofrece nada.
 * Si la API de GitHub no responde (límite por IP en hostings compartidos), se usa una
 * segunda vía sin la API.
 */

defined( 'ABSPATH' ) || exit;

define( 'LP_GITHUB_REPO', 'AleY9/liquen-panel' );
define( 'LP_GITHUB_ZIP', 'liquen-panel.zip' );

// Última release de GitHub, guardada 6 h (1 h si GitHub no respondió).
function lp_ultima_release() {
	$cache = get_site_transient( 'lp_ultima_release' );
	if ( false !== $cache ) {
		return $cache ?: null;
	}

	$resp = wp_remote_get(
		'https://api.github.com/repos/' . LP_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Liquen-Panel/' . LP_VERSION . '; ' . home_url(),
			),
		)
	);

	$datos = null;
	if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		$zip  = '';
		foreach ( (array) ( $json['assets'] ?? array() ) as $a ) {
			if ( LP_GITHUB_ZIP === ( $a['name'] ?? '' ) ) {
				$zip = $a['browser_download_url'];
				break;
			}
		}
		if ( ! empty( $json['tag_name'] ) && $zip ) {
			$datos = array(
				'version' => ltrim( $json['tag_name'], 'vV' ),
				'zip'     => $zip,
				'notas'   => (string) ( $json['body'] ?? '' ),
				'url'     => (string) ( $json['html_url'] ?? '' ),
				'fecha'   => (string) ( $json['published_at'] ?? '' ),
			);
		}
	}

	// Segunda vía, sin la API: github.com/…/releases/latest redirige a la última etiqueta.
	// La API limita las consultas por IP y en hostings compartidos se agota seguido.
	if ( ! $datos ) {
		$datos = lp_ultima_release_sin_api();
	}

	// Se guarda '' cuando no hay datos, para no consultar GitHub en cada carga (15 min y se reintenta).
	set_site_transient( 'lp_ultima_release', $datos ?: '', $datos ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
	return $datos;
}

function lp_ultima_release_sin_api() {
	$resp = wp_remote_head(
		'https://github.com/' . LP_GITHUB_REPO . '/releases/latest',
		array(
			'timeout'     => 10,
			'redirection' => 0,
			'headers'     => array( 'User-Agent' => 'Liquen-Panel/' . LP_VERSION . '; ' . home_url() ),
		)
	);
	if ( is_wp_error( $resp ) ) {
		return null;
	}
	$destino = (string) wp_remote_retrieve_header( $resp, 'location' );
	if ( ! preg_match( '#/releases/tag/(v?[0-9][0-9.]*)$#', $destino, $m ) ) {
		return null;
	}
	$zip = 'https://github.com/' . LP_GITHUB_REPO . '/releases/download/' . $m[1] . '/' . LP_GITHUB_ZIP;
	// El zip lo arma GitHub un minuto después de publicar: si todavía no está, no se ofrece nada.
	$hay = wp_remote_head( $zip, array( 'timeout' => 10, 'redirection' => 0 ) );
	if ( is_wp_error( $hay ) || ! in_array( (int) wp_remote_retrieve_response_code( $hay ), array( 200, 302 ), true ) ) {
		return null;
	}
	return array(
		'version' => ltrim( $m[1], 'vV' ),
		'zip'     => $zip,
		'notas'   => '',
		'url'     => 'https://github.com/' . LP_GITHUB_REPO . '/releases/tag/' . $m[1],
		'fecha'   => '',
	);
}

// Datos del plugin en el formato que espera WordPress.
function lp_datos_actualizacion( $r, $instalada = LP_VERSION ) {
	return (object) array(
		'id'           => 'github.com/' . LP_GITHUB_REPO,
		'slug'         => 'liquen-panel',
		'plugin'       => plugin_basename( LP_FILE ),
		'new_version'  => $r ? $r['version'] : $instalada,
		'url'          => 'https://github.com/' . LP_GITHUB_REPO,
		'package'      => $r ? $r['zip'] : '',
		'requires'     => '6.4',
		'requires_php' => '8.0',
		'icons'        => array(),
		'banners'      => array(),
	);
}

// Aviso de actualización en Plugins y en Escritorio → Actualizaciones.
add_filter( 'pre_set_site_transient_update_plugins', function ( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}
	$base = plugin_basename( LP_FILE );
	$r    = lp_ultima_release();
	// Versión instalada: la que WordPress leyó del archivo, no la constante en memoria.
	// Justo después de una actualización automática, la constante todavía es la vieja
	// y el aviso «Hay una nueva versión» quedaba colgado hasta la siguiente revisión.
	$instalada = (string) ( $transient->checked[ $base ] ?? LP_VERSION );

	if ( $r && version_compare( $r['version'], $instalada, '>' ) ) {
		$transient->response[ $base ] = lp_datos_actualizacion( $r );
		unset( $transient->no_update[ $base ] );
	} else {
		// Figurar en no_update habilita el enlace «Activar actualizaciones automáticas».
		$transient->no_update[ $base ] = lp_datos_actualizacion( null, $instalada );
		unset( $transient->response[ $base ] );
	}
	return $transient;
} );

// Ventana «Ver detalles» con las notas de la versión.
add_filter( 'plugins_api', function ( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== 'liquen-panel' ) {
		return $res;
	}
	$r = lp_ultima_release();
	if ( ! $r ) {
		return $res;
	}
	return (object) array(
		'name'          => 'Liquen Panel',
		'slug'          => 'liquen-panel',
		'version'       => $r['version'],
		'author'        => 'Liquen Web',
		'homepage'      => 'https://github.com/' . LP_GITHUB_REPO,
		'download_link' => $r['zip'],
		'requires'      => '6.4',
		'requires_php'  => '8.0',
		'last_updated'  => $r['fecha'],
		'sections'      => array(
			'changelog' => wpautop( esc_html( $r['notas'] ?: 'Versión ' . $r['version'] ) ),
		),
	);
}, 10, 3 );

// «Volver a comprobar» en Actualizaciones consulta GitHub en el momento.
add_action( 'load-update-core.php', function () {
	if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		delete_site_transient( 'lp_ultima_release' );
	}
} );

// Después de actualizar, olvidar lo guardado.
add_action( 'upgrader_process_complete', function ( $upgrader, $opciones ) {
	if ( 'update' === ( $opciones['action'] ?? '' ) && 'plugin' === ( $opciones['type'] ?? '' ) ) {
		delete_site_transient( 'lp_ultima_release' );
	}
}, 10, 2 );
