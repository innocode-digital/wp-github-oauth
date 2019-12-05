<?php
/**
 * Plugin Name: Github OAuth
 * Description: Allows Single Sign On into WordPress through Github OAuth app with restriction by organization and team.
 * Version: 1.1.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Tested up to: 5.3.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

use Innocode\GithubOAuth;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if ( defined( 'GITHUB_OAUTH_CLIENT_ID' ) && defined( 'GITHUB_OAUTH_CLIENT_SECRET' ) ) {
	$GLOBALS['innocode_github_oauth'] = new GithubOAuth\Plugin();
	$GLOBALS['innocode_github_oauth']->run();
}

if ( ! function_exists( 'innocode_github_oauth' ) ) {
	function innocode_github_oauth() {
		/**
		 * @var GithubOAuth\Plugin $innocode_github_oauth
		 */
		global $innocode_github_oauth;

		if ( is_null( $innocode_github_oauth ) ) {
			trigger_error(
				'Missing required constants GITHUB_OAUTH_CLIENT_ID and GITHUB_OAUTH_CLIENT_SECRET.',
				E_USER_ERROR
			);
		}

		return $innocode_github_oauth;
	}
}
