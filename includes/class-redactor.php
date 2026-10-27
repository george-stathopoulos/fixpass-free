<?php
/**
 * Removes secrets and email addresses from text before it is copied for support.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pattern-based redaction for the context captured by Spotlight (error messages, request URLs).
 */
final class Fixpass_Redactor {

	/**
	 * Redact a string.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function text( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return $text;
		}
		$patterns = array(
			// key=value secrets in URLs, query strings and messages.
			'/((?:pass(?:word|wd)?|pwd|secret|token|api[_-]?key|apikey|auth|authorization|nonce|session)["\']?\s*[=:]\s*["\']?)([^\s"\'&,;]{3,})/i' => '$1[redacted]',
			// Bearer / Basic credentials.
			'/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/]{8,}=*/' => '$1 [redacted]',
			// Well-known key formats.
			'/\b(?:sk|pk|rk)[-_](?:live|test|ant|proj)?[-_]?[A-Za-z0-9_\-]{16,}\b/' => '[redacted-key]',
			'/\b(?:gh[pousr]_[A-Za-z0-9]{20,}|xox[abpr]-[A-Za-z0-9\-]{10,}|AKIA[0-9A-Z]{16}|AIza[0-9A-Za-z\-_]{30,})\b/' => '[redacted-key]',
			// Long random-looking strings (hashes, tokens).
			'/\b[A-Fa-f0-9]{40,}\b/' => '[redacted-hash]',
			// Card-like numbers.
			'/\b(?:\d[ -]?){13,19}\b/' => '[redacted-number]',
		);
		$text = (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), $text );

		// Emails: keep the domain, hide the mailbox.
		return (string) preg_replace_callback(
			'/([A-Za-z0-9._%+\-]+)@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/',
			static function ( $m ) {
				return substr( $m[1], 0, 1 ) . '***@' . $m[2];
			},
			$text
		);
	}
}
