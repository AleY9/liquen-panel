<?php
/**
 * Herramientas de los ajustes:
 * - Exportar / importar los ajustes en un archivo .json (pase de staging a producción, sitios nuevos).
 * - Aviso de campos nuevos de ACF que todavía no se revisaron en Ajustes → Liquen Panel.
 * - Protección contra guardar desde una pestaña vieja (número de revisión de los ajustes).
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Revisión de los ajustes (pestaña vieja) ---------- */

function lp_ajustes_rev() {
	return (string) get_option( 'lp_ajustes_rev', '' );
}

// Cada cambio de los ajustes, venga de donde venga (formulario, kit, importar), cambia la revisión.
function lp_ajustes_nueva_rev() {
	update_option( 'lp_ajustes_rev', uniqid( '', true ), false );
}
add_action( 'add_option_lp_ajustes', 'lp_ajustes_nueva_rev' );
add_action( 'update_option_lp_ajustes', 'lp_ajustes_nueva_rev' );

/* ---------- Campos nuevos de ACF ---------- */

// Campos soportados de los tipos activos que aparecieron en ACF después del último guardado de los ajustes.
function lp_campos_nuevos() {
	$g   = get_option( 'lp_ajustes', array() );
	$out = array();
	foreach ( (array) ( $g['tipos'] ?? array() ) as $slug => $t ) {
		// Sin «conocidos» = ajustes de antes de la 1.0.9: no se avisa hasta el primer guardado.
		if ( empty( $t['activo'] ) || ! isset( $t['conocidos'] ) || ! post_type_exists( $slug ) ) {
			continue;
		}
		foreach ( lp_campos_acf_de_tipo( $slug ) as $k => $f ) {
			if ( lp_campo_soportado( $f ) && ! in_array( $k, (array) $t['conocidos'], true ) && ! in_array( $k, (array) ( $t['campos'] ?? array() ), true ) ) {
				$out[ $slug ][ $k ] = $f['label'];
			}
		}
	}
	return $out;
}

// Ajustes de antes de la 1.0.9 no tienen la lista de campos ya vistos, y sin ella no hay aviso.
// Una vez por versión: se completa con los campos de ahora (igual que al guardar el formulario).
add_action( 'admin_init', function () {
	if ( get_option( 'lp_version_datos' ) === LP_VERSION || ! function_exists( 'acf_get_field_groups' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$g      = get_option( 'lp_ajustes', array() );
	$cambio = false;
	if ( is_array( $g ) ) {
		foreach ( (array) ( $g['tipos'] ?? array() ) as $slug => $t ) {
			if ( ! isset( $t['conocidos'] ) && post_type_exists( $slug ) ) {
				$g['tipos'][ $slug ]['conocidos'] = array_keys( lp_campos_acf_de_tipo( $slug ) );
				$cambio                           = true;
			}
		}
	}
	if ( $cambio ) {
		update_option( 'lp_ajustes', $g );
	}
	update_option( 'lp_version_datos', LP_VERSION, false );
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'acf_get_field_groups' ) ) {
		return;
	}
	$pantalla = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$donde    = array( 'dashboard', 'plugins', 'edit-acf-field-group', 'acf-field-group', 'settings_page_liquen-panel' );
	if ( ! $pantalla || ! in_array( $pantalla->id, $donde, true ) ) {
		return;
	}
	$nuevos = lp_campos_nuevos();
	if ( ! $nuevos ) {
		return;
	}
	$lineas = array();
	foreach ( $nuevos as $slug => $campos ) {
		$pto      = get_post_type_object( $slug );
		$lineas[] = '<strong>' . esc_html( $pto ? $pto->labels->name : $slug ) . ':</strong> ' . esc_html( implode( ', ', $campos ) );
	}
	echo '<div class="notice notice-warning"><p><strong>Liquen Panel:</strong> hay campos nuevos de ACF que no están en el formulario del panel.</p><p>' . implode( '<br>', $lineas ) . '</p><p>Si van en el panel, tildalos en <a href="' . esc_url( admin_url( 'options-general.php?page=liquen-panel&tab=contenidos' ) ) . '">Ajustes → Liquen Panel → Contenidos</a> y guardá. Si no, guardá igual y el aviso desaparece.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
} );

/* ---------- Exportar ---------- */

function lp_exportar_url() {
	return wp_nonce_url( admin_url( 'admin-post.php?action=lp_exportar' ), 'lp_exportar' );
}

