<?php
/**
 * Minimal read-only IMAP client over SSL (no PHP imap extension needed).
 * It never deletes or changes mail: it uses BODY.PEEK, so messages stay unread.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

class KB_Imap {

	private $fp  = null;
	private $tag = 0;

	public function connect( $host, $port, $user, $pass ) {
		$ctx      = stream_context_create( array( 'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true ) ) );
		$this->fp = @stream_socket_client( 'ssl://' . $host . ':' . (int) $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $this->fp ) {
			throw new RuntimeException( 'IMAP connect failed: ' . $errstr );
		}
		stream_set_timeout( $this->fp, 30 );
		$greeting = fgets( $this->fp );
		if ( false === $greeting || ! str_starts_with( $greeting, '* OK' ) ) {
			throw new RuntimeException( 'IMAP server did not say hello.' );
		}
		$this->command( 'LOGIN ' . $this->quote( $user ) . ' ' . $this->quote( $pass ) );
	}

	public function close() {
		if ( $this->fp ) {
			try {
				$this->command( 'LOGOUT' );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// Already closing.
			}
			fclose( $this->fp );
			$this->fp = null;
		}
	}

	/** @return array{uidvalidity:int, uidnext:int} */
	public function select_inbox() {
		list( $text ) = $this->command( 'EXAMINE INBOX' );
		preg_match( '/UIDVALIDITY (\d+)/', $text, $v );
		preg_match( '/UIDNEXT (\d+)/', $text, $n );
		return array(
			'uidvalidity' => (int) ( $v[1] ?? 0 ),
			'uidnext'     => (int) ( $n[1] ?? 0 ),
		);
	}

	/** UIDs greater than $after. */
	public function uids_after( $after ) {
		list( $text ) = $this->command( 'UID SEARCH UID ' . ( (int) $after + 1 ) . ':*' );
		$uids         = array();
		if ( preg_match( '/^\* SEARCH([\d ]*)/m', $text, $m ) ) {
			foreach ( preg_split( '/\s+/', trim( $m[1] ) ) as $u ) {
				if ( '' !== $u && (int) $u > (int) $after ) {
					$uids[] = (int) $u;
				}
			}
		}
		sort( $uids );
		return $uids;
	}

	/**
	 * Fetch one full message and parse it.
	 *
	 * @return array{from:string, from_name:string, subject:string, message_id:string, in_reply_to:string, text:string}|null
	 */
	public function fetch( $uid ) {
		list( , $literals ) = $this->command( 'UID FETCH ' . (int) $uid . ' (BODY.PEEK[])' );
		if ( empty( $literals ) ) {
			return null;
		}
		return self::parse_message( $literals[0] );
	}

	/* ---------- protocol ---------- */

	private function quote( $s ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $s ) . '"';
	}

	/**
	 * Run a command. Returns [response text, literal strings].
	 */
	private function command( $cmd ) {
		$tag = 'K' . ( ++$this->tag );
		fwrite( $this->fp, $tag . ' ' . $cmd . "\r\n" );
		$text     = '';
		$literals = array();
		while ( true ) {
			$line = fgets( $this->fp );
			if ( false === $line ) {
				throw new RuntimeException( 'IMAP connection closed.' );
			}
			if ( preg_match( '/\{(\d+)\}\r\n$/', $line, $m ) ) {
				$text      .= $line;
				$literals[] = $this->read_bytes( (int) $m[1] );
				continue;
			}
			$text .= $line;
			if ( str_starts_with( $line, $tag . ' ' ) ) {
				if ( ! preg_match( '/^' . $tag . ' OK/i', $line ) ) {
					throw new RuntimeException( 'IMAP: ' . trim( substr( $line, strlen( $tag ) + 1 ) ) );
				}
				break;
			}
		}
		return array( $text, $literals );
	}

	private function read_bytes( $n ) {
		$out = '';
		while ( strlen( $out ) < $n ) {
			$chunk = fread( $this->fp, $n - strlen( $out ) );
			if ( false === $chunk || '' === $chunk ) {
				throw new RuntimeException( 'IMAP read failed.' );
			}
			$out .= $chunk;
		}
		return $out;
	}

	/* ---------- MIME ---------- */

	public static function parse_message( $raw ) {
		list( $headers, $body ) = self::split( $raw );
		$from                   = self::decode_header( $headers['from'] ?? '' );
		$email                  = '';
		if ( preg_match( '/<([^>]+)>/', $from, $m ) ) {
			$email = $m[1];
		} elseif ( preg_match( '/[\w.+\-]+@[\w\-.]+\.\w+/', $from, $m ) ) {
			$email = $m[0];
		}
		$name = trim( preg_replace( '/<[^>]*>/', '', $from ), " \"'" );
		$text = self::extract_text( $headers, $body );
		return array(
			'from'        => strtolower( trim( $email ) ),
			'from_name'   => $name,
			'subject'     => self::decode_header( $headers['subject'] ?? '' ),
			'message_id'  => trim( $headers['message-id'] ?? '' ),
			'in_reply_to' => trim( $headers['in-reply-to'] ?? '' ),
			'references'  => trim( $headers['references'] ?? '' ),
			'auto'        => ! empty( $headers['auto-submitted'] ) && 'no' !== strtolower( trim( $headers['auto-submitted'] ) ),
			'full_text'   => $text,
			'text'        => self::strip_quoted( $text ),
		);
	}

	/** Split raw MIME into [lowercased headers, body]. */
	private static function split( $raw ) {
		$raw   = str_replace( "\r\n", "\n", $raw );
		$pos   = strpos( $raw, "\n\n" );
		$head  = false === $pos ? $raw : substr( $raw, 0, $pos );
		$body  = false === $pos ? '' : substr( $raw, $pos + 2 );
		$head  = preg_replace( "/\n[ \t]+/", ' ', $head );
		$out   = array();
		foreach ( explode( "\n", $head ) as $line ) {
			$p = strpos( $line, ':' );
			if ( false !== $p ) {
				$k = strtolower( trim( substr( $line, 0, $p ) ) );
				if ( ! isset( $out[ $k ] ) ) {
					$out[ $k ] = trim( substr( $line, $p + 1 ) );
				}
			}
		}
		return array( $out, $body );
	}

	private static function decode_header( $v ) {
		if ( function_exists( 'iconv_mime_decode' ) ) {
			$d = @iconv_mime_decode( $v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false !== $d ) {
				return $d;
			}
		}
		return mb_decode_mimeheader( $v );
	}

	/** Find the best text part: text/plain first, else text/html stripped. */
	private static function extract_text( $headers, $body, $depth = 0 ) {
		$ctype = strtolower( $headers['content-type'] ?? 'text/plain' );
		if ( $depth < 5 && str_starts_with( $ctype, 'multipart/' ) && preg_match( '/boundary="?([^";]+)"?/i', $headers['content-type'], $m ) ) {
			$parts = explode( '--' . $m[1], $body );
			$html  = '';
			foreach ( $parts as $part ) {
				$part = ltrim( $part, "\n" );
				if ( '' === trim( $part ) || str_starts_with( $part, '--' ) ) {
					continue;
				}
				list( $ph, $pb ) = self::split( $part );
				$pt              = strtolower( $ph['content-type'] ?? 'text/plain' );
				if ( str_contains( strtolower( $ph['content-disposition'] ?? '' ), 'attachment' ) ) {
					continue;
				}
				if ( str_starts_with( $pt, 'text/plain' ) || str_starts_with( $pt, 'multipart/' ) ) {
					$t = self::extract_text( $ph, $pb, $depth + 1 );
					if ( '' !== trim( $t ) ) {
						return $t;
					}
				} elseif ( str_starts_with( $pt, 'text/html' ) && '' === $html ) {
					$html = self::extract_text( $ph, $pb, $depth + 1 );
				} elseif ( str_starts_with( $pt, 'message/delivery-status' ) ) {
					$html .= "\n" . $pb;
				}
			}
			return $html;
		}
		$enc = strtolower( trim( $headers['content-transfer-encoding'] ?? '' ) );
		if ( 'base64' === $enc ) {
			$body = (string) base64_decode( preg_replace( '/\s+/', '', $body ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		} elseif ( 'quoted-printable' === $enc ) {
			$body = quoted_printable_decode( $body );
		}
		if ( preg_match( '/charset="?([\w\-]+)"?/i', $ctype, $cm ) && 'utf-8' !== strtolower( $cm[1] ) && function_exists( 'mb_convert_encoding' ) ) {
			$conv = @mb_convert_encoding( $body, 'UTF-8', $cm[1] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false !== $conv ) {
				$body = $conv;
			}
		}
		if ( str_starts_with( $ctype, 'text/html' ) ) {
			$body = preg_replace( '#<(br|/p|/div|/li)[^>]*>#i', "\n", $body );
			$body = html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' );
		}
		return trim( preg_replace( "/\n{3,}/", "\n\n", $body ) );
	}

	/** Remove the quoted earlier email so only the new reply is left. */
	private static function strip_quoted( $text ) {
		$lines = explode( "\n", $text );
		$out   = array();
		foreach ( $lines as $line ) {
			$t = trim( $line );
			if ( preg_match( '/^On .{5,200}wrote:$/i', $t ) || preg_match( '/^-{2,}\s*Original Message/i', $t ) || preg_match( '/^From: .+/i', $t ) && count( $out ) > 0 ) {
				break;
			}
			if ( str_starts_with( $t, '>' ) ) {
				continue;
			}
			$out[] = $line;
		}
		$res = trim( implode( "\n", $out ) );
		return '' === $res ? trim( $text ) : $res;
	}
}
