<?php

namespace Innocode\GithubOAuth;

/**
 * Class Query
 * @package Innocode\GithubOAuth
 */
final class Router
{
	/**
	 * @var string
	 */
	private $_endpoint;
	/**
	 * @var array
	 */
	private $_routes = [];

	/**
	 * Query constructor.
	 * @param string $endpoint
	 */
	public function __construct( string $endpoint )
	{
		$this->_endpoint = $endpoint;
	}

	/**
	 * @return string
	 */
	public function get_endpoint() : string
	{
		return $this->_endpoint;
	}

	/**
	 * @return array
	 */
	public function get_routes() : array
	{
		return $this->_routes;
	}

	/**
	 * @param string   $uri
	 * @param callable $callback
	 */
	public function add_route( string $uri, callable $callback )
	{
		$this->_routes[ $uri ] = $callback;
	}

	/**
	 * @param string|null $uri
	 * @return string
	 */
	public function path( string $uri = null ) : string
	{
		$path = "/{$this->get_endpoint()}/";

		if ( ! is_null( $uri ) ) {
			$path .= "$uri/";
		}

		return $path;
	}

	/**
	 * @param string $uri
	 * @return string
	 */
	public function url( string $uri ) : string
	{
		return home_url( $this->path( $uri ) );
	}

	public function handle_request()
	{
		$endpoint = $this->get_endpoint();
		$uri = get_query_var( $endpoint, null );

		if ( is_null( $uri ) ) {
			return;
		}

		$routes = $this->get_routes();

		if ( isset( $routes[ $uri ] ) ) {
			$routes[ $uri ]();

			exit;
		}
	}
}