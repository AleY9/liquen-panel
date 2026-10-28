<?php
/**
 * Campos del formulario: cuáles se muestran, cómo se leen, se validan y se guardan.
 *
 * Cada campo del formulario es un "item":
 *   id     → 'titulo', 'contenido', 'extracto', 'foto', 'tax:<taxonomía>' o la key de ACF (field_…)
 *   tipo   → titulo | contenido | extracto | foto | tax | galeria | tipo de ACF (text, date_picker…)
 *   label, ayuda, requerido, acf (definición del campo ACF si corresponde)
 *
 * Los valores van normalizados (mismo formato al leer de la base y al recibir del formulario):
 *   textos → string · imagen → int · galería → int[] · tax → int[] · checkbox → string[]
 *   true_false → '1'|'0' · fecha → 'Y-m-d' · hora → 'H:i'
 */

defined( 'ABSPATH' ) || exit;

// ---------- Definición ----------

function lp_items( $slug ) {
	$tipo = lp_tipo( $slug );
	if ( ! $tipo ) {
		return array();
	}
	$nucleo = (array) $tipo['nucleo'];
	$acf    = lp_campos_acf_de_tipo( $slug );
	$elegidos = array();
	foreach ( (array) $tipo['campos'] as $k ) {
		if ( isset( $acf[ $k ] ) && lp_campo_soportado( $acf[ $k ] ) ) {
			$elegidos[ $k ] = $acf[ $k ];
		}
	}
	$item_acf = function ( $f ) {
		$t = lp_es_galeria( $f ) ? 'galeria' : $f['type'];
		return array(
			'id'        => $f['key'],
			'tipo'      => $t,
			'label'     => $f['label'],
			'ayuda'     => isset( $f['instructions'] ) ? wp_strip_all_tags( $f['instructions'] ) : '',
			'requerido' => ! empty( $f['required'] ),
			'acf'       => $f,
		);
	};

	$items = array();
	$items[] = array( 'id' => 'titulo', 'tipo' => 'titulo', 'label' => 'Título', 'ayuda' => '', 'requerido' => true );

	// Fechas primero: deciden si aparece en «Próximas».
	foreach ( array( 'fecha_inicio', 'fecha_fin' ) as $fk ) {
		$k = $tipo[ $fk ];
		if ( $k && isset( $elegidos[ $k ] ) ) {
			$items[] = $item_acf( $elegidos[ $k ] );
			unset( $elegidos[ $k ] );
		}
	}
	// Imágenes.
	if ( in_array( 'foto', $nucleo, true ) ) {
		$items[] = array( 'id' => 'foto', 'tipo' => 'foto', 'label' => 'Foto destacada', 'ayuda' => '', 'requerido' => false );
	}
	foreach ( $elegidos as $k => $f ) {
		if ( 'image' === $f['type'] || lp_es_galeria( $f ) ) {
			$items[] = $item_acf( $f );
			unset( $elegidos[ $k ] );
		}
	}
	if ( in_array( 'contenido', $nucleo, true ) ) {
		$items[] = array( 'id' => 'contenido', 'tipo' => 'contenido', 'label' => 'Texto', 'ayuda' => '', 'requerido' => false );
	}
	if ( in_array( 'extracto', $nucleo, true ) ) {
		$items[] = array( 'id' => 'extracto', 'tipo' => 'extracto', 'label' => 'Resumen', 'ayuda' => 'Una o dos frases para las tarjetas.', 'requerido' => false );
	}
	foreach ( (array) $tipo['taxonomias'] as $tx ) {
		$t = get_taxonomy( $tx );
		if ( $t && is_object_in_taxonomy( $slug, $tx ) ) {
			$ayuda = '';
			if ( 'category' === $tx ) {
				$def   = get_term( (int) get_option( 'default_category' ), 'category' );
				$ayuda = ( $def && ! is_wp_error( $def ) ) ? 'Podés marcar varias. Si no marcás ninguna, queda en «' . $def->name . '».' : 'Podés marcar varias.';
			}
			$unica = in_array( $tx, (array) ( $tipo['tax_unica'] ?? array() ), true );
			if ( $unica && 'category' === $tx ) {
				$ayuda = str_replace( 'Podés marcar varias. ', '', $ayuda );
				$ayuda = str_replace( 'Si no marcás ninguna', 'Si no elegís ninguna', $ayuda );
				$ayuda = 'Podés marcar varias.' === $ayuda ? '' : $ayuda;
			}
			$items[] = array( 'id' => 'tax:' . $tx, 'tipo' => 'tax', 'label' => $t->labels->name, 'ayuda' => $ayuda, 'requerido' => false, 'tax' => $tx, 'unica' => $unica );
		}
	}
	foreach ( $elegidos as $f ) {
		$items[] = $item_acf( $f );
	}
	return apply_filters( 'lp_items', $items, $slug );
}

