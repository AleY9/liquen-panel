<?php
/**
 * Ajustes → Liquen Panel (solo administradores).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_options_page( 'Liquen Panel', 'Liquen Panel', 'manage_options', 'liquen-panel', 'lp_admin_pagina' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( LP_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=liquen-panel' ) ) . '">Ajustes</a>' );
	return $links;
} );

add_action( 'admin_init', function () {
	register_setting( 'lp_ajustes_grupo', 'lp_ajustes', array( 'sanitize_callback' => 'lp_admin_limpiar', 'default' => array() ) );
} );

add_action( 'admin_notices', function () {
	if ( current_user_can( 'activate_plugins' ) && ! function_exists( 'acf_get_field_groups' ) ) {
		echo '<div class="notice notice-error"><p><strong>Liquen Panel</strong> necesita Advanced Custom Fields activo.</p></div>';
	}
	if ( current_user_can( 'manage_options' ) && lp_slug_ocupado() ) {
		echo '<div class="notice notice-warning"><p><strong>Liquen Panel está apagado.</strong> ' . esc_html( lp_slug_ocupado_txt() ) . ' No se toca: elegí otra dirección en <a href="' . esc_url( admin_url( 'options-general.php?page=liquen-panel' ) ) . '">Ajustes → Liquen Panel</a>.</p></div>';
	}
} );

function lp_admin_limpiar( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$def = lp_config_defecto();
	$out = array();

	$out['slug']       = sanitize_title( $in['slug'] ?? 'panel' );
	$out['slug']       = '' === $out['slug'] ? 'panel' : $out['slug'];
	delete_transient( 'lp_ocupado' );
	if ( lp_slug_ocupado( $out['slug'] ) ) {
		add_settings_error( 'lp_ajustes', 'lp_slug', lp_slug_ocupado_txt( $out['slug'] ) . ' El panel queda apagado hasta que elijas otra dirección (por ejemplo «gestion» o «mi-panel»).' );
	}
	$out['nombre']     = sanitize_text_field( $in['nombre'] ?? $def['nombre'] );
	$out['nombre_app'] = mb_substr( sanitize_text_field( $in['nombre_app'] ?? 'Panel' ), 0, 30 );
	$out['whatsapp']   = preg_replace( '/[^\d+ ]/', '', (string) ( $in['whatsapp'] ?? '' ) );
	$out['max_px']     = max( 800, min( 4000, (int) ( $in['max_px'] ?? 2048 ) ) );
	$out['biblioteca'] = empty( $in['biblioteca'] ) ? 0 : 1;
	$out['icono']      = absint( $in['icono'] ?? 0 );
	$out['fuente']     = preg_replace( '/[^a-zA-Z0-9 ,\'"\-]/', '', (string) ( $in['fuente'] ?? '' ) );
	$url               = esc_url_raw( (string) ( $in['fuente_url'] ?? '' ) );
	$out['fuente_url'] = preg_match( '#^https://fonts\.(googleapis|bunny)\.(com|net)/#', $url ) ? $url : '';

	$roles        = array_keys( wp_roles()->roles );
	$out['roles'] = array_values( array_intersect( (array) ( $in['roles'] ?? array() ), $roles ) );

	$out['colores'] = array();
	foreach ( $def['colores'] as $k => $v ) {
		$c                    = sanitize_hex_color( $in['colores'][ $k ] ?? '' );
		$out['colores'][ $k ] = $c ? $c : $v;
	}

	$out['tipos'] = array();
	foreach ( lp_tipos_disponibles() as $slug => $pto ) {
		$t      = isset( $in['tipos'][ $slug ] ) && is_array( $in['tipos'][ $slug ] ) ? $in['tipos'][ $slug ] : array();
		$campos = lp_campos_acf_de_tipo( $slug );
		$keys   = array_keys( $campos );
		$imgs   = array( 'foto' );
		$fechas = array();
		foreach ( $campos as $k => $f ) {
			if ( 'image' === $f['type'] ) {
				$imgs[] = $k;
			}
			if ( 'date_picker' === $f['type'] ) {
				$fechas[] = $k;
			}
		}
		$taxs = get_object_taxonomies( $slug );
		$out['tipos'][ $slug ] = array(
			'activo'       => empty( $t['activo'] ) ? 0 : 1,
			'singular'     => mb_strtolower( sanitize_text_field( $t['singular'] ?? '' ) ),
			'plural'       => mb_strtolower( sanitize_text_field( $t['plural'] ?? '' ) ),
			'genero'       => ( $t['genero'] ?? 'm' ) === 'f' ? 'f' : 'm',
			'crear'        => empty( $t['crear'] ) ? 0 : 1,
			'borrar'       => empty( $t['borrar'] ) ? 0 : 1,
			'programar'    => empty( $t['programar'] ) ? 0 : 1,
			'nucleo'       => array_values( array_intersect( array_merge( array( 'titulo' ), (array) ( $t['nucleo'] ?? array() ) ), array( 'titulo', 'contenido', 'foto', 'extracto' ) ) ),
			'taxonomias'   => array_values( array_intersect( (array) ( $t['taxonomias'] ?? array() ), $taxs ) ),
			'campos'       => array_values( array_intersect( (array) ( $t['campos'] ?? array() ), $keys ) ),
			'fecha_inicio' => in_array( $t['fecha_inicio'] ?? '', $fechas, true ) ? $t['fecha_inicio'] : '',
			'fecha_fin'    => in_array( $t['fecha_fin'] ?? '', $fechas, true ) ? $t['fecha_fin'] : '',
			'al_menos_uno' => array_values( array_intersect( (array) ( $t['al_menos_uno'] ?? array() ), $imgs ) ),
			'al_menos_msg' => sanitize_text_field( $t['al_menos_msg'] ?? '' ),
			'orden'        => max( 1, min( 99, (int) ( $t['orden'] ?? 10 ) ) ),
			'tax_unica'    => array_values( array_intersect( (array) ( $t['tax_unica'] ?? array() ), $taxs ) ),
		);
		$tt = &$out['tipos'][ $slug ];
		if ( '' === $tt['singular'] ) {
			$tt['singular'] = mb_strtolower( $pto->labels->singular_name );
		}
		if ( '' === $tt['plural'] ) {
			$tt['plural'] = mb_strtolower( $pto->labels->name );
		}
		if ( $tt['fecha_fin'] === $tt['fecha_inicio'] ) {
			$tt['fecha_fin'] = '';
		}
		// Las fechas que deciden «Próximas» tienen que estar en el formulario.
		foreach ( array( 'fecha_inicio', 'fecha_fin' ) as $fk ) {
			if ( $tt[ $fk ] && ! in_array( $tt[ $fk ], $tt['campos'], true ) ) {
				$tt['campos'][] = $tt[ $fk ];
			}
		}
		unset( $tt );
	}
	return $out;
}

// Colores de las paletas de Bricks, para copiar con un clic.
function lp_admin_colores_sitio() {
	$out = array();
	foreach ( (array) get_option( 'bricks_color_palette', array() ) as $pal ) {
		foreach ( (array) ( $pal['colors'] ?? array() ) as $c ) {
			$hex = sanitize_hex_color( $c['light'] ?? ( $c['hex'] ?? '' ) );
			if ( $hex ) {
				$out[ $hex ] = ( $pal['name'] ?? '' ) . ( ! empty( $c['raw'] ) ? ' · ' . $c['raw'] : '' );
			}
		}
	}
	return $out;
}

function lp_admin_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_enqueue_media();
	$c        = lp_config();
	$guardado = get_option( 'lp_ajustes', array() );
	$tipos_g  = isset( $guardado['tipos'] ) && is_array( $guardado['tipos'] ) ? $guardado['tipos'] : array();
	$n        = 'lp_ajustes';
	?>
	<div class="wrap lp-admin">
		<h1>Liquen Panel</h1>
		<?php if ( lp_slug_ocupado() ) : ?>
			<div class="notice notice-warning inline"><p><strong>El panel está apagado.</strong> <?php echo esc_html( lp_slug_ocupado_txt() ); ?> Cambiá la «Dirección del panel» acá abajo y guardá.</p></div>
		<?php else : ?>
			<p>Panel para cargar contenidos desde el celular: <a href="<?php echo esc_url( lp_url() ); ?>" target="_blank"><strong><?php echo esc_html( lp_url() ); ?></strong></a></p>
		<?php endif; ?>
		<style>
			.lp-admin .lp-box{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:4px 20px 16px;margin:16px 0;max-width:980px}
			.lp-admin .lp-tipo{border-top:1px solid #eee;padding-top:8px}
			.lp-admin .lp-cols{display:flex;flex-wrap:wrap;gap:6px 18px}
			.lp-admin .lp-cols label{min-width:240px}
			.lp-admin .lp-tax{display:flex;flex-direction:column;gap:2px}.lp-admin .lp-tax-unica{font-size:12px;color:#646970;margin-left:24px}.lp-admin .lp-sw{display:inline-block;width:26px;height:26px;border-radius:4px;border:1px solid #ccc;cursor:pointer;margin:2px;vertical-align:middle}
			.lp-admin .lp-off{color:#999}
			.lp-admin details>summary{cursor:pointer;font-weight:600;font-size:15px;padding:8px 0}
		</style>
		<form method="post" action="options.php">
			<?php settings_fields( 'lp_ajustes_grupo' ); ?>

			<div class="lp-box">
				<h2>General</h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="lp-slug">Dirección del panel</label></th><td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="lp-slug" type="text" name="<?php echo esc_attr( $n ); ?>[slug]" value="<?php echo esc_attr( $c['slug'] ); ?>" class="regular-text" style="width:160px"> <code>/</code><p class="description">Tiene que ser una dirección libre: si ya hay una página con ese nombre, el panel no la toma. Si usás LiteSpeed Cache: agregá <code>^/<?php echo esc_html( $c['slug'] ); ?></code> en Caché → Excluir → «URIs a no cachear» y purgá.</p></td></tr>
					<tr><th><label for="lp-nombre">Nombre de la app</label></th><td><input id="lp-nombre" type="text" name="<?php echo esc_attr( $n ); ?>[nombre]" value="<?php echo esc_attr( $c['nombre'] ); ?>" class="regular-text"><br><label>Nombre corto (debajo del ícono) <input type="text" name="<?php echo esc_attr( $n ); ?>[nombre_app]" value="<?php echo esc_attr( $c['nombre_app'] ); ?>" maxlength="30"></label></td></tr>
					<tr><th>Quiénes pueden usarlo</th><td>
						<?php foreach ( wp_roles()->roles as $rk => $r ) : ?>
							<label style="margin-right:14px"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[roles][]" value="<?php echo esc_attr( $rk ); ?>" <?php checked( in_array( $rk, $c['roles'], true ) || 'administrator' === $rk ); ?> <?php disabled( 'administrator' === $rk ); ?>> <?php echo esc_html( translate_user_role( $r['name'] ) ); ?></label>
						<?php endforeach; ?>
						<input type="hidden" name="<?php echo esc_attr( $n ); ?>[roles][]" value="administrator">
						<p class="description">Cada uno ve solo lo que WordPress le deja editar (un autor, lo suyo; un editor, todo).</p>
					</td></tr>
					<tr><th><label for="lp-wa">WhatsApp de ayuda</label></th><td><input id="lp-wa" type="text" name="<?php echo esc_attr( $n ); ?>[whatsapp]" value="<?php echo esc_attr( $c['whatsapp'] ); ?>" placeholder="59899123456"><p class="description">Opcional. Muestra un botón «¿Necesitás una mano?» en el inicio del panel.</p></td></tr>
					<tr><th><label for="lp-px">Tamaño de las imágenes</label></th><td><input id="lp-px" type="number" min="800" max="4000" step="1" name="<?php echo esc_attr( $n ); ?>[max_px]" value="<?php echo (int) $c['max_px']; ?>"> px de lado mayor <p class="description">Se achican en el celular antes de subir.</p></td></tr>
					<tr><th>Fotos</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[biblioteca]" value="1" <?php checked( ! empty( $c['biblioteca'] ) ); ?>> Permitir elegir fotos de la biblioteca de medios</label><p class="description">Si lo destildás, en el panel solo se pueden subir fotos nuevas. Cada usuario ve las imágenes que WordPress le deja usar (administradores y editores, todas; los demás, solo las suyas).</p></td></tr>
				</table>
			</div>

			<div class="lp-box">
				<h2>Apariencia</h2>
				<table class="form-table" role="presentation">
					<?php
					$nombres = array( 'principal' => 'Principal (barra y botones)', 'acento' => 'Acento (fechas, detalles)', 'fondo' => 'Fondo', 'texto' => 'Texto', 'error' => 'Errores' );
					foreach ( $nombres as $k => $lbl ) :
						?>
						<tr><th><label for="lp-col-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $lbl ); ?></label></th><td><input class="lp-color" id="lp-col-<?php echo esc_attr( $k ); ?>" type="color" name="<?php echo esc_attr( $n ); ?>[colores][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $c['colores'][ $k ] ); ?>"> <code><?php echo esc_html( $c['colores'][ $k ] ); ?></code></td></tr>
					<?php endforeach; ?>
					<tr><th></th><td><button type="button" class="button" id="lp-colores-fabrica" data-colores="<?php echo esc_attr( wp_json_encode( lp_config_defecto()['colores'] ) ); ?>">Volver a los colores de fábrica</button><p class="description">Después tocá «Guardar ajustes».</p></td></tr>
					<?php $sitio = lp_admin_colores_sitio(); if ( $sitio ) : ?>
						<tr><th>Colores del sitio</th><td>
							<?php foreach ( $sitio as $hex => $tit ) : ?><span class="lp-sw" style="background:<?php echo esc_attr( $hex ); ?>" title="<?php echo esc_attr( $hex . ' — ' . $tit ); ?>" data-hex="<?php echo esc_attr( $hex ); ?>"></span><?php endforeach; ?>
							<p class="description">Tocá un color de arriba (Principal, Acento…) y después uno de estos para copiarlo.</p>
						</td></tr>
					<?php endif; ?>
					<tr><th><label for="lp-fuente">Tipografía</label></th><td><input id="lp-fuente" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[fuente]" value="<?php echo esc_attr( $c['fuente'] ); ?>" placeholder="'Source Sans 3'"><br><input type="url" class="large-text" name="<?php echo esc_attr( $n ); ?>[fuente_url]" value="<?php echo esc_attr( $c['fuente_url'] ); ?>" placeholder="https://fonts.googleapis.com/css2?family=…"><p class="description">Opcional. Vacío = la del sistema del celular (la más liviana).</p></td></tr>
					<tr><th>Ícono de la app</th><td>
						<input type="hidden" id="lp-icono" name="<?php echo esc_attr( $n ); ?>[icono]" value="<?php echo (int) $c['icono']; ?>">
						<span id="lp-icono-prev"><?php echo $c['icono'] ? wp_get_attachment_image( (int) $c['icono'], array( 64, 64 ) ) : ''; ?></span>
						<button type="button" class="button" id="lp-icono-btn">Elegir imagen</button> <button type="button" class="button-link" id="lp-icono-quitar">Quitar</button>
						<p class="description">Cuadrada, de 512 × 512 px. Si no hay, se usa el ícono del sitio (Ajustes → Generales).</p>
					</td></tr>
				</table>
			</div>

			<div class="lp-box">
				<h2>Qué se carga desde el panel</h2>
				<p>Los campos salen de ACF. Para cambiar un campo (nombre, ayuda, opciones), editalo en ACF: el panel lo toma solo.</p>
				<?php
				$lp_pos = 0;
				foreach ( lp_tipos_disponibles() as $slug => $pto ) :
					$lp_pos++;
					$t      = isset( $tipos_g[ $slug ] ) ? array_merge( lp_tipo_defecto( $slug ), $tipos_g[ $slug ] ) : ( isset( $c['tipos'][ $slug ] ) ? $c['tipos'][ $slug ] : lp_tipo_defecto( $slug ) );
					$pre    = $n . '[tipos][' . $slug . ']';
					$campos = lp_campos_acf_de_tipo( $slug );
					$fechas = array_filter( $campos, function ( $f ) {
						return 'date_picker' === $f['type'];
					} );
					$imgs   = array_filter( $campos, function ( $f ) {
						return 'image' === $f['type'];
					} );
					?>
					<details class="lp-tipo" <?php echo ! empty( $t['activo'] ) ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $pto->labels->name ); ?> <code><?php echo esc_html( $slug ); ?></code> <?php echo ! empty( $t['activo'] ) ? '— <span style="color:#008a20">activo</span>' : '— <span class="lp-off">no se usa</span>'; ?></summary>
						<p><label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[activo]" value="1" <?php checked( ! empty( $t['activo'] ) ); ?>> <strong>Usar en el panel</strong></label></p>
						<p>
							Se llama: <input type="text" name="<?php echo esc_attr( $pre ); ?>[singular]" value="<?php echo esc_attr( $t['singular'] ); ?>" placeholder="actividad" style="width:150px"> /
							<input type="text" name="<?php echo esc_attr( $pre ); ?>[plural]" value="<?php echo esc_attr( $t['plural'] ); ?>" placeholder="actividades" style="width:150px">
							<select name="<?php echo esc_attr( $pre ); ?>[genero]"><option value="f" <?php selected( $t['genero'], 'f' ); ?>>femenino (Nueva, Publicada)</option><option value="m" <?php selected( $t['genero'], 'm' ); ?>>masculino (Nuevo, Publicado)</option></select>
						</p>
						<p class="lp-cols">
							<label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[crear]" value="1" <?php checked( ! empty( $t['crear'] ) ); ?>> Se pueden crear nuevos</label>
							<label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[borrar]" value="1" <?php checked( ! empty( $t['borrar'] ) ); ?>> Se pueden borrar (a la papelera)</label>
							<label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[programar]" value="1" <?php checked( ! empty( $t['programar'] ) ); ?>> Se puede programar la publicación</label>
						</p>
						<p><label>Orden en el inicio del panel <input type="number" min="1" max="99" name="<?php echo esc_attr( $pre ); ?>[orden]" value="<?php echo (int) ( ! empty( $t['orden'] ) && 99 !== (int) $t['orden'] ? $t['orden'] : $lp_pos ); ?>" style="width:70px"></label> <span class="description">— el número más bajo va primero</span></p>
						<p><strong>Campos de WordPress</strong></p>
						<p class="lp-cols">
							<label><input type="checkbox" checked disabled> Título</label>
							<?php foreach ( array( 'foto' => 'Foto destacada', 'contenido' => 'Texto', 'extracto' => 'Resumen (extracto)' ) as $k => $lbl ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[nucleo][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $t['nucleo'], true ) ); ?>> <?php echo esc_html( $lbl ); ?></label>
							<?php endforeach; ?>
						</p>
						<?php $taxs = get_object_taxonomies( $slug, 'objects' ); if ( $taxs ) : ?>
							<p><strong>Taxonomías</strong> <span class="description">(casillas para marcar)</span></p>
							<p class="lp-cols">
								<?php foreach ( $taxs as $tx ) : if ( 'post_format' === $tx->name || ! $tx->show_ui ) { continue; } ?>
									<span class="lp-tax"><label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[taxonomias][]" value="<?php echo esc_attr( $tx->name ); ?>" <?php checked( in_array( $tx->name, (array) $t['taxonomias'], true ) ); ?>> <?php echo esc_html( $tx->labels->name ); ?></label>
									<label class="lp-tax-unica"><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[tax_unica][]" value="<?php echo esc_attr( $tx->name ); ?>" <?php checked( in_array( $tx->name, (array) ( $t['tax_unica'] ?? array() ), true ) ); ?>> se elige una sola</label></span>
								<?php endforeach; ?>
							</p>
						<?php endif; ?>
						<p><strong>Campos de ACF</strong></p>
						<?php if ( ! $campos ) : ?>
							<p class="lp-off">Este tipo no tiene grupos de campos de ACF.</p>
						<?php else : ?>
							<p class="lp-cols">
								<?php foreach ( $campos as $k => $f ) : $ok = lp_campo_soportado( $f ); ?>
									<label class="<?php echo $ok ? '' : 'lp-off'; ?>"><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[campos][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $ok && in_array( $k, (array) $t['campos'], true ) ); ?> <?php disabled( ! $ok ); ?>> <?php echo esc_html( $f['label'] ); ?> <small>(<?php echo esc_html( lp_es_galeria( $f ) ? 'galería' : $f['type'] ); ?><?php echo $ok ? '' : ', no soportado'; ?>)</small></label>
								<?php endforeach; ?>
							</p>
						<?php endif; ?>
						<?php if ( $fechas ) : ?>
							<p><strong>Fechas</strong> <span class="description">— ordenan la lista en Próximas, Sin fecha y Pasadas; las copias salen sin fecha</span></p>
							<p>
								Inicio <select name="<?php echo esc_attr( $pre ); ?>[fecha_inicio]"><option value="">— no usar —</option><?php foreach ( $fechas as $k => $f ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $t['fecha_inicio'], $k ); ?>><?php echo esc_html( $f['label'] ); ?></option><?php endforeach; ?></select>
								Fin <select name="<?php echo esc_attr( $pre ); ?>[fecha_fin]"><option value="">— no usar —</option><?php foreach ( $fechas as $k => $f ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $t['fecha_fin'], $k ); ?>><?php echo esc_html( $f['label'] ); ?></option><?php endforeach; ?></select>
							</p>
						<?php endif; ?>
						<?php if ( $imgs || post_type_supports( $slug, 'thumbnail' ) ) : ?>
							<p><strong>Imagen obligatoria</strong> <span class="description">— para publicar hace falta al menos una de las marcadas</span></p>
							<p class="lp-cols">
								<?php if ( post_type_supports( $slug, 'thumbnail' ) ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[al_menos_uno][]" value="foto" <?php checked( in_array( 'foto', (array) $t['al_menos_uno'], true ) ); ?>> Foto destacada</label><?php endif; ?>
								<?php foreach ( $imgs as $k => $f ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $pre ); ?>[al_menos_uno][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $t['al_menos_uno'], true ) ); ?>> <?php echo esc_html( $f['label'] ); ?></label><?php endforeach; ?>
							</p>
							<p><label>Mensaje <input type="text" class="large-text" name="<?php echo esc_attr( $pre ); ?>[al_menos_msg]" value="<?php echo esc_attr( $t['al_menos_msg'] ); ?>" placeholder="Subí una foto o el afiche (o los dos)."></label></p>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>
			</div>

			<?php submit_button( 'Guardar ajustes' ); ?>
		</form>
		<?php lp_kit_caja(); ?>
	</div>
	<script>
	(function(){
		var ultimo=null;
		document.querySelectorAll('.lp-color').forEach(function(i){i.addEventListener('focus',function(){ultimo=i;});i.addEventListener('click',function(){ultimo=i;});i.addEventListener('input',function(){i.nextElementSibling.textContent=i.value;});});
		document.querySelectorAll('.lp-sw').forEach(function(s){s.addEventListener('click',function(){if(!ultimo){alert('Primero tocá el color que querés cambiar (Principal, Acento…).');return;}ultimo.value=s.dataset.hex;ultimo.nextElementSibling.textContent=s.dataset.hex;});});
		var fab=document.getElementById('lp-colores-fabrica');
		fab&&fab.addEventListener('click',function(){var d=JSON.parse(fab.dataset.colores);Object.keys(d).forEach(function(k){var i=document.getElementById('lp-col-'+k);if(i){i.value=d[k];i.nextElementSibling.textContent=d[k];}});});
		var b=document.getElementById('lp-icono-btn'),q=document.getElementById('lp-icono-quitar'),inp=document.getElementById('lp-icono'),prev=document.getElementById('lp-icono-prev'),fr;
		b&&b.addEventListener('click',function(){if(!window.wp||!wp.media){return;}fr=fr||wp.media({title:'Ícono de la app (512 × 512)',library:{type:'image'},multiple:false});fr.off('select').on('select',function(){var a=fr.state().get('selection').first().toJSON();inp.value=a.id;prev.innerHTML='<img src="'+(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url)+'" width="64" height="64" alt="">';});fr.open();});
		q&&q.addEventListener('click',function(){inp.value='0';prev.innerHTML='';});
	})();
	</script>
	<?php
}
