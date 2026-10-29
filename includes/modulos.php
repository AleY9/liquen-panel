<?php
/**
 * Registro de módulos: lo que usan los complementos (Liquen Calendarios y los que vengan)
 * para aparecer en el panel como una sección más, con el mismo acceso, colores y avisos.
 *
 * Cómo se registra un módulo (desde el complemento):
 *
 *   add_action( 'lp_registrar_modulos', function () {
 *       lp_registrar_modulo( 'calendarios', array(
 *           'titulo' => 'Disponibilidad',          // nombre en el inicio y en la barra del navegador
 *           'orden'  => 5,                         // lugar en el inicio, junto a los tipos (número más bajo, primero)
 *           'puede'  => 'edit_posts',              // capacidad de WordPress, o función que devuelve true/false
 *           'inicio' => 'lc_panel_inicio',         // opcional: HTML de la tarjeta en el inicio (debajo del título)
 *           'vista'  => 'lc_panel_vista',          // pantalla del módulo: recibe $v (subpantalla) y devuelve HTML,
 *                                                  // o array( 'titulo' => …, 'html' => … )
 *           'post'   => 'lc_panel_post',           // opcional: recibe $accion; el núcleo ya revisó sesión, permiso,
 *                                                  // nonce y límite de envíos. Redirige con lp_modulo_ir() o deja
 *                                                  // un aviso con lp_modulo_aviso().
 *           'css'    => array( 'https://…/panel-calendarios.css' ),  // opcional, solo en las pantallas del módulo
 *           'js'     => array( 'https://…/panel-calendarios.js' ),
 *       ) );
 *   } );
 *
 * Direcciones: lp_modulo_url( 'calendarios', array( 'v' => 'recurso', 'id' => 12 ) ) → /panel/?m=calendarios&v=recurso&id=12
 * Formularios: lp_modulo_campos( 'calendarios', 'bloquear' ) dentro del <form method="post">.
 * Estilos: las clases del panel (lp-card, lp-btn, lp-btn-sec, lp-aviso…) y los colores --lp-principal, --lp-acento, etc.
 *
 * El acceso general no cambia: solo entran administradores y los roles elegidos en Ajustes.
 * 'puede' restringe más dentro de eso, nunca amplía.
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['lp_modulos']        = null;
$GLOBALS['lp_modulo_actual']  = '';

/**
 * Registra un módulo. Usar dentro de la acción 'lp_registrar_modulos'.
 *
 * @return bool true si quedó registrado.
 */
function lp_registrar_modulo( $id, $args ) {
	$id = sanitize_key( $id );
	if ( '' === $id || ! is_array( $GLOBALS['lp_modulos'] ) || empty( $args['vista'] ) || ! is_callable( $args['vista'] ) ) {
		return false;
	}
	$GLOBALS['lp_modulos'][ $id ] = array(
		'id'     => $id,
		'titulo' => sanitize_text_field( (string) ( $args['titulo'] ?? $id ) ),
		'orden'  => max( 1, min( 99, (int) ( $args['orden'] ?? 50 ) ) ),
		'puede'  => $args['puede'] ?? 'read',
		'inicio' => isset( $args['inicio'] ) && is_callable( $args['inicio'] ) ? $args['inicio'] : null,
		'vista'  => $args['vista'],
		'post'   => isset( $args['post'] ) && is_callable( $args['post'] ) ? $args['post'] : null,
		'css'    => array_values( array_filter( array_map( 'esc_url_raw', (array) ( $args['css'] ?? array() ) ) ) ),
		'js'     => array_values( array_filter( array_map( 'esc_url_raw', (array) ( $args['js'] ?? array() ) ) ) ),
	);
	return true;
}

// Todos los módulos registrados (los complementos se registran la primera vez que se pide la lista).
function lp_modulos() {
	if ( null === $GLOBALS['lp_modulos'] ) {
		$GLOBALS['lp_modulos'] = array();
		do_action( 'lp_registrar_modulos' );
	}
	return $GLOBALS['lp_modulos'];
}

// ¿Puede el usuario actual usar este módulo? (Siempre dentro del acceso general al panel.)
function lp_modulo_permitido( $m ) {
	if ( ! lp_puede_usar() ) {
		return false;
	}
	$p = $m['puede'];
	if ( is_string( $p ) && ! is_callable( $p ) ) {
		return current_user_can( $p );
	}
	return is_callable( $p ) ? (bool) call_user_func( $p ) : false;
}

// Módulo por id, solo si el usuario actual puede usarlo.
function lp_modulo( $id ) {
	$todos = lp_modulos();
	$id    = sanitize_key( (string) $id );
	return isset( $todos[ $id ] ) && lp_modulo_permitido( $todos[ $id ] ) ? $todos[ $id ] : null;
}

// Módulos del usuario actual, en orden.
function lp_modulos_activos() {
	$out = array();
	foreach ( lp_modulos() as $id => $m ) {
		if ( lp_modulo_permitido( $m ) ) {
			$out[ $id ] = $m;
		}
	}
	uasort( $out, function ( $a, $b ) {
		return $a['orden'] <=> $b['orden'];
	} );
	return $out;
}

/* ---------- Ayudas para los complementos ---------- */

function lp_modulo_url( $id, $args = array() ) {
	return lp_url( array_merge( array( 'm' => sanitize_key( $id ) ), (array) $args ) );
}

