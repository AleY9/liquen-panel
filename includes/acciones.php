<?php
/**
 * Acciones del panel: guardar, publicar / pasar a borrador, duplicar, borrar,
 * subir imágenes y limpiar las imágenes que quedaron sin usar.
 * Todo se comprueba en el servidor: sesión, permisos de WordPress sobre cada contenido,
 * nonce, campos permitidos (solo los configurados) y límite de envíos.
 */

defined( 'ABSPATH' ) || exit;

// Errores y valores del último envío fallido, para volver a mostrar el formulario.
$GLOBALS['lp_estado'] = null;

function lp_limite_ok() {
	$clave = 'lp_rl_' . get_current_user_id();
	$n     = (int) get_transient( $clave );
	if ( $n >= 60 ) {
		return false;
	}
	set_transient( $clave, $n + 1, 10 * MINUTE_IN_SECONDS );
	return true;
}

function lp_ir( $args ) {
	wp_safe_redirect( lp_url( $args ) );
	exit;
}

// ¿Puede el usuario actual editar este contenido desde el panel?
function lp_puede_editar( $id, $slug ) {
	return $id && get_post_type( $id ) === $slug && lp_tipo( $slug ) && current_user_can( 'edit_post', $id )
		&& in_array( get_post_status( $id ), array( 'publish', 'draft', 'pending', 'future', 'private' ), true );
}

function lp_puede_crear( $slug ) {
	$tipo = lp_tipo( $slug );
	$pto  = get_post_type_object( $slug );
	return $tipo && ! empty( $tipo['crear'] ) && $pto && current_user_can( $pto->cap->create_posts );
}

