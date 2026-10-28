<?php
/**
 * Al borrar el plugin: se quitan sus ajustes y su tarea programada.
 * Los contenidos y las imágenes no se tocan.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'lp_ajustes' );
delete_option( 'lp_rutas_slug' );
delete_transient( 'lp_ocupado' );
delete_option( 'lp_kit_flush' );
wp_clear_scheduled_hook( 'lp_limpieza_fotos' );
