<?php
/**
 * Reemplazos por si el servidor no tiene la extensión mbstring.
 *
 * El panel usa mb_strtolower, mb_strtoupper y mb_stripos para los textos con tildes y eñes.
 * WordPress ya trae mb_substr y mb_strlen propios; estos tres no. Si falta mbstring,
 * se usan estas versiones simples (alcanzan para español) y el panel no se cae.
 * Si mbstring está, no se define nada y se usan las del servidor.
 */

defined( 'ABSPATH' ) || exit;

// Letras con tilde, diéresis y eñe: minúscula => mayúscula.
function lp_compat_letras() {
	return array(
		'á' => 'Á', 'é' => 'É', 'í' => 'Í', 'ó' => 'Ó', 'ú' => 'Ú',
		'à' => 'À', 'è' => 'È', 'ì' => 'Ì', 'ò' => 'Ò', 'ù' => 'Ù',
		'â' => 'Â', 'ê' => 'Ê', 'î' => 'Î', 'ô' => 'Ô', 'û' => 'Û',
		'ä' => 'Ä', 'ë' => 'Ë', 'ï' => 'Ï', 'ö' => 'Ö', 'ü' => 'Ü',
		'ã' => 'Ã', 'õ' => 'Õ', 'ñ' => 'Ñ', 'ç' => 'Ç',
	);
}

if ( ! function_exists( 'mb_strtolower' ) ) {
	function mb_strtolower( $s, $encoding = null ) {
		return strtr( strtolower( (string) $s ), array_flip( lp_compat_letras() ) );
	}
}

if ( ! function_exists( 'mb_strtoupper' ) ) {
	function mb_strtoupper( $s, $encoding = null ) {
		return strtr( strtoupper( (string) $s ), lp_compat_letras() );
	}
}

if ( ! function_exists( 'mb_stripos' ) ) {
	function mb_stripos( $haystack, $needle, $offset = 0, $encoding = null ) {
		$h = mb_strtolower( (string) $haystack );
		$n = mb_strtolower( (string) $needle );
		if ( '' === $n ) {
			return false;
		}
		// Se busca en bytes y se devuelve la posición en caracteres, como la original.
		$desde = $offset > 0 ? strlen( mb_substr( $h, 0, $offset ) ) : 0;
		$pos   = strpos( $h, $n, $desde );
		return false === $pos ? false : mb_strlen( substr( $h, 0, $pos ) );
	}
}