function lp_manejar_post() {
	if ( lp_modulo_manejar_post() ) {
		return; // Era de un complemento (ya se revisó nonce, permiso y límite).
	}
	$accion = isset( $_POST['lp_accion'] ) ? sanitize_key( wp_unslash( $_POST['lp_accion'] ) ) : ''; // phpcs:ignore WordPress.Security
	$slug   = isset( $_POST['lp_t'] ) ? sanitize_key( wp_unslash( $_POST['lp_t'] ) ) : ''; // phpcs:ignore WordPress.Security
	$id     = isset( $_POST['lp_id'] ) ? (int) $_POST['lp_id'] : 0; // phpcs:ignore WordPress.Security
	if ( ! $accion || ! lp_tipo( $slug ) ) {
		return;
	}
	$nonce = isset( $_POST['_lpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_lpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security
	if ( ! wp_verify_nonce( $nonce, 'lp_' . $accion . '_' . $slug . '_' . $id ) ) {
		$GLOBALS['lp_estado'] = array( 'aviso' => 'La página estaba abierta hace mucho. Volvé a intentarlo.' );
		return;
	}
	if ( ! lp_limite_ok() ) {
		$GLOBALS['lp_estado'] = array( 'aviso' => 'Hiciste muchos cambios seguidos. Esperá unos minutos y probá de nuevo.' );
		return;
	}
	switch ( $accion ) {
		case 'guardar':
			lp_accion_guardar( $slug, $id );
			break;
		case 'estado':
			lp_accion_estado( $slug, $id );
			break;
		case 'duplicar':
			lp_accion_duplicar( $slug, $id );
			break;
		case 'borrar':
			lp_accion_borrar( $slug, $id );
			break;
	}
}

// Fecha y hora de programación ('Y-m-d\TH:i', hora del sitio) → DateTime futuro, o null.
function lp_fecha_programada() {
	$v = isset( $_POST['lp_programar'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_programar'] ) ) : ''; // phpcs:ignore WordPress.Security
	if ( '' === $v ) {
		return null;
	}
	$d = DateTime::createFromFormat( '!Y-m-d\TH:i', $v, wp_timezone() );
	if ( ! $d || $d->format( 'Y-m-d\TH:i' ) !== $v || $d->getTimestamp() <= time() + 60 ) {
		return null;
	}
	return $d;
}

function lp_accion_guardar( $slug, $id ) {
	if ( $id && ! lp_puede_editar( $id, $slug ) ) {
		lp_ir( array( 'msg' => 'sinpermiso' ) );
	}
	if ( ! $id && ! lp_puede_crear( $slug ) ) {
		lp_ir( array( 'msg' => 'sinpermiso' ) );
	}
	$boton = isset( $_POST['lp_boton'] ) ? sanitize_key( wp_unslash( $_POST['lp_boton'] ) ) : 'guardar'; // phpcs:ignore WordPress.Security
	$prog  = ( 'publicar' === $boton && ! empty( lp_tipo( $slug )['programar'] ) ) ? lp_fecha_programada() : null;

	// Un borrador se puede guardar incompleto (menos el título); publicar exige todo.
	$estricto = ! ( 'borrador' === $boton || ( $id && 'draft' === get_post_status( $id ) && 'guardar' === $boton ) );
	$actuales = $id ? lp_valores_post( $slug, $id ) : lp_valores_nuevos( $slug );
	$recibido = lp_valores_request( $slug );
	list( $vals, $err ) = lp_validar( $slug, $recibido, $actuales, $estricto, $id );

	if ( $err ) {
		foreach ( $vals as $k => $v ) {
			if ( null === $v ) {
				$vals[ $k ] = $actuales[ $k ];
			}
		}
		$GLOBALS['lp_estado'] = array( 'vals' => $vals, 'err' => $err );
		return;
	}

	$pto    = get_post_type_object( $slug );
	$antes  = $id ? get_post_status( $id ) : '';
	$puede_publicar = current_user_can( $pto->cap->publish_posts );
	if ( 'borrador' === $boton ) {
		$estado = 'draft';
	} elseif ( 'publicar' === $boton ) {
		$estado = $puede_publicar ? ( $prog ? 'future' : 'publish' ) : 'pending';
	} else { // guardar cambios: queda como estaba
		$estado = $antes ? $antes : 'draft';
	}

	$post = array(
		'post_type'   => $slug,
		'post_status' => $estado,
		'post_title'  => $vals['titulo'],
	);
	if ( $id ) {
		$post['ID'] = $id;
	}
	if ( array_key_exists( 'contenido', $vals ) && null !== $vals['contenido'] ) {
		// Solo se reescribe si cambió (así no se toca el formato de un texto que no se editó).
		if ( ! $id || $vals['contenido'] !== $actuales['contenido'] ) {
			$post['post_content'] = lp_texto_a_bloques( $vals['contenido'] );
		}
	}
	if ( array_key_exists( 'extracto', $vals ) ) {
		$post['post_excerpt'] = $vals['extracto'];
	}
	if ( 'future' === $estado && $prog ) {
		$post['post_date']     = $prog->format( 'Y-m-d H:i:s' );
		$post['post_date_gmt'] = get_gmt_from_date( $post['post_date'] );
		$post['edit_date']     = true;
	} elseif ( 'publish' === $estado && 'future' === $antes ) {
		// Programada que se publica ya: fecha de hoy.
		$post['post_date']     = current_time( 'mysql' );
		$post['post_date_gmt'] = current_time( 'mysql', true );
		$post['edit_date']     = true;
	}

	$res = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $res ) || ! $res ) {
		$GLOBALS['lp_estado'] = array( 'vals' => $vals, 'err' => array(), 'aviso' => 'No se pudo guardar. Probá de nuevo.' );
		return;
	}
	$id = (int) $res;
	lp_escribir( $slug, $id, $vals );

	// Las imágenes subidas desde el panel quedan adjuntas a este contenido.
	foreach ( lp_imagenes_de( $slug, $vals ) as $att ) {
		if ( ! (int) get_post_field( 'post_parent', $att ) && get_post_meta( $att, '_lp_panel_foto', true ) ) {
			wp_update_post( array( 'ID' => $att, 'post_parent' => $id ) );
		}
	}

	$msg = array( 'publish' => 'publicado', 'future' => 'programado', 'pending' => 'revision', 'draft' => 'borrador' );
	$msg = ( 'guardar' === $boton && $antes === $estado ) ? 'guardado' : ( isset( $msg[ $estado ] ) ? $msg[ $estado ] : 'guardado' );
	lp_ir( array( 'v' => 'lista', 't' => $slug, 'msg' => $msg, 'id' => $id ) );
}

function lp_accion_estado( $slug, $id ) {
	if ( ! lp_puede_editar( $id, $slug ) ) {
		lp_ir( array( 'msg' => 'sinpermiso' ) );
	}
	$a = isset( $_POST['lp_a'] ) ? sanitize_key( wp_unslash( $_POST['lp_a'] ) ) : ''; // phpcs:ignore WordPress.Security
	if ( 'borrador' === $a ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		lp_ir( array( 'v' => 'lista', 't' => $slug, 'msg' => 'borrador', 'id' => $id ) );
	}
	if ( 'publicar' === $a ) {
		$pto = get_post_type_object( $slug );
		list( , $err ) = lp_validar( $slug, lp_valores_post( $slug, $id ), lp_valores_post( $slug, $id ), true, $id );
		if ( $err ) {
			lp_ir( array( 'v' => 'editar', 't' => $slug, 'id' => $id, 'msg' => 'faltan' ) );
		}
		$upd = array( 'ID' => $id, 'post_status' => current_user_can( $pto->cap->publish_posts ) ? 'publish' : 'pending' );
		if ( 'future' === get_post_status( $id ) || strtotime( get_post_field( 'post_date_gmt', $id ) . ' UTC' ) > time() ) {
			$upd['post_date']     = current_time( 'mysql' );
			$upd['post_date_gmt'] = current_time( 'mysql', true );
			$upd['edit_date']     = true;
		}
		wp_update_post( $upd );
		lp_ir( array( 'v' => 'lista', 't' => $slug, 'msg' => 'publish' === $upd['post_status'] ? 'publicado' : 'revision', 'id' => $id ) );
	}
}

function lp_accion_duplicar( $slug, $id ) {
	if ( ! lp_puede_editar( $id, $slug ) || ! lp_puede_crear( $slug ) ) {
		lp_ir( array( 'msg' => 'sinpermiso' ) );
	}
	$orig  = get_post( $id );
	$nuevo = wp_insert_post( wp_slash( array(
		'post_type'    => $slug,
		'post_status'  => 'draft',
		'post_title'   => $orig->post_title,
		'post_content' => $orig->post_content,
		'post_excerpt' => $orig->post_excerpt,
		'post_author'  => get_current_user_id(),
	) ), true );
	if ( is_wp_error( $nuevo ) ) {
		lp_ir( array( 'v' => 'lista', 't' => $slug, 'msg' => 'error' ) );
	}
	// Todos los campos y la foto destacada (las imágenes se comparten, no se duplican).
	$saltar = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', '_encloseme', '_pingme' );
	foreach ( get_post_meta( $id ) as $k => $valores ) {
		if ( in_array( $k, $saltar, true ) || 0 === strpos( $k, '_lp_' ) ) {
			continue;
		}
		foreach ( $valores as $v ) {
			add_post_meta( $nuevo, $k, maybe_unserialize( $v ) );
		}
	}
	foreach ( get_object_taxonomies( $slug ) as $tx ) {
		$t = wp_get_object_terms( $id, $tx, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $t ) && $t ) {
			wp_set_object_terms( $nuevo, array_map( 'intval', $t ), $tx, false );
		}
	}
	// La copia sale sin fechas: hay que poner la nueva.
	$tipo = lp_tipo( $slug );
	foreach ( array( 'fecha_inicio', 'fecha_fin' ) as $fk ) {
		if ( ! empty( $tipo[ $fk ] ) ) {
			update_field( $tipo[ $fk ], '', $nuevo );
		}
	}
	lp_ir( array( 'v' => 'editar', 't' => $slug, 'id' => $nuevo, 'msg' => 'copia' ) );
}

function lp_accion_borrar( $slug, $id ) {
	$tipo = lp_tipo( $slug );
	if ( ! lp_puede_editar( $id, $slug ) || empty( $tipo['borrar'] ) || ! current_user_can( 'delete_post', $id ) ) {
		lp_ir( array( 'msg' => 'sinpermiso' ) );
	}
	wp_trash_post( $id );
	lp_ir( array( 'v' => 'lista', 't' => $slug, 'msg' => 'borrado' ) );
}

// ---------- Subir imágenes (una por vez, apenas se eligen) ----------

// Sin sesión: respuesta clara (si no, WordPress devuelve un «0» que el panel no entiende).
add_action( 'wp_ajax_nopriv_lp_subir', function () {
	wp_send_json( array( 'ok' => false, 'msg' => 'Tu sesión se cerró. Volvé a entrar y probá de nuevo.' ) );
} );

add_action( 'wp_ajax_lp_subir', function () {
	$error = function ( $msg ) {
		wp_send_json( array( 'ok' => false, 'msg' => $msg ) );
	};
	if ( ! lp_puede_usar() || ! current_user_can( 'upload_files' ) ) {
		$error( 'Tu sesión se cerró. Volvé a entrar y probá de nuevo.' );
	}
	if ( ! check_ajax_referer( 'lp_subir', 'nonce', false ) ) {
		$error( 'La página estaba abierta hace mucho. Recargala y probá de nuevo.' );
	}
	if ( ! lp_limite_ok() ) {
		$error( 'Subiste muchas imágenes seguidas. Esperá unos minutos.' );
	}
	if ( empty( $_FILES['archivo'] ) ) {
		$error( 'No llegó ninguna imagen.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tipos = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
	);
	$att = media_handle_upload( 'archivo', 0, array(), array( 'test_form' => false, 'mimes' => $tipos ) );
	if ( is_wp_error( $att ) ) {
		$error( 'No se pudo subir la imagen: ' . $att->get_error_message() );
	}
	update_post_meta( $att, '_lp_panel_foto', 1 );
	$u = wp_get_attachment_image_url( $att, 'medium_large' );
	wp_send_json( array( 'ok' => true, 'id' => $att, 'url' => $u ? $u : wp_get_attachment_url( $att ) ) );
} );

// ---------- Biblioteca de medios ----------

function lp_biblioteca_activa() {
	return ! empty( lp_config()['biblioteca'] ) && current_user_can( 'upload_files' );
}

// Imágenes que el usuario puede elegir: todas si puede editar lo de otros; si no, solo las suyas.
add_action( 'wp_ajax_lp_biblioteca', function () {
	if ( ! lp_puede_usar() || ! lp_biblioteca_activa() ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'No tenés acceso a la biblioteca.' ) );
	}
	if ( ! check_ajax_referer( 'lp_biblioteca', 'nonce', false ) ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'La página estaba abierta hace mucho. Recargala y probá de nuevo.' ) );
	}
	$pag  = max( 1, (int) ( $_GET['pag'] ?? 1 ) ); // phpcs:ignore WordPress.Security
	$q    = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security
	$args = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ),
		'posts_per_page' => 24,
		'paged'          => $pag,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
	);
	if ( '' !== $q ) {
		$args['s'] = $q;
	}
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		$args['author'] = get_current_user_id();
	}
	$query = new WP_Query( apply_filters( 'lp_biblioteca_args', $args ) );
	$out   = array();
	foreach ( $query->posts as $id ) {
		$mini = wp_get_attachment_image_url( $id, 'medium' );
		$prev = wp_get_attachment_image_url( $id, 'medium_large' );
		$out[] = array(
			'id'    => (int) $id,
			'mini'  => $mini ? $mini : wp_get_attachment_url( $id ),
			'url'   => $prev ? $prev : wp_get_attachment_url( $id ),
			'texto' => get_the_title( $id ),
		);
	}
	wp_send_json( array( 'ok' => true, 'items' => $out, 'mas' => $pag < (int) $query->max_num_pages ) );
} );

