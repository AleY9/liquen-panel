<?php
/**
 * Pestañas informativas de Ajustes → Liquen Panel:
 * - Recuadro «Qué hace / Qué no hace» (arriba de todas las pestañas).
 * - Primeros pasos: qué configurar y cómo instalar el panel como app en el celular.
 * - Diagnóstico: lista de chequeo (bien / para revisar / para arreglar), mismo formato que Liquen Calendarios.
 */

defined( 'ABSPATH' ) || exit;

function lp_admin_url( $tab = '' ) {
	return admin_url( 'options-general.php?page=liquen-panel' . ( $tab ? '&tab=' . $tab : '' ) );
}

/* ---------- Qué hace / Qué no hace ---------- */

function lp_que_hace() {
	?>
	<div class="notice notice-info inline" style="margin:12px 0">
		<p><strong>Qué hace:</strong> un panel en <code>/<?php echo esc_html( lp_config()['slug'] ); ?>/</code> para cargar y editar desde el celular los contenidos del sitio (actividades, alojamientos, entradas…) con los campos de ACF que se eligen acá. Achica las fotos antes de subirlas, ordena por fecha (próximas, sin fecha, pasadas) y se instala como app en el celular. Los complementos (como Liquen Calendarios) suman secciones al panel.</p>
		<p><strong>Qué no hace:</strong> no cambia el diseño de la web (eso es Bricks) ni reemplaza a wp-admin para lo de administrador (plugins, usuarios, ajustes). No cobra ni toma reservas.</p>
		<p><strong>Tipos de contenido y campos:</strong> se arman en ACF. En un sitio nuevo, el Kit de inicio («Primeros pasos») los crea en ACF de una vez.</p>
		<p>Otras secciones e integraciones (reservas, pagos, plataformas externas) se pueden desarrollar a pedido.</p>
	</div>
	<?php
}

/* ---------- Primeros pasos ---------- */

function lp_tab_primeros() {
	$url = lp_url();
	?>
	<div class="lp-box">
		<h2>Configurar el panel</h2>
		<ol class="lp-pasos">
			<li><strong><a href="<?php echo esc_url( lp_admin_url( 'contenidos' ) ); ?>" data-ir="contenidos">Contenidos</a>:</strong> marcá qué tipos se cargan desde el panel, cómo se llaman y qué campos de ACF muestran. Si el sitio es nuevo y todavía no tiene los tipos, usá el Kit de inicio (abajo).</li>
			<li><strong><a href="<?php echo esc_url( lp_admin_url( 'apariencia' ) ); ?>" data-ir="apariencia">Apariencia</a>:</strong> colores del sitio, nombre corto (el que aparece debajo del ícono, ej. «Tao Panel») e ícono cuadrado de 512 × 512 px.</li>
			<li><strong><a href="<?php echo esc_url( lp_admin_url( 'general' ) ); ?>" data-ir="general">General</a>:</strong> quiénes pueden usarlo y el WhatsApp de ayuda.</li>
			<li><strong>Si el sitio usa LiteSpeed Cache:</strong> en LiteSpeed Cache → Caché → Excluir → «URIs a no cachear», agregá <code>^/<?php echo esc_html( lp_config()['slug'] ); ?></code> y purgá todo.</li>
			<li><strong><a href="<?php echo esc_url( lp_admin_url( 'diagnostico' ) ); ?>" data-ir="diagnostico">Diagnóstico</a>:</strong> que no quede nada para arreglar.</li>
			<li><strong>Probarlo:</strong> abrí <a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a>, cargá algo de prueba y borralo.</li>
		</ol>
	</div>

	<div class="lp-box">
		<h2>Instalar el panel como app en el celular</h2>
		<p>Queda un ícono en la pantalla del celular que abre el panel a pantalla completa, sin la barra del navegador. <strong>Hacelo en el sitio publicado</strong> (no en un staging): el ícono abre la dirección desde donde se instaló.</p>
		<h3>Android (Chrome)</h3>
		<ol class="lp-pasos">
			<li>Entrá a <code><?php echo esc_html( $url ); ?></code> e iniciá sesión con «Recuérdame» marcado.</li>
			<li>Menú <strong>⋮</strong> (arriba a la derecha) → <strong>«Instalar y crear acceso directo»</strong>. En otros celulares se llama «Agregar a la pantalla principal» o «Instalar app».</li>
			<li>Si pregunta, elegí <strong>«Instalar»</strong> (no «Crear acceso directo»).</li>
			<li>El ícono aparece en la pantalla de inicio o en el cajón de apps.</li>
		</ol>
		<h3>iPhone (Safari)</h3>
		<ol class="lp-pasos">
			<li>Entrá a <code><?php echo esc_html( $url ); ?></code> <strong>con Safari</strong> e iniciá sesión.</li>
			<li>Botón <strong>Compartir</strong> (cuadrado con flecha) → <strong>«Agregar a inicio»</strong> → «Agregar».</li>
		</ol>
		<p><strong>Revisar:</strong> que se vea el ícono del panel, el nombre corto debajo y que abra sin barra de dirección. Si se ve el ícono del sitio, falta elegir el ícono en Apariencia.</p>
		<p class="description">Con letra grande en el celular (accesibilidad) el panel se tiene que seguir leyendo bien: probalo así si el cliente la usa.</p>
	</div>
	<?php
}