// Redirige a una pantalla del módulo. Con $aviso, lo muestra una vez al llegar (verde si $ok).
function lp_modulo_ir( $id, $args = array(), $aviso = '', $ok = true ) {
	if ( '' !== $aviso ) {
		set_transient( 'lp_flash_' . get_current_user_id(), array( 'aviso' => (string) $aviso, 'ok' => (bool) $ok ), 5 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( lp_modulo_url( $id, $args ) );
	exit;
}

// Campos ocultos de un formulario del módulo (acción + nonce). Va dentro de <form method="post">.
function lp_modulo_campos( $id, $accion ) {
	$id     = sanitize_key( $id );
	$accion = sanitize_key( $accion );
	return '<input type="hidden" name="lp_m" value="' . esc_attr( $id ) . '">'
		. '<input type="hidden" name="lp_m_accion" value="' . esc_attr( $accion ) . '">'
		. '<input type="hidden" name="_lpnonce" value="' . esc_attr( wp_create_nonce( 'lp_m_' . $id . '_' . $accion ) ) . '">';
}

// Aviso para mostrar arriba de la pantalla del módulo (después de un envío que no redirigió).
function lp_modulo_aviso( $texto, $ok = false ) {
	$GLOBALS['lp_estado'] = array( 'aviso' => (string) $texto, 'ok' => (bool) $ok );
}

/* ---------- Núcleo: envíos y pantallas ---------- */

// Envío de un formulario de módulo. Devuelve true si era de un módulo (lo haya aceptado o no).
function lp_modulo_manejar_post() {
	if ( ! isset( $_POST['lp_m'] ) ) { // phpcs:ignore WordPress.Security
		return false;
	}
	$id     = sanitize_key( wp_unslash( $_POST['lp_m'] ) ); // phpcs:ignore WordPress.Security
	$accion = isset( $_POST['lp_m_accion'] ) ? sanitize_key( wp_unslash( $_POST['lp_m_accion'] ) ) : ''; // phpcs:ignore WordPress.Security
	$m      = lp_modulo( $id );
	if ( ! $m || ! $m['post'] || '' === $accion ) {
		lp_modulo_aviso( 'No tenés permiso para hacer eso.' );
		return true;
	}
	$nonce = isset( $_POST['_lpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_lpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security
	if ( ! wp_verify_nonce( $nonce, 'lp_m_' . $id . '_' . $accion ) ) {
		lp_modulo_aviso( 'La página estaba abierta hace mucho. Volvé a intentarlo.' );
		return true;
	}
	if ( ! lp_limite_ok() ) {
		lp_modulo_aviso( 'Hiciste muchos cambios seguidos. Esperá unos minutos y probá de nuevo.' );
		return true;
	}
	call_user_func( $m['post'], $accion );
	return true;
}

// Pantalla de un módulo. Devuelve false si el módulo no existe o no se puede usar (se muestra el inicio).
function lp_modulo_render( $id ) {
	$m = lp_modulo( $id );
	if ( ! $m ) {
		return false;
	}
	$v   = isset( $_GET['v'] ) ? sanitize_key( wp_unslash( $_GET['v'] ) ) : ''; // phpcs:ignore WordPress.Security
	$res = call_user_func( $m['vista'], $v );
	if ( is_array( $res ) ) {
		$titulo = (string) ( $res['titulo'] ?? $m['titulo'] );
		$html   = (string) ( $res['html'] ?? '' );
	} else {
		$titulo = $m['titulo'];
		$html   = (string) $res;
	}
	$GLOBALS['lp_modulo_actual'] = $m['id'];
	lp_render_documento( $titulo, lp_aviso_msg() . $html );
	return true;
}

// Tarjeta del módulo en el inicio del panel.
function lp_modulo_tarjeta( $m ) {
	$h  = '<section class="lp-card lp-tipo lp-modulo lp-modulo-' . esc_attr( $m['id'] ) . '">';
	$h .= '<h2>' . lp_e( $m['titulo'] ) . '</h2>';
	$cuerpo = $m['inicio'] ? (string) call_user_func( $m['inicio'] ) : '';
	$h .= '' !== $cuerpo ? $cuerpo : '<a class="lp-btn lp-btn-sec" href="' . esc_url( lp_modulo_url( $m['id'] ) ) . '">Abrir</a>';
	$h .= '</section>';
	return $h;
}

// Estilos y scripts del módulo que se está mostrando (los imprime lp_render_documento).
function lp_modulo_assets( $que ) {
	$id = $GLOBALS['lp_modulo_actual'];
	$m  = $id ? lp_modulo( $id ) : null;
	if ( ! $m ) {
		return '';
	}
	$h = '';
	foreach ( $m[ $que ] as $u ) {
		$h .= 'css' === $que
			? '<link rel="stylesheet" href="' . esc_url( $u ) . '">' . "\n"
			: '<script src="' . esc_url( $u ) . '"></script>' . "\n";
	}
	return $h;
}

/* ---------- Caja en Ajustes ---------- */

// Complementos enganchados al panel (solo informa; se activan o desactivan como cualquier plugin).
function lp_modulos_caja() {
	$todos = lp_modulos();
	if ( ! $todos ) {
		return;
	}
	uasort( $todos, function ( $a, $b ) {
		return $a['orden'] <=> $b['orden'];
	} );
	?>
	<div class="lp-box" id="lp-modulos">
		<h2>Complementos en el panel</h2>
		<p>Aparecen en el inicio del panel junto a los tipos de contenido, según su número de orden. Para sacarlos, desactivá el plugin del complemento.</p>
		<ul style="list-style:disc;margin-left:20px">
			<?php foreach ( $todos as $m ) : ?>
				<li><strong><?php echo esc_html( $m['titulo'] ); ?></strong> — orden <?php echo (int) $m['orden']; ?><?php echo is_string( $m['puede'] ) && 'read' !== $m['puede'] ? ' · solo con el permiso «' . esc_html( $m['puede'] ) . '»' : ''; ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}