add_action( 'admin_post_lp_exportar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'lp_exportar' );

	$ajustes = get_option( 'lp_ajustes', array() );
	$ajustes = is_array( $ajustes ) ? $ajustes : array();

	// Nombre de cada campo de ACF, para encontrarlo en otro sitio aunque su clave sea distinta.
	$nombres = array();
	foreach ( array_keys( (array) ( $ajustes['tipos'] ?? array() ) ) as $slug ) {
		foreach ( lp_campos_acf_de_tipo( $slug ) as $k => $f ) {
			$nombres[ $slug ][ $k ] = $f['name'];
		}
	}

	$datos = array(
		'liquen_panel'   => LP_VERSION,
		'sitio'          => home_url( '/' ),
		'fecha'          => gmdate( 'c' ),
		'ajustes'        => $ajustes,
		'campos_nombres' => $nombres,
		// El ícono es un ID de la biblioteca de este sitio: va también su dirección, para traerlo a otro.
		'icono_url'      => ! empty( $ajustes['icono'] ) ? (string) wp_get_attachment_url( (int) $ajustes['icono'] ) : '',
	);

	$host = sanitize_file_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="liquen-panel-' . $host . '-' . gmdate( 'Y-m-d' ) . '.json"' );
	echo wp_json_encode( $datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
} );

/* ---------- Importar ---------- */

// ¿El ID del ícono es la misma imagen en este sitio? (Si la dirección coincide, o si no hay dirección para comparar.)
function lp_icono_es_del_sitio( $id, $url ) {
	if ( ! wp_attachment_is_image( $id ) ) {
		return false;
	}
	return '' === $url || wp_get_attachment_url( $id ) === $url;
}

