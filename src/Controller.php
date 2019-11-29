<?php

namespace Innocode\GithubOAuth;

use Github\Client;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use WP_Http;

/**
 * Class Controller
 * @package Innocode\GithubOAuth
 */
final class Controller
{
	/**
	 * @param Plugin $plugin
	 */
	public function index( Plugin $plugin )
	{
		wp_safe_redirect( $plugin->get_router()->path( 'auth' ) );
	}

	/**
	 * @param Plugin $plugin
	 */
	public function auth( Plugin $plugin )
	{
		$router = $plugin->get_router();
		$provider = $plugin->get_provider();
		$session = $plugin->get_session();

		$state = wp_generate_password( 20, false );
		$session->set( $state );

		wp_redirect( $provider->getAuthorizationUrl( [
			'scope'        => $plugin->get_scope(),
			'state'        => $state,
			'redirect_uri' => $router->url( 'verify' ),
		] ) );
	}

	/**
	 * @param Plugin $plugin
	 */
	public function verify( Plugin $plugin )
	{
		if ( is_user_logged_in() ) {
			$this->done();
			exit;
		}

		$provider = $plugin->get_provider();
		$session = $plugin->get_session();

		$code = isset( $_GET['code'] ) ? $_GET['code'] : '';

		if ( ! $code ) {
			$this->error(
				$plugin,
				__( 'Invalid code.', 'innocode-github-oauth' )
			);
		}

		$state = isset( $_GET['state'] ) ? $_GET['state'] : '';

		if ( ! $state || ! $session->is( $state ) ) {
			$this->error(
				$plugin,
				__( 'Invalid state.', 'innocode-github-oauth' )
			);
		}

		try {
			$token = $provider->getAccessToken(
				'authorization_code',
				[
					'code' => $_GET['code'],
				]
			);
		} catch ( IdentityProviderException $exception ) {
			$this->error(
				$plugin,
				$exception->getMessage(),
				$exception->getCode()
			);
		}

		$client = $plugin->get_client();
		// $token is always defined because wp_die(); calls die();
		$client->authenticate( $token->getToken(), Client::AUTH_HTTP_TOKEN );

		foreach ( [
			'GITHUB_OAUTH_ORGANIZATION',
			'GITHUB_OAUTH_TEAMS2ROLES',
		] as $constant ) {
			if ( defined( $constant ) ) {
				continue;
			}

			trigger_error(
				sprintf(
					'Missing required constant %s',
					$constant
				),
				E_USER_ERROR
			);
		}

		$user = $client->currentUser();
		$teams = array_column(
			array_filter( $user->teams(), function ( array $team ) {
				return defined( 'GITHUB_OAUTH_ORGANIZATION' ) &&
				       $team['organization']['id'] == GITHUB_OAUTH_ORGANIZATION;
			} ),
			'id'
		);

		$userdata = [];
		$profile = $user->show();

		if ( defined( 'GITHUB_OAUTH_TEAMS2ROLES' ) ) {
			foreach ( GITHUB_OAUTH_TEAMS2ROLES as $role => $role_teams ) {
				if ( count( array_intersect( $role_teams, $teams ) ) > 0 ) {
					$userdata['role'] = $role;
					break;
				}
			}
		}

		if ( ! isset( $userdata['role'] ) ) {
			trigger_error(
				sprintf(
					'Role was not found for %s',
					$profile['login']
				),
				E_USER_NOTICE
			);
			$this->done();
			exit;
		}

		foreach ( $user->emails()->all() as $email ) {
			if ( $email['primary'] && $email['verified'] ) {
				$userdata['user_email'] = $email['email'];
				break;
			}
		}

		if ( ! isset( $userdata['user_email'] ) ) {
			// Probably should never be triggered
			trigger_error(
				sprintf(
					'Invalid %s primary email address on GitHub',
					$profile['login']
				),
				E_USER_NOTICE
			);
			$this->done();
			exit;
		}

		$userdata['user_pass'] = wp_generate_password( 12, false );
		$user_id = email_exists( $userdata['user_email'] );

		if ( $user_id ) {
			wp_set_password( $userdata['user_pass'], $user_id );
			$this->signon( $plugin, $user_id );
			$this->done();
			exit;
		}

		$is_super = $userdata['role'] == 'super_admin';

		if ( $is_super ) {
			$userdata['role'] = 'administrator';
		}

		$userdata['user_login'] = $profile['login'];

		while ( $user_id = username_exists( $userdata['user_login'] ) ) {
			$userdata['user_login'] = uniqid( "{$profile['login']}-" );
		}

		if ( isset( $profile['name'] ) ) {
			$name_parts = explode( ' ', $profile['name'] );

			if ( count( $name_parts ) > 1 ) {
				$userdata['last_name'] = array_pop( $name_parts );
			}

			$userdata['first_name'] = $name_parts[0];
		}

		$user_id = wp_insert_user( wp_slash( $userdata ) );

		if ( is_wp_error( $user_id ) ) {
			$this->error(
				$plugin,
				$user_id->get_error_message()
			);
		}

		if ( is_multisite() ) {
			if ( $is_super ) {
				grant_super_admin( $user_id );
			} elseif ( ! is_user_member_of_blog( $user_id ) ) {
				add_user_to_blog(
					get_current_blog_id(),
					$user_id,
					$userdata['role']
				);
			}
		}

		$this->signon( $plugin, $user_id );
		$this->done();
	}

	/**
	 * @param Plugin $plugin
	 * @param string $message
	 * @param int    $response
	 */
	public function error( Plugin $plugin, string $message, int $response = WP_Http::BAD_REQUEST )
	{
		wp_die(
			'<h1>' . __( 'Something went wrong.' ) . '</h1>' . $message,
			'',
			[
				'response'  => $response,
				'link_url'  => $plugin->get_router()->url( 'auth' ),
				'link_text' => __( 'Try Again' ),
			]
		);
	}

	public function done()
	{
		if ( ! is_user_logged_in() ) {
			$this->not_found();
			exit;
		}

		$redirect_to = ! empty( $_REQUEST['redirect_to'] )
			? $_REQUEST['redirect_to']
			: '';

		if ( $redirect_to ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		$user = wp_get_current_user();

		switch ( true ) {
			case is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ):
				$redirect_to = user_admin_url();

				break;
			case is_multisite() && ! $user->has_cap( 'read' ):
				$redirect_to = get_dashboard_url( $user->ID );

				break;
			case ! $user->has_cap( 'edit_posts' ):
				$redirect_to = $user->has_cap( 'read' )
					? admin_url( 'profile.php' )
					: home_url();

				break;
			default:
				$redirect_to = admin_url();

				break;
		}

		wp_redirect( $redirect_to );
	}

	public function not_found()
	{
		global $wp_query;

		$wp_query->set_404();
		status_header( WP_Http::NOT_FOUND );
		nocache_headers();
	}

	/**
	 * @param Plugin $plugin
	 * @param int    $user_id
	 */
	public function signon( Plugin $plugin, int $user_id )
	{
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		update_user_meta( $user_id, 'oauth_identity_provider', $plugin::IDENTITY_PROVIDER );
		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID, true );
	}
}