// ---------- Texto del contenido ----------

// Contenido de WordPress → texto plano con párrafos separados por línea en blanco.
function lp_html_a_texto( $html ) {
	$html = preg_replace( '/<!--.*?-->/s', '', (string) $html );
	$html = preg_replace( '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>\s*\1\s*</a>#i', '$1', $html );
	$html = preg_replace( '#</p>\s*#i', "\n\n", $html );
	$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
	return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
}

// ¿Se puede editar como texto sin perder formato? (solo párrafos, saltos y enlaces sueltos)
function lp_texto_editable( $html ) {
	$html = preg_replace( '/<!--\s*\/?wp:paragraph[^>]*-->/', '', (string) $html );
	if ( preg_match( '/<!--\s*wp:/', $html ) ) {
		return false; // otros bloques (imágenes, columnas, listas…)
	}
	$html = preg_replace( '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>\s*\1\s*</a>#i', '$1', $html );
	$sin  = preg_replace( '#</?(p|br)(\s[^>]*)?/?>#i', '', $html );
	return ! preg_match( '/<[a-z]/i', $sin );
}

// Texto plano → párrafos de bloques; las direcciones web quedan como enlaces.
function lp_texto_a_bloques( $texto ) {
	$out = array();
	foreach ( preg_split( "/\n\s*\n/", str_replace( "\r", '', (string) $texto ) ) as $p ) {
		$p = trim( $p );
		if ( '' === $p ) {
			continue;
		}
		$p     = make_clickable( nl2br( esc_html( $p ), false ) );
		$out[] = "<!-- wp:paragraph -->\n<p>{$p}</p>\n<!-- /wp:paragraph -->";
	}
	return implode( "\n\n", $out );
}

// ---------- Lectura ----------

function lp_meta_galeria( $campo, $i ) {
	return $campo['name'] . '_' . $campo['sub_fields'][ $i ]['name'];
}

function lp_img_valida( $id ) {
	$id = (int) $id;
	return ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;
}

function lp_leer( $item, $post_id ) {
	switch ( $item['tipo'] ) {
		case 'titulo':
			return (string) get_post_field( 'post_title', $post_id );
		case 'contenido':
			return lp_html_a_texto( get_post_field( 'post_content', $post_id ) );
		case 'extracto':
			return (string) get_post_field( 'post_excerpt', $post_id );
		case 'foto':
			return lp_img_valida( get_post_thumbnail_id( $post_id ) );
		case 'tax':
			$ids = wp_get_object_terms( $post_id, $item['tax'], array( 'fields' => 'ids' ) );
			$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
			sort( $ids );
			return $ids;
		case 'galeria':
			$out = array();
			foreach ( array_keys( $item['acf']['sub_fields'] ) as $i ) {
				$v = lp_img_valida( get_post_meta( $post_id, lp_meta_galeria( $item['acf'], $i ), true ) );
				if ( $v ) {
					$out[] = $v;
				}
			}
			return $out;
	}
	$f = $item['acf'];
	$v = get_post_meta( $post_id, $f['name'], true );
	switch ( $f['type'] ) {
		case 'image':
			return lp_img_valida( is_array( $v ) ? ( $v['ID'] ?? 0 ) : $v );
		case 'checkbox':
			return array_values( array_map( 'strval', (array) ( '' === $v ? array() : maybe_unserialize( $v ) ) ) );
		case 'true_false':
			return '1' === (string) $v ? '1' : '0'; // vacío = No (entradas viejas sin valor guardado)
		case 'date_picker':
			return preg_match( '/^(\d{4})(\d{2})(\d{2})$/', (string) $v, $m ) ? "$m[1]-$m[2]-$m[3]" : '';
		case 'time_picker':
			return preg_match( '/^(\d{2}):(\d{2})/', (string) $v, $m ) ? "$m[1]:$m[2]" : '';
		case 'wysiwyg':
			return lp_html_a_texto( $v );
		case 'number':
			return is_scalar( $v ) ? (string) $v : '';
	}
	return is_scalar( $v ) ? (string) $v : '';
}

