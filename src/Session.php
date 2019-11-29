<?php

namespace Innocode\GithubOAuth;

use PasswordHash;

/**
 * Class Session
 * @package Innocode\GithubOAuth
 */
class Session
{
	/**
	 * @var string
	 */
	protected $_name;
	/**
	 * @var string
	 */
	protected $_cookie_path;

	/**
	 * Cookie constructor.
	 *
	 * @param string $name
	 * @param string $cookie_path
	 */
	public function __construct( string $name, string $cookie_path )
	{
		$this->_name = $name;
		$this->_cookie_path = $cookie_path;
	}

	/**
	 * @return string
	 */
	public function get_name() : string
	{
		return "innocode-github-oauth-$this->_name";
	}

	/**
	 * @return string
	 */
	public function get_cookie_path() : string
	{
		return $this->_cookie_path;
	}

	/**
	 * @param string $value
	 */
	public function set( string $value )
	{
		setcookie(
			$this->get_name(),
			$this->get_hasher()->HashPassword( $value ),
			0,
			$this->get_cookie_path(),
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}

	/**
	 * @return string
	 */
	public function get() : string
	{
		$name = $this->get_name();

		return isset( $_COOKIE[ $name ] ) ? (string) $_COOKIE[ $name ] : '';
	}

	public function delete()
	{
		setcookie(
			$this->get_name(),
			' ',
			time() - YEAR_IN_SECONDS,
			$this->get_cookie_path(),
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}

	/**
	 * @param string $value
	 *
	 * @return bool
	 */
	public function is( string $value ) : bool
	{
		return $this->get_hasher()->CheckPassword( $value, $this->get() );
	}

	/**
	 * @return PasswordHash
	 */
	public function get_hasher()
	{
		global $wp_hasher;

		if ( empty( $wp_hasher ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
			$wp_hasher = new PasswordHash( 8, true );
		}

		return $wp_hasher;
	}
}