// ---------- Limpieza diaria: imágenes subidas desde el panel que nunca se usaron ----------

function lp_imagen_en_uso( $att ) {
	global $wpdb;
	$id = (int) $att;
	if ( (int) get_post_field( 'post_parent', $id ) ) {
		$padre = get_post( (int) get_post_field( 'post_parent', $id ) );
		if ( $padre && 'trash' !== $padre->post_status ) {
			return true; // adjunta a un contenido: ante la duda, se queda
		}
	}
	$meta = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id <> %d AND ( meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s )",
		$id,
		(string) $id,
		'%"' . $id . '"%',
		'%i:' . $id . ';%'
	) );
	if ( $meta ) {
		return true;
	}
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','attachment') AND post_content LIKE %s",
		'%wp-image-' . $id . '%'
	) );
}

add_action( 'lp_limpieza_fotos', function () {
	$ids = get_posts( array(
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'numberposts' => 100,
		'fields'      => 'ids',
		'post_parent' => 0,
		'meta_key'    => '_lp_panel_foto', // phpcs:ignore WordPress.DB
		'date_query'  => array( array( 'before' => '48 hours ago' ) ),
	) );
	foreach ( $ids as $id ) {
		if ( ! lp_imagen_en_uso( $id ) ) {
			wp_delete_attachment( $id, true );
		}
	}
} );

// Si el cron se perdió (por ejemplo, plugin actualizado a mano), se vuelve a programar.
add_action( 'admin_init', function () {
	if ( ! wp_next_scheduled( 'lp_limpieza_fotos' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lp_limpieza_fotos' );
	}
} );