// Baja el ícono de otro sitio a la biblioteca de este. Devuelve el ID nuevo o 0.
function lp_traer_icono( $url ) {
	$url = esc_url_raw( $url );
	if ( '' === $url || ! preg_match( '#^https?://#i', $url ) || ! preg_match( '#\.(png|jpe?g|webp|gif)$#i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
		return 0;
	}
	// Si ya se trajo antes (otra importación), se reusa.
	$antes = get_posts( array(
		'post_type'      => 'attachment',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_lp_icono_origen', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	if ( $antes && wp_attachment_is_image( (int) $antes[0] ) ) {
		return (int) $antes[0];
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_sideload_image( $url, 0, 'Ícono del panel', 'id' );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( (int) $id, '_lp_icono_origen', $url );
	return (int) $id;
}


add_action( 'admin_post_lp_importar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'lp_importar' );

	$res  = array( 'ok' => false, 'avisos' => array() );
	$json = '';
	$arch = $_FILES['lp_archivo'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( $arch && UPLOAD_ERR_OK === ( $arch['error'] ?? -1 ) && is_uploaded_file( $arch['tmp_name'] ) && $arch['size'] < MB_IN_BYTES ) {
		$json = (string) file_get_contents( $arch['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$datos = json_decode( $json, true );

	if ( ! is_array( $datos ) || empty( $datos['liquen_panel'] ) || ! isset( $datos['ajustes'] ) || ! is_array( $datos['ajustes'] ) ) {
		$res['avisos'][] = 'El archivo no es una exportación de Liquen Panel. No se cambió nada.';
	} else {
		$res = lp_importar_ajustes( $datos['ajustes'], (array) ( $datos['campos_nombres'] ?? array() ), (string) ( $datos['icono_url'] ?? '' ) );
		if ( ! empty( $datos['sitio'] ) ) {
			array_unshift( $res['avisos'], 'Ajustes importados desde ' . $datos['sitio'] . ' (versión ' . $datos['liquen_panel'] . ').' );
		}
	}

	set_transient( 'lp_io_res_' . get_current_user_id(), $res, 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'options-general.php?page=liquen-panel&tab=exportar' ) );
	exit;
} );

// Adapta los ajustes de otro sitio a este y los guarda. Devuelve qué no se pudo pasar.
function lp_importar_ajustes( $aj, $nombres, $icono_url = '' ) {
	$avisos = array();

	// Ícono: es una imagen de la biblioteca. En el mismo sitio sirve el ID; en otro, se trae por su dirección.
	if ( ! empty( $aj['icono'] ) && ! lp_icono_es_del_sitio( (int) $aj['icono'], $icono_url ) ) {
		$nuevo       = lp_traer_icono( $icono_url );
		$aj['icono'] = $nuevo;
		if ( ! $nuevo ) {
			$avisos[] = 'No se pudo traer el ícono de la app: elegilo de nuevo en Apariencia.';
		}
	}

	$disponibles = lp_tipos_disponibles();
	foreach ( (array) ( $aj['tipos'] ?? array() ) as $slug => $t ) {
		if ( ! isset( $disponibles[ $slug ] ) ) {
			if ( ! empty( $t['activo'] ) ) {
				$avisos[] = 'El tipo «' . $slug . '» no existe en este sitio: se saltea. Si va, crealo (en ACF o con el Kit de inicio) e importá de nuevo.';
			}
			continue;
		}

		// Clave del campo en este sitio: la misma, o la del campo con el mismo nombre.
		$aca   = lp_campos_acf_de_tipo( $slug );
		$por_n = array();
		foreach ( $aca as $k => $f ) {
			$por_n[ $f['name'] ] = $k;
		}
		$mapear = function ( $k ) use ( $aca, $por_n, $nombres, $slug ) {
			if ( isset( $aca[ $k ] ) ) {
				return $k;
			}
			$nom = $nombres[ $slug ][ $k ] ?? '';
			return ( '' !== $nom && isset( $por_n[ $nom ] ) ) ? $por_n[ $nom ] : null;
		};

		$faltan = array();
		$campos = array();
		foreach ( (array) ( $t['campos'] ?? array() ) as $k ) {
			$m = $mapear( $k );
			if ( $m ) {
				$campos[] = $m;
			} else {
				$faltan[] = $nombres[ $slug ][ $k ] ?? $k;
			}
		}
		$t['campos'] = $campos;
		foreach ( array( 'fecha_inicio', 'fecha_fin' ) as $fk ) {
			$t[ $fk ] = ! empty( $t[ $fk ] ) ? (string) $mapear( $t[ $fk ] ) : '';
		}
		$t['al_menos_uno'] = array_values( array_filter( array_map( function ( $k ) use ( $mapear ) {
			return 'foto' === $k ? 'foto' : $mapear( $k );
		}, (array) ( $t['al_menos_uno'] ?? array() ) ) ) );
		if ( isset( $t['conocidos'] ) ) {
			$t['conocidos'] = array_values( array_filter( array_map( $mapear, (array) $t['conocidos'] ) ) );
		}
		$aj['tipos'][ $slug ] = $t;

		if ( $faltan && ! empty( $t['activo'] ) ) {
			$avisos[] = 'En «' . $disponibles[ $slug ]->labels->name . '» estos campos no existen en este sitio y se sacaron: ' . implode( ', ', $faltan ) . '.';
		}
	}

	update_option( 'lp_ajustes', $aj ); // Pasa por lp_admin_limpiar, igual que el formulario.
	delete_transient( 'lp_ocupado' );
	if ( lp_slug_ocupado() ) {
		$avisos[] = lp_slug_ocupado_txt() . ' El panel queda apagado hasta que elijas otra dirección en General.';
	}

	// Nuevas direcciones (si cambió la dirección del panel).
	update_option( 'lp_kit_flush', 1, false );

	return array( 'ok' => true, 'avisos' => $avisos );
}

/* ---------- Caja en Ajustes ---------- */

function lp_ajustes_caja() {
	$clave = 'lp_io_res_' . get_current_user_id();
	$res   = get_transient( $clave );
	if ( $res ) {
		delete_transient( $clave );
	}
	?>
	<div class="lp-box" id="lp-io">
		<h2>Exportar / importar ajustes</h2>
		<?php if ( $res ) : ?>
			<div class="notice <?php echo $res['ok'] ? 'notice-success' : 'notice-error'; ?> inline">
				<?php if ( $res['ok'] ) : ?><p><strong>Ajustes importados.</strong> Revisalos en las pestañas General, Contenidos y Apariencia antes de usar el panel.</p><?php endif; ?>
				<?php if ( $res['avisos'] ) : ?><ul style="list-style:disc;margin-left:20px"><?php foreach ( $res['avisos'] as $l ) : ?><li><?php echo esc_html( $l ); ?></li><?php endforeach; ?></ul><?php endif; ?>
			</div>
		<?php endif; ?>
		<p>Para pasar la configuración de un sitio a otro (por ejemplo, del staging a producción). Se lleva todo lo de esta página: general, apariencia y qué se carga desde el panel.</p>
		<p><a class="button" href="<?php echo esc_url( lp_exportar_url() ); ?>">Descargar ajustes (.json)</a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" onsubmit="return confirm('Se reemplazan todos los ajustes de este sitio por los del archivo. ¿Seguir?');">
			<input type="hidden" name="action" value="lp_importar">
			<?php wp_nonce_field( 'lp_importar' ); ?>
			<p><input type="file" name="lp_archivo" accept=".json,application/json" required> <?php submit_button( 'Importar ajustes', 'secondary', 'submit', false ); ?></p>
			<p class="description">En un sitio nuevo, primero crear los tipos de contenido y los campos (en ACF o con el Kit de inicio) y después importar: los campos se buscan por su nombre.</p>
		</form>
	</div>
	<?php
}
