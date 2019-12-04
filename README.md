# Github OAuth

### Description

Allows Single Sign On into WordPress through [Github OAuth](https://developer.github.com/apps/building-oauth-apps/authorizing-oauth-apps/)
app with restriction by [organization](https://help.github.com/en/github/setting-up-and-managing-organizations-and-teams/about-organizations)
and [team](https://help.github.com/en/github/setting-up-and-managing-organizations-and-teams/about-teams).

### Install

- Preferable way is to use [Composer](https://getcomposer.org/):

    ````
    composer require innocode-digital/wp-github-oauth
    ````

    By default it will be installed as [Must Use Plugin](https://codex.wordpress.org/Must_Use_Plugins).
    But it's possible to control with `extra.installer-paths` in `composer.json`.

- Alternate way is to clone this repo to `wp-content/mu-plugins/` or `wp-content/plugins/`:

    ````
    cd wp-content/plugins/
    git clone git@github.com:innocode-digital/wp-github-oauth.git
    cd wp-github-oauth/
    composer install
    ````

If plugin was installed as regular plugin then activate **Github OAuth** from Plugins page 
or [WP-CLI](https://make.wordpress.org/cli/handbook/): `wp plugin activate wp-github-oauth`.

### Usage

Add required constants (usually to `wp-config.php`):

````
define( 'GITHUB_OAUTH_CLIENT_ID', '' );
define( 'GITHUB_OAUTH_CLIENT_SECRET', '' );
define( 'GITHUB_OAUTH_ORGANIZATION', 123456 ); // Organization ID
define( 'GITHUB_OAUTH_TEAMS2ROLES', [
    'super_admin'   => [
        123456, 234567,
    ], // Applicable to Multisite, will be the same as 'administrator' for single sites
    'administrator' => [
        345678,
    ],
    'editor'        => [
        456789, 567890, 654321,
    ],
] );
````
 
**IMPORTANT**: keys in `GITHUB_OAUTH_TEAMS2ROLES` are equal to roles (see
[Roles and Capabilities](https://wordpress.org/support/article/roles-and-capabilities/)) and should be
in descendant order by capability since first match will be used in case when user is in different teams.
    
### Documentation

By default auth URL is using `github` as an endpoint but it's possible to change with constant:

```
define( 'INNOCODE_GITHUB_OAUTH_ENDPOINT', '' );
```

---

It's possible to change [Github OAuth scope](https://developer.github.com/apps/building-oauth-apps/understanding-scopes-for-oauth-apps/):

````
add_filter( 'innocode_github_oauth_scope', function ( array $scope ) {
    return $scope; // Default is array containing 'user' and 'read:org'.
} );
````

---

It's possible to change place where endpoint should be added:

````
add_filter( 'innocode_github_oauth_endpoint_mask', function ( $mask, $endpoint ) {
    return $mask; // Default is EP_ROOT constant.
}, 10, 2 );
````