/* ---------- Diagnóstico ---------- */

// Exclusiones de LiteSpeed Cache (se guardan como lista o como texto, según la versión).
function lp_litespeed_excluidas() {
	$v = get_option( 'litespeed.conf.cache-exc', array() );
	if ( is_string( $v ) ) {
		$j = json_decode( $v, true );
		$v = is_array( $j ) ? $j : preg_split( '/\R/', $v );
	}
	return array_filter( array_map( 'trim', (array) $v ) );
}

/**
 * Lista de chequeo.
 *
 * @return array [ ['grupo', 'estado' => ok|ojo|mal, 'texto', 'enlace' => url|null], ... ]
 */
function lp_diagnostico() {
	$items = array();
	$add   = function ( $grupo, $estado, $texto, $enlace = null ) use ( &$items ) {
		$items[] = compact( 'grupo', 'estado', 'texto', 'enlace' );
	};
	$c = lp_config();
	$G = 'General';

	// ACF.
	if ( function_exists( 'acf_get_field_groups' ) ) {
		$add( $G, 'ok', 'Advanced Custom Fields activo.' );
	} else {
		$add( $G, 'mal', 'Falta Advanced Custom Fields: sin ACF el panel no tiene campos para mostrar.', admin_url( 'plugins.php' ) );
	}

	// Dirección.
	if ( lp_slug_ocupado() ) {
		$add( $G, 'mal', 'El panel está apagado: ' . lp_slug_ocupado_txt(), lp_admin_url( 'general' ) );
	} else {
		$add( $G, 'ok', 'Dirección del panel: ' . lp_url() );
	}

	// Enlaces permanentes (sin ellos /panel/ no existe).
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		$add( $G, 'mal', 'Los enlaces permanentes están en «Simple»: la dirección del panel no funciona. Elegí «Nombre de la entrada» en Ajustes → Enlaces permanentes.', admin_url( 'options-permalink.php' ) );
	}

	// Quiénes lo usan.
	$nombres = array();
	foreach ( array_unique( array_merge( array( 'administrator' ), $c['roles'] ) ) as $r ) {
		$obj       = get_role( $r );
		$nombres[] = $obj ? translate_user_role( wp_roles()->roles[ $r ]['name'] ?? $r ) : $r;
	}
	$add( $G, 'ok', 'Lo pueden usar: ' . implode( ', ', $nombres ) . '.' );

	// Actualizaciones automáticas.
	$auto = (array) get_site_option( 'auto_update_plugins', array() );
	if ( in_array( plugin_basename( LP_FILE ), $auto, true ) ) {
		$add( $G, 'ok', 'Actualizaciones automáticas activadas.' );
	} else {
		$add( $G, 'ojo', 'Las actualizaciones automáticas están apagadas: las versiones nuevas hay que instalarlas a mano. Activalas en Plugins → «Activar las actualizaciones automáticas».', admin_url( 'plugins.php' ) );
	}

	// Limpieza diaria de fotos que quedaron sin usar.
	if ( wp_next_scheduled( 'lp_limpieza_fotos' ) ) {
		$add( $G, 'ok', 'Limpieza diaria de fotos sin usar programada.' );
	} else {
		$add( $G, 'ojo', 'La limpieza diaria de fotos sin usar no está programada. Desactivá y activá el plugin para que se programe.' );
	}

	// PHP.
	if ( version_compare( PHP_VERSION, '8.1', '>=' ) ) {
		$add( $G, 'ok', 'PHP ' . PHP_VERSION . '.' );
	} else {
		$add( $G, 'ojo', 'PHP ' . PHP_VERSION . ': funciona, pero conviene pasar a 8.3 (las versiones viejas dejan de recibir arreglos de seguridad).' );
	}

	// LiteSpeed Cache.
	$C = 'Caché';
	if ( defined( 'LSCWP_V' ) ) {
		$patron = '^/' . $c['slug'];
		$ok     = false;
		foreach ( lp_litespeed_excluidas() as $e ) {
			if ( 0 === strpos( $e, $patron ) || 0 === strpos( $e, '/' . $c['slug'] ) ) {
				$ok = true;
				break;
			}
		}
		if ( $ok ) {
			$add( $C, 'ok', 'LiteSpeed Cache activo y el panel está excluido de la caché.' );
		} else {
			$add( $C, 'mal', 'LiteSpeed Cache está activo y no excluye el panel: se pueden ver datos viejos después de guardar. En LiteSpeed Cache → Caché → Excluir → «URIs a no cachear», agregá ' . $patron . ' y purgá todo.', admin_url( 'admin.php?page=litespeed-cache' ) );
		}
	} else {
		$add( $C, 'ok', 'LiteSpeed Cache no está activo. Si se activa, hay que excluir ^/' . $c['slug'] . '.' );
	}

	// Contenidos.
	$T      = 'Contenidos';
	$activos = lp_tipos_activos();
	if ( $activos ) {
		$add( $T, 'ok', 'En el panel: ' . implode( ', ', array_map( function ( $t ) {
			return $t['plural'];
		}, $activos ) ) . '.' );
	} else {
		$add( $T, 'mal', 'No hay ningún tipo de contenido para cargar desde el panel.', lp_admin_url( 'contenidos' ) );
	}
	if ( empty( get_option( 'lp_ajustes', array() )['tipos'] ) ) {
		$add( $T, 'ojo', 'Los ajustes todavía no se guardaron nunca: el panel muestra solo las entradas, con todos sus campos.', lp_admin_url( 'contenidos' ) );
	}
	foreach ( lp_campos_nuevos() as $slug => $campos ) {
		$pto = get_post_type_object( $slug );
		$add( $T, 'ojo', 'Campos nuevos de ACF en ' . ( $pto ? $pto->labels->name : $slug ) . ' que no están en el panel: ' . implode( ', ', $campos ) . '. Si van, tildalos; si no, guardá igual.', lp_admin_url( 'contenidos' ) );
	}
	foreach ( lp_modulos() as $m ) {
		$add( $T, 'ok', 'Complemento en el panel: ' . $m['titulo'] . '.' );
	}

	// Apariencia.
	$A = 'Apariencia';
	if ( $c['colores'] === lp_config_defecto()['colores'] ) {
		$add( $A, 'ojo', 'El panel tiene los colores de fábrica: elegí los del sitio.', lp_admin_url( 'apariencia' ) );
	} else {
		$add( $A, 'ok', 'Colores propios del sitio.' );
	}
	$icono = (int) $c['icono'];
	if ( $icono && wp_attachment_is_image( $icono ) ) {
		$meta = wp_get_attachment_metadata( $icono );
		$w    = (int) ( $meta['width'] ?? 0 );
		$h    = (int) ( $meta['height'] ?? 0 );
		if ( $w < 512 || $h < 512 ) {
			$add( $A, 'ojo', 'El ícono de la app es de ' . $w . ' × ' . $h . ' px: en el celular se puede ver borroso. Mejor uno de 512 × 512.', lp_admin_url( 'apariencia' ) );
		} elseif ( $w !== $h ) {
			$add( $A, 'ojo', 'El ícono de la app no es cuadrado (' . $w . ' × ' . $h . ' px): el celular lo recorta.', lp_admin_url( 'apariencia' ) );
		} else {
			$add( $A, 'ok', 'Ícono de la app elegido (' . $w . ' × ' . $h . ' px).' );
		}
	} elseif ( get_site_icon_url( 512 ) ) {
		$add( $A, 'ojo', 'Sin ícono propio: la app usa el ícono del sitio.', lp_admin_url( 'apariencia' ) );
	} else {
		$add( $A, 'mal', 'No hay ícono: al instalar el panel en el celular queda un ícono genérico.', lp_admin_url( 'apariencia' ) );
	}
	if ( 'Panel' === $c['nombre_app'] ) {
		$add( $A, 'ojo', 'El nombre corto es «Panel»: si el cliente tiene más de un panel instalado, conviene uno propio (ej. «Tao Panel»).', lp_admin_url( 'apariencia' ) );
	} else {
		$add( $A, 'ok', 'Nombre corto: «' . $c['nombre_app'] . '».' );
	}
	if ( '' === $c['whatsapp'] ) {
		$add( $A, 'ojo', 'Sin WhatsApp de ayuda: el panel no muestra el botón «¿Necesitás una mano?». Es opcional.', lp_admin_url( 'general' ) );
	} else {
		$add( $A, 'ok', 'WhatsApp de ayuda: ' . $c['whatsapp'] . '.' );
	}

	return $items;
}

