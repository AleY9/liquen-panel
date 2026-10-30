<?php
/**
 * Plugin Name:       Liquen Panel
 * Description:       Back office en el front para cargar y editar contenidos (entradas y tipos de contenido con campos ACF) desde el celular, sin entrar a wp-admin.
 * Version:           1.2.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Liquen Web
 * Text Domain:       liquen-panel
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/AleY9/liquen-panel
 *
 * Ajustes: wp-admin → Ajustes → Liquen Panel.
 * Panel:   /panel/ (configurable).
 */

defined( 'ABSPATH' ) || exit;

define( 'LP_VERSION', '1.2.1' );
define( 'LP_FILE', __FILE__ );
define( 'LP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LP_URL', plugin_dir_url( __FILE__ ) );

require_once LP_DIR . 'includes/compat.php';
require_once LP_DIR . 'includes/config.php';
require_once LP_DIR . 'includes/campos.php';
require_once LP_DIR . 'includes/acceso.php';
require_once LP_DIR . 'includes/acciones.php';
require_once LP_DIR . 'includes/modulos.php';
require_once LP_DIR . 'includes/vistas.php';
require_once LP_DIR . 'includes/admin.php';
require_once LP_DIR . 'includes/ajustes.php';
require_once LP_DIR . 'includes/diagnostico.php';
require_once LP_DIR . 'includes/kit.php';
require_once LP_DIR . 'includes/actualizaciones.php';

register_activation_hook( __FILE__, 'lp_activar' );
register_deactivation_hook( __FILE__, 'lp_desactivar' );

function lp_activar() {
	lp_rutas();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'lp_limpieza_fotos' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lp_limpieza_fotos' );
	}
	// Que WordPress vuelva a preguntar por actualizaciones: así aparece enseguida
	// el enlace «Activar actualizaciones automáticas».
	delete_site_transient( 'update_plugins' );
}

function lp_desactivar() {
	wp_clear_scheduled_hook( 'lp_limpieza_fotos' );
	flush_rewrite_rules();
}
