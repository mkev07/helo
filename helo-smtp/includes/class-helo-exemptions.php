<?php
/**
 * Who gets to skip the Turnstile check.
 *
 * Without this, a staff member who posts twenty times a day solves a challenge
 * twenty times a day, and an office or monitoring service gets treated as a
 * bot. Three levers: signed-in users, an IP allowlist, and a user-agent
 * allowlist.
 *
 * Comments are deliberately exempt from all of it — see Helo_Turnstile.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Exemptions {

	/**
	 * Should the current request skip verification?
	 *
	 * @param string $form_action Form label, so callers can be excluded.
	 * @return bool
	 */
	public static function applies( $form_action = '' ) {
		// Never exempt our own settings screen previews, and never exempt the
		// surfaces marked strict (comments).
		if ( 'wordpress-comment' === $form_action ) {
			return false;
		}

		$exempt = false;

		if ( Helo_Settings::get( 'turnstile_skip_users' ) && self::is_signed_in() ) {
			$exempt = true;
		}

		if ( ! $exempt && self::ip_listed( self::remote_ip(), Helo_Settings::get( 'turnstile_skip_ips' ) ) ) {
			$exempt = true;
		}

		if ( ! $exempt && self::agent_listed( self::user_agent(), Helo_Settings::get( 'turnstile_skip_agents' ) ) ) {
			$exempt = true;
		}

		/**
		 * Filter the exemption decision.
		 *
		 * @param bool   $exempt      Whether to skip the check.
		 * @param string $form_action Form label.
		 */
		return (bool) apply_filters( 'helo_turnstile_exempt', $exempt, $form_action );
	}

	/**
	 * Logged in, including REST requests that drop the current user.
	 *
	 * Contact Form 7 and friends post to /wp-json without an X-WP-Nonce, which
	 * leaves WordPress treating a perfectly valid session as anonymous. The
	 * signed cookie is still there, so check it directly.
	 *
	 * @return bool
	 */
	public static function is_signed_in() {
		if ( is_user_logged_in() ) {
			return true;
		}

		if ( ! defined( 'LOGGED_IN_COOKIE' ) || empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			return false;
		}

		return (bool) wp_validate_auth_cookie( $_COOKIE[ LOGGED_IN_COOKIE ], 'logged_in' );
	}

	/* ------------------------------------------------------------------ ip */

	/**
	 * The visitor's IP.
	 *
	 * Proxy headers are attacker-controlled unless something upstream
	 * overwrites them, which is why the admin screen warns that an IP
	 * allowlist is only as trustworthy as the proxy in front of it.
	 * CF-Connecting-IP is preferred because Cloudflare always rewrites it.
	 *
	 * @return string Validated IP, or ''.
	 */
	public static function remote_ip() {
		$cf = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';
		if ( $cf && filter_var( $cf, FILTER_VALIDATE_IP ) ) {
			return $cf;
		}

		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) : '';
		if ( $forwarded ) {
			$chain = explode( ',', $forwarded );

			// Prefer the first routable address in the chain.
			foreach ( $chain as $candidate ) {
				$candidate = trim( $candidate );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $candidate;
				}
			}

			foreach ( $chain as $candidate ) {
				$candidate = trim( $candidate );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
	}

	public static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	/**
	 * Is $ip covered by a newline-separated list of IPs and CIDR ranges?
	 *
	 * Pure — tested.
	 *
	 * @param string $ip   Candidate address.
	 * @param string $list Newline-separated entries.
	 * @return bool
	 */
	public static function ip_listed( $ip, $list ) {
		if ( '' === (string) $ip || '' === trim( (string) $list ) ) {
			return false;
		}

		foreach ( preg_split( '/[\r\n]+/', (string) $list ) as $entry ) {
			$entry = trim( $entry );

			if ( '' === $entry || 0 === strpos( $entry, '#' ) ) {
				continue;
			}

			if ( self::ip_matches( $ip, $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Exact address or CIDR range, IPv4 and IPv6. Pure — tested.
	 *
	 * @param string $ip    Candidate address.
	 * @param string $entry Allowlist entry.
	 * @return bool
	 */
	public static function ip_matches( $ip, $entry ) {
		if ( false === strpos( $entry, '/' ) ) {
			$a = @inet_pton( $ip );
			$b = @inet_pton( $entry );

			return ( false !== $a && false !== $b && $a === $b );
		}

		return self::ip_in_cidr( $ip, $entry );
	}

	/**
	 * Compare the first N bits of two packed addresses. Pure — tested.
	 *
	 * @param string $ip   Candidate address.
	 * @param string $cidr Range in a.b.c.d/nn or v6/nnn form.
	 * @return bool
	 */
	public static function ip_in_cidr( $ip, $cidr ) {
		$parts = explode( '/', $cidr, 2 );

		if ( 2 !== count( $parts ) || '' === $parts[1] || ! ctype_digit( ltrim( $parts[1] ) ) ) {
			return false;
		}

		$packed_ip  = @inet_pton( $ip );
		$packed_net = @inet_pton( trim( $parts[0] ) );

		// A false from inet_pton, or a v4/v6 mismatch, is simply not a match.
		if ( false === $packed_ip || false === $packed_net || strlen( $packed_ip ) !== strlen( $packed_net ) ) {
			return false;
		}

		$bits     = (int) $parts[1];
		$max_bits = strlen( $packed_ip ) * 8;

		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}

		$whole_bytes = intdiv( $bits, 8 );
		$spare_bits  = $bits % 8;

		if ( $whole_bytes > 0 && substr( $packed_ip, 0, $whole_bytes ) !== substr( $packed_net, 0, $whole_bytes ) ) {
			return false;
		}

		if ( 0 === $spare_bits ) {
			return true;
		}

		$mask = chr( ( 0xFF << ( 8 - $spare_bits ) ) & 0xFF );

		return ( $packed_ip[ $whole_bytes ] & $mask ) === ( $packed_net[ $whole_bytes ] & $mask );
	}

	/* -------------------------------------------------------------- agents */

	/**
	 * Does the user agent contain any listed substring? Pure — tested.
	 *
	 * @param string $agent Candidate user agent.
	 * @param string $list  Newline-separated substrings.
	 * @return bool
	 */
	public static function agent_listed( $agent, $list ) {
		if ( '' === (string) $agent || '' === trim( (string) $list ) ) {
			return false;
		}

		foreach ( preg_split( '/[\r\n]+/', (string) $list ) as $entry ) {
			$entry = trim( $entry );

			if ( '' === $entry || 0 === strpos( $entry, '#' ) ) {
				continue;
			}

			if ( false !== stripos( $agent, $entry ) ) {
				return true;
			}
		}

		return false;
	}
}