function lp_tab_diagnostico() {
	$items  = lp_diagnostico();
	$cuenta = array_count_values( array_column( $items, 'estado' ) );
	$iconos = array( 'ok' => '✓', 'ojo' => '!', 'mal' => '✕' );
	?>
	<div class="lp-resumen">
		<span style="color:#00a32a">✓ <?php echo (int) ( $cuenta['ok'] ?? 0 ); ?> bien</span>
		<span style="color:#996800">! <?php echo (int) ( $cuenta['ojo'] ?? 0 ); ?> para revisar</span>
		<span style="color:#d63638">✕ <?php echo (int) ( $cuenta['mal'] ?? 0 ); ?> para arreglar</span>
	</div>
	<?php
	$grupos = array();
	foreach ( $items as $it ) {
		$grupos[ $it['grupo'] ][] = $it;
	}
	foreach ( $grupos as $g => $lista ) :
		usort( $lista, function ( $a, $b ) {
			$o = array( 'mal' => 0, 'ojo' => 1, 'ok' => 2 );
			return $o[ $a['estado'] ] <=> $o[ $b['estado'] ];
		} );
		?>
		<div class="lp-grupo">
			<h3><?php echo esc_html( $g ); ?></h3>
			<?php foreach ( $lista as $it ) : ?>
				<div class="lp-item lp-<?php echo esc_attr( $it['estado'] ); ?>">
					<span class="lp-ico"><?php echo esc_html( $iconos[ $it['estado'] ] ); ?></span>
					<p>
						<?php echo esc_html( $it['texto'] ); ?>
						<?php if ( $it['enlace'] && 'ok' !== $it['estado'] ) : ?>
							<a href="<?php echo esc_url( $it['enlace'] ); ?>"<?php echo preg_match( '/[?&]tab=([a-z]+)/', $it['enlace'], $m ) && false !== strpos( $it['enlace'], 'page=liquen-panel' ) ? ' data-ir="' . esc_attr( $m[1] ) . '"' : ''; ?>>Ir →</a>
						<?php endif; ?>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
	<?php
}
