<?php

namespace Innocode\GithubOAuth;

use Github\Client;
use League\OAuth2\Client\Provider\Github as Provider;
use WP_User;

/**
 * Class Plugin
 * @package Innocode\GithubOAuth
 */
final class Plugin
{
	const IDENTITY_PROVIDER = 'github';
	const ROUTES = [
		'', // index
		'auth',
		'verify',
	];

	/**
	 * @var Controller
	 */
	private $_controller;
	/**
	 * @var Router
	 */
	private $_router;
	/**
	 * @var Provider
	 */
	private $_provider;
	/**
	 * @var Client
	 */
	private $_client;
	/**
	 * @var Session
	 */
	private $_session;

	public function __construct()
	{
		$this->_init_controller();
		$this->_init_router();
		$this->_init_provider();
		$this->_init_client();
		$this->_init_session();
	}

	public function run()
	{
		add_action( 'init', [ $this, 'add_rewrite_endpoints' ] );
		add_action( 'template_redirect', [ $this->get_router(), 'handle_request' ] );
		add_filter( 'allow_password_reset', [ $this, 'allow_password_reset' ], 10, 2 );
		add_filter( 'show_password_fields', [ $this, 'allow_password_reset' ], 10, 2 );
	}

	/**
	 * @return Controller
	 */
	public function get_controller() : Controller
	{
		return $this->_controller;
	}

	/**
	 * @return Router
	 */
	public function get_router() : Router
	{
		return $this->_router;
	}

	/**
	 * @return Provider
	 */
	public function get_provider() : Provider
	{
		return $this->_provider;
	}

	/**
	 * @return Client
	 */
	public function get_client() : Client
	{
		return $this->_client;
	}

	/**
	 * @return Session
	 */
	public function get_session() : Session
	{
		return $this->_session;
	}

	/**
	 * @return array
	 */
	public function get_scope() : array
	{
		return apply_filters(
			'innocode_github_oauth_scope', [ 'user', 'read:org' ]
		);
	}

	public function add_rewrite_endpoints()
	{
		$endpoint = $this->get_router()->get_endpoint();

		add_rewrite_endpoint(
			$endpoint,
			apply_filters(
				'innocode_github_oauth_endpoint_mask', EP_ROOT, $endpoint
			)
		);
	}

	/**
	 * @param bool    $allow
	 * @param WP_User $user
	 *
	 * @return bool
	 */
	public function allow_password_reset( bool $allow, WP_User $user ) : bool
	{
		return ! $this->is_identified( $user->ID ) ? $allow : false;
	}

	/**
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function is_identified( int $user_id ) : bool
	{
		return get_user_meta(
			$user_id,
			'oauth_identity_provider',
			true
		) === static::IDENTITY_PROVIDER;
	}

	private function _init_provider()
	{
		$this->_provider = new Provider( [
			'clientId'     => defined( 'GITHUB_OAUTH_CLIENT_ID' )
				? GITHUB_OAUTH_CLIENT_ID
				: '',
			'clientSecret' => defined( 'GITHUB_OAUTH_CLIENT_SECRET' )
				? GITHUB_OAUTH_CLIENT_SECRET
				: '',
			'redirectUri'  => $this->get_router()->url( 'auth' ),
		] );
	}

	private function _init_client()
	{
		$this->_client = new Client();
	}

	private function _init_session()
	{
		$this->_session = new Session( 'state', $this->get_router()->path() );
	}

	private function _init_controller()
	{
		$this->_controller = new Controller();
	}

	private function _init_router()
	{
		$this->_router = new Router(
			defined( 'INNOCODE_GITHUB_OAUTH_ENDPOINT' )
				? INNOCODE_GITHUB_OAUTH_ENDPOINT
				: 'github'
		);

		foreach ( static::ROUTES as $route ) {
			$this->_router->add_route( $route, function () use ( $route ) {
				if ( ! $route ) {
					$route = 'index';
				}

				$this->get_controller()->$route( $this );
			} );
		}
	}
}