function lp_valores_post( $slug, $post_id ) {
	$out = array();
	foreach ( lp_items( $slug ) as $it ) {
		$out[ $it['id'] ] = lp_leer( $it, $post_id );
	}
	return $out;
}

// Valores iniciales de un contenido nuevo (los "valor por defecto" de ACF).
function lp_valores_nuevos( $slug ) {
	$out = array();
	foreach ( lp_items( $slug ) as $it ) {
		$v = '';
		if ( in_array( $it['tipo'], array( 'tax', 'galeria', 'checkbox' ), true ) ) {
			$v = array();
		} elseif ( 'foto' === $it['tipo'] || 'image' === $it['tipo'] ) {
			$v = 0;
		} elseif ( isset( $it['acf'] ) ) {
			$d = $it['acf']['default_value'] ?? '';
			if ( 'true_false' === $it['tipo'] ) {
				$v = $d ? '1' : '0';
			} elseif ( is_scalar( $d ) && false !== $d ) {
				$v = (string) $d;
			}
		}
		$out[ $it['id'] ] = $v;
	}
	return $out;
}

// ---------- Recepción y validación ----------

// Valores tal como llegan del formulario (lp[id]).
function lp_valores_request( $slug ) {
	$raw = isset( $_POST['lp'] ) && is_array( $_POST['lp'] ) ? wp_unslash( $_POST['lp'] ) : array(); // phpcs:ignore WordPress.Security
	$out = array();
	foreach ( lp_items( $slug ) as $it ) {
		$v = isset( $raw[ $it['id'] ] ) ? $raw[ $it['id'] ] : null;
		switch ( $it['tipo'] ) {
			case 'tax':
				$out[ $it['id'] ] = array_values( array_unique( array_filter( array_map( 'intval', (array) $v ) ) ) );
				break;
			case 'galeria':
				$out[ $it['id'] ] = array_values( array_filter( array_map( 'intval', (array) $v ) ) );
				break;
			case 'checkbox':
				$out[ $it['id'] ] = array_values( array_map( 'strval', (array) $v ) );
				break;
			case 'foto':
			case 'image':
				$out[ $it['id'] ] = (int) ( is_scalar( $v ) ? $v : 0 );
				break;
			case 'true_false':
				$out[ $it['id'] ] = '1' === ( is_scalar( $v ) ? (string) $v : '' ) ? '1' : '0';
				break;
			default:
				$out[ $it['id'] ] = is_scalar( $v ) ? (string) $v : null; // null = no vino (campo bloqueado)
		}
	}
	return $out;
}

// ---------- Lógica condicional de ACF ----------

/**
 * Valor de un campo para evaluar condiciones: el del formulario si está, si no el guardado
 * (o el valor por defecto de ACF en uno nuevo). Normalizado a string o array de strings.
 */
function lp_cond_valor( $key, $vals, $post_id ) {
	if ( array_key_exists( $key, $vals ) && null !== $vals[ $key ] ) {
		$v = $vals[ $key ];
	} else {
		$f = function_exists( 'acf_get_field' ) ? acf_get_field( $key ) : null;
		if ( ! $f ) {
			return '';
		}
		if ( $post_id ) {
			$v = get_post_meta( $post_id, $f['name'], true );
			$v = is_string( $v ) ? maybe_unserialize( $v ) : $v;
		} else {
			$v = $f['default_value'] ?? '';
		}
		if ( 'true_false' === $f['type'] ) {
			$v = $v ? '1' : '0';
		}
	}
	if ( is_array( $v ) ) {
		return array_values( array_map( 'strval', $v ) );
	}
	return null === $v || false === $v ? '' : (string) $v;
}

// Una regla de ACF: array( field, operator, value ).
function lp_cond_regla( $op, $v, $esperado ) {
	$esperado = (string) $esperado;
	switch ( $op ) {
		case '==':
			return is_array( $v ) ? in_array( $esperado, $v, true ) : $v === $esperado;
		case '!=':
			return is_array( $v ) ? ! in_array( $esperado, $v, true ) : $v !== $esperado;
		case '==empty':
			return is_array( $v ) ? ! $v : '' === $v;
		case '!=empty':
			return is_array( $v ) ? (bool) $v : '' !== $v;
		case '==contains':
			return false !== mb_stripos( is_array( $v ) ? implode( ' ', $v ) : $v, $esperado );
		case '==pattern':
			return (bool) @preg_match( '/' . str_replace( '/', '\\/', $esperado ) . '/', is_array( $v ) ? implode( ' ', $v ) : $v ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		case '>':
			return ! is_array( $v ) && '' !== $v && is_numeric( $v ) && (float) $v > (float) $esperado;
		case '<':
			return ! is_array( $v ) && '' !== $v && is_numeric( $v ) && (float) $v < (float) $esperado;
	}
	return true; // operador desconocido: no ocultar
}

// Condiciones de un campo (las de ACF), o array() si no tiene.
function lp_cond_de( $it ) {
	if ( empty( $it['acf']['conditional_logic'] ) || ! is_array( $it['acf']['conditional_logic'] ) ) {
		return array();
	}
	return apply_filters( 'lp_condiciones', $it['acf']['conditional_logic'], $it );
}

/**
 * ¿Se muestra el campo? Grupos de reglas: alcanza con que se cumpla un grupo (O);
 * dentro de un grupo se tienen que cumplir todas (Y). Igual que ACF.
 */
function lp_cond_visible( $it, $vals, $post_id = 0 ) {
	$grupos = lp_cond_de( $it );
	if ( ! $grupos ) {
		return true;
	}
	foreach ( $grupos as $reglas ) {
		$ok = true;
		foreach ( (array) $reglas as $r ) {
			if ( empty( $r['field'] ) || ! lp_cond_regla( $r['operator'] ?? '==', lp_cond_valor( $r['field'], $vals, $post_id ), $r['value'] ?? '' ) ) {
				$ok = false;
				break;
			}
		}
		if ( $ok ) {
			return true;
		}
	}
	return false;
}

// Condiciones para el navegador: reglas + valor actual de los campos que no están en el formulario.
function lp_cond_js( $slug, $it, $vals, $post_id ) {
	$grupos = lp_cond_de( $it );
	if ( ! $grupos ) {
		return '';
	}
	$en_form = wp_list_pluck( lp_items( $slug ), 'id' );
	$out     = array();
	foreach ( $grupos as $reglas ) {
		$g = array();
		foreach ( (array) $reglas as $r ) {
			if ( empty( $r['field'] ) ) {
				continue;
			}
			$x = array(
				'f'  => $r['field'],
				'op' => $r['operator'] ?? '==',
				'v'  => (string) ( $r['value'] ?? '' ),
			);
			if ( ! in_array( $r['field'], $en_form, true ) ) {
				$x['fijo'] = lp_cond_valor( $r['field'], array(), $post_id );
			}
			$g[] = $x;
		}
		$out[] = $g;
	}
	return wp_json_encode( $out );
}

// Fecha 'Y-m-d' de verdad (descarta 2026-02-31).
function lp_fecha_ok( $v ) {
	$d = DateTime::createFromFormat( '!Y-m-d', (string) $v );
	return $d && $d->format( 'Y-m-d' ) === $v;
}

/**
 * Limpia y valida. Devuelve array( valores limpios, errores[id] ).
 * $actuales: valores guardados (para permitir imágenes que ya estaban y campos bloqueados).
 * $estricto: false para borradores (no exige obligatorios ni imágenes).
 */
function lp_validar( $slug, $vals, $actuales = array(), $estricto = true, $post_id = 0 ) {
	$tipo  = lp_tipo( $slug );
	$items = lp_items( $slug );
	$out   = array();
	$err   = array();

	$img_ok = function ( $id, $id_actual ) {
		$id = lp_img_valida( $id );
		if ( ! $id ) {
			return 0;
		}
		$antes = is_array( $id_actual ) ? $id_actual : array( $id_actual );
		return ( in_array( $id, array_map( 'intval', $antes ), true ) || current_user_can( 'edit_post', $id ) ) ? $id : 0;
	};

	foreach ( $items as $it ) {
		$id  = $it['id'];
		$v   = array_key_exists( $id, $vals ) ? $vals[ $id ] : null;
		$act = array_key_exists( $id, $actuales ) ? $actuales[ $id ] : null;
		$f   = isset( $it['acf'] ) ? $it['acf'] : array();
		$lbl = $it['label'];

		switch ( $it['tipo'] ) {
			case 'titulo':
				$v = trim( sanitize_text_field( (string) $v ) );
				if ( '' === $v ) {
					$err[ $id ] = 'Escribí el título.';
				} elseif ( mb_strlen( $v ) > 150 ) {
					$err[ $id ] = 'El título es muy largo (máximo 150 caracteres).';
				}
				break;
			case 'contenido':
			case 'wysiwyg':
				if ( null === $v ) { // bloqueado: no se toca
					$out[ $id ] = null;
					continue 2;
				}
				$v = trim( sanitize_textarea_field( $v ) );
				if ( mb_strlen( $v ) > 20000 ) {
					$err[ $id ] = 'El texto es demasiado largo.';
				}
				break;
			case 'extracto':
				$v = trim( sanitize_textarea_field( (string) $v ) );
				if ( mb_strlen( $v ) > 400 ) {
					$err[ $id ] = 'El resumen es muy largo (máximo 400 caracteres).';
				}
				break;
			case 'foto':
			case 'image':
				$v = $img_ok( $v, $act );
				break;
			case 'galeria':
				$v = array_slice( array_values( array_filter( array_map( function ( $x ) use ( $img_ok, $act ) {
					return $img_ok( $x, (array) $act );
				}, (array) $v ) ) ), 0, count( $f['sub_fields'] ) );
				break;
			case 'tax':
				$validos = get_terms( array( 'taxonomy' => $it['tax'], 'hide_empty' => false, 'fields' => 'ids' ) );
				$validos = is_wp_error( $validos ) ? array() : array_map( 'intval', $validos );
				$v       = array_values( array_intersect( (array) $v, $validos ) );
				if ( ! empty( $it['unica'] ) ) {
					$v = array_slice( $v, 0, 1 );
				}
				sort( $v );
				break;
			case 'text':
			case 'url':
			case 'email':
				$v = trim( sanitize_text_field( (string) $v ) );
				if ( 'url' === $it['tipo'] && '' !== $v ) {
					if ( ! preg_match( '#^https?://#i', $v ) ) {
						$v = 'https://' . ltrim( $v, '/' );
					}
					$v    = esc_url_raw( $v, array( 'http', 'https' ) );
					$host = (string) wp_parse_url( $v, PHP_URL_HOST );
					if ( '' === $v || ! filter_var( $v, FILTER_VALIDATE_URL ) || false === strpos( $host, '.' ) || preg_match( '/\s/', $v ) ) {
						$err[ $id ] = '«' . $lbl . '»: la dirección no es válida. Copiala entera desde el navegador.';
					}
				}
				if ( 'email' === $it['tipo'] && '' !== $v && ! is_email( $v ) ) {
					$err[ $id ] = '«' . $lbl . '»: el email no parece válido.';
				}
				if ( ! empty( $f['maxlength'] ) && mb_strlen( $v ) > (int) $f['maxlength'] ) {
					$err[ $id ] = '«' . $lbl . '» supera los ' . (int) $f['maxlength'] . ' caracteres.';
				}
				break;
			case 'textarea':
				$v = trim( sanitize_textarea_field( (string) $v ) );
				if ( ! empty( $f['maxlength'] ) && mb_strlen( $v ) > (int) $f['maxlength'] ) {
					$err[ $id ] = '«' . $lbl . '» supera los ' . (int) $f['maxlength'] . ' caracteres.';
				}
				break;
			case 'number':
				$v = trim( str_replace( ',', '.', sanitize_text_field( (string) $v ) ) );
				if ( '' !== $v ) {
					if ( ! is_numeric( $v ) ) {
						$err[ $id ] = '«' . $lbl . '»: escribí solo el número.';
					} elseif ( '' !== ( $f['min'] ?? '' ) && (float) $v < (float) $f['min'] ) {
						$err[ $id ] = '«' . $lbl . '»: el mínimo es ' . $f['min'] . '.';
					} elseif ( '' !== ( $f['max'] ?? '' ) && (float) $v > (float) $f['max'] ) {
						$err[ $id ] = '«' . $lbl . '»: el máximo es ' . $f['max'] . '.';
					} else {
						$v = (string) ( 0 + $v );
					}
				}
				break;
			case 'select':
			case 'radio':
			case 'button_group':
				$v = (string) $v;
				if ( '' !== $v && ! array_key_exists( $v, (array) $f['choices'] ) ) {
					$v = '';
				}
				break;
			case 'checkbox':
				$v = array_values( array_intersect( array_map( 'strval', array_keys( (array) $f['choices'] ) ), (array) $v ) );
				break;
			case 'true_false':
				$v = '1' === $v ? '1' : '0';
				break;
			case 'date_picker':
				$v = trim( (string) $v );
				if ( '' !== $v && ! lp_fecha_ok( $v ) ) {
					$err[ $id ] = '«' . $lbl . '»: la fecha no es válida.';
				}
				break;
			case 'time_picker':
				$v = trim( (string) $v );
				if ( '' !== $v ) {
					$d = DateTime::createFromFormat( '!H:i', $v );
					if ( ! $d || $d->format( 'H:i' ) !== $v ) {
						$err[ $id ] = '«' . $lbl . '»: escribí la hora así: 19:30.';
					}
				}
				break;
		}
		if ( $estricto && ! isset( $err[ $id ] ) && $it['requerido'] && 'titulo' !== $id && 'true_false' !== $it['tipo'] && ( '' === $v || array() === $v || 0 === $v || null === $v ) ) {
			$err[ $id ] = 'Completá «' . $lbl . '».';
		}
		$out[ $id ] = $v;
	}

	// Campos ocultos por la lógica condicional de ACF: no se validan ni se guardan (queda lo que había).
	foreach ( $items as $it ) {
		if ( ! lp_cond_visible( $it, $out, $post_id ) ) {
			unset( $err[ $it['id'] ] );
			$out[ $it['id'] ] = null;
		}
	}

	// Regla: fecha de fin igual o posterior a la de inicio.
	$fi = $tipo['fecha_inicio'];
	$ff = $tipo['fecha_fin'];
	if ( $fi && $ff && ! empty( $out[ $fi ] ) && ! empty( $out[ $ff ] ) && ! isset( $err[ $fi ] ) && ! isset( $err[ $ff ] ) && $out[ $ff ] < $out[ $fi ] ) {
		$err[ $ff ] = 'La fecha de finalización tiene que ser igual o posterior a la de inicio.';
	}
	if ( $estricto && $fi && $ff && empty( $out[ $fi ] ) && ! empty( $out[ $ff ] ) ) {
		$err[ $fi ] = 'Poné también la fecha de inicio.';
	}

	// Regla: al menos una de estas imágenes.
	$grupo = array_values( array_intersect( (array) $tipo['al_menos_uno'], array_keys( $out ) ) );
	if ( $estricto && $grupo ) {
		$hay = false;
		foreach ( $grupo as $g ) {
			if ( ! empty( $out[ $g ] ) ) {
				$hay = true;
			}
		}
		if ( ! $hay ) {
			$err[ $grupo[0] ] = lp_msg_al_menos_uno( $slug );
		}
	}
	return array( $out, $err );
}

function lp_msg_al_menos_uno( $slug ) {
	$tipo = lp_tipo( $slug );
	if ( ! empty( $tipo['al_menos_msg'] ) ) {
		return $tipo['al_menos_msg'];
	}
	$nombres = array();
	foreach ( lp_items( $slug ) as $it ) {
		if ( in_array( $it['id'], (array) $tipo['al_menos_uno'], true ) ) {
			$nombres[] = mb_strtolower( $it['label'] );
		}
	}
	$ultimo = array_pop( $nombres );
	return 'Subí ' . ( $nombres ? 'al menos una imagen: ' . implode( ', ', $nombres ) . ' o ' . $ultimo : 'la ' . $ultimo ) . '.';
}

// ---------- Escritura ----------

/**
 * Guarda los campos (no el título ni el contenido, que van en wp_insert_post).
 */
function lp_escribir( $slug, $post_id, $vals ) {
	foreach ( lp_items( $slug ) as $it ) {
		$id = $it['id'];
		if ( ! array_key_exists( $id, $vals ) || null === $vals[ $id ] ) {
			continue;
		}
		$v = $vals[ $id ];
		switch ( $it['tipo'] ) {
			case 'titulo':
			case 'contenido':
			case 'extracto':
				break;
			case 'foto':
				if ( $v ) {
					set_post_thumbnail( $post_id, $v );
				} else {
					delete_post_thumbnail( $post_id );
				}
				break;
			case 'tax':
				if ( ! $v && 'category' === $it['tax'] ) {
					$v = array( (int) get_option( 'default_category' ) );
				}
				wp_set_object_terms( $post_id, $v, $it['tax'], false );
				break;
			case 'galeria':
				$f = $it['acf'];
				foreach ( array_values( $f['sub_fields'] ) as $i => $sub ) {
					update_post_meta( $post_id, lp_meta_galeria( $f, $i ), isset( $v[ $i ] ) ? (int) $v[ $i ] : '' );
					update_post_meta( $post_id, '_' . lp_meta_galeria( $f, $i ), $sub['key'] );
				}
				update_post_meta( $post_id, $f['name'], '' );
				update_post_meta( $post_id, '_' . $f['name'], $f['key'] );
				break;
			case 'date_picker':
				update_field( $it['id'], '' === $v ? '' : str_replace( '-', '', $v ), $post_id );
				break;
			case 'time_picker':
				update_field( $it['id'], '' === $v ? '' : $v . ':00', $post_id );
				break;
			case 'true_false':
				update_field( $it['id'], (int) $v, $post_id );
				break;
			case 'wysiwyg':
				update_field( $it['id'], '' === $v ? '' : wpautop( make_clickable( esc_html( $v ) ) ), $post_id );
				break;
			default:
				update_field( $it['id'], $v, $post_id );
		}
	}
}

// Imágenes que usa un contenido (para adjuntarlas al contenido).
function lp_imagenes_de( $slug, $vals ) {
	$ids = array();
	foreach ( lp_items( $slug ) as $it ) {
		if ( in_array( $it['tipo'], array( 'foto', 'image', 'galeria' ), true ) && ! empty( $vals[ $it['id'] ] ) ) {
			$ids = array_merge( $ids, array_map( 'intval', (array) $vals[ $it['id'] ] ) );
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}
