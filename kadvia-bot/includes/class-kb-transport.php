<?php
/**
 * PSR-18 HTTP client that sends the Anthropic SDK's requests through
 * WordPress's own HTTP API (wp_remote_request). This avoids bundling Guzzle,
 * which often clashes with other plugins on shared hosting.
 *
 * @package kadvia-bot
 */

defined( 'ABSPATH' ) || exit;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class KB_Network_Exception extends \RuntimeException implements NetworkExceptionInterface {

	private RequestInterface $request;

	public function __construct( string $message, RequestInterface $request ) {
		parent::__construct( $message );
		$this->request = $request;
	}

	public function getRequest(): RequestInterface {
		return $this->request;
	}
}

class KB_Transport implements ClientInterface {

	private Psr17Factory $factory;

	public function __construct( ?Psr17Factory $factory = null ) {
		$this->factory = $factory ?? new Psr17Factory();
	}

	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$headers = array();
		foreach ( $request->getHeaders() as $name => $values ) {
			$headers[ $name ] = implode( ', ', $values );
		}

		$res = wp_remote_request(
			(string) $request->getUri(),
			array(
				'method'      => $request->getMethod(),
				'headers'     => $headers,
				'body'        => (string) $request->getBody(),
				'timeout'     => 180,
				'redirection' => 0,
				'user-agent'  => $headers['User-Agent'] ?? 'kadvia-bot/' . KB_VERSION,
			)
		);

		if ( is_wp_error( $res ) ) {
			throw new KB_Network_Exception( $res->get_error_message(), $request );
		}

		$response = $this->factory->createResponse( (int) wp_remote_retrieve_response_code( $res ) );
		foreach ( wp_remote_retrieve_headers( $res )->getAll() as $name => $value ) {
			// WordPress has already decoded the body, so these no longer apply.
			if ( in_array( strtolower( $name ), array( 'content-encoding', 'content-length', 'transfer-encoding' ), true ) ) {
				continue;
			}
			$response = $response->withHeader( $name, $value );
		}
		return $response->withBody( $this->factory->createStream( (string) wp_remote_retrieve_body( $res ) ) );
	}
}

/**
 * Lets the SDK's HTTP discovery find KB_Transport as the PSR-18 client.
 * Registered only right before the bot creates its Claude client.
 */
class KB_Discovery_Strategy implements \Http\Discovery\Strategy\DiscoveryStrategy {

	public static function getCandidates( $type ) {
		if ( ClientInterface::class === $type ) {
			return array(
				array(
					'class'     => KB_Transport::class,
					'condition' => KB_Transport::class,
				),
			);
		}
		return array();
	}
}
