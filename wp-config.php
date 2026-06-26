<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '>n@Bh~EcSuZHTx-CbgYirnJBXv|_=GeE.AzC62Ax^Mz[@og7jHyH;~s?wQWrxf9-' );
define( 'SECURE_AUTH_KEY',   'hmhr1!kUs1Gv%?Wp8c5c`+BZ29Ouy$VzK]+$ .?.ReVG4P(9%V?OKQfDOb,ck^M_' );
define( 'LOGGED_IN_KEY',     'awNPE)>%,9l14sbb7!4wi0GT4fn.FAzA.?14)7pG|-U|Ou_Q{ei`:|(PLw5p`3G)' );
define( 'NONCE_KEY',         '2{P,2]v(x2?zO uM1KJD=3d]4&yVpU0.N6;lgAUr7;4Q{~IF,N(lIW78ugOt+hB2' );
define( 'AUTH_SALT',         'GVIC[)%1, hH.;l{ZL1*^&8i0f_`Cn!etV*o5w:.i`HT*4YP2m|5H1Iz}g:#fhir' );
define( 'SECURE_AUTH_SALT',  '$MQb%R7bQLzn.=~&HSpaFfbsd/0kG4LOm`me}))fyrZw8nI?5~^HLJ>dLq4-6j7u' );
define( 'LOGGED_IN_SALT',    '??E3u=!lHGn)>@e%~uwXR+<9y9(S,jif/=P)46G52m@^xf29*Gqg_r w^YTIs<zt' );
define( 'NONCE_SALT',        ':iW+ocCo9R!q4lQ#B&AF*(6g!T]~HS=E#6BO +w3,IB8O89l]gYNXWOGr+0E-K*R' );
define( 'WP_CACHE_KEY_SALT', 'zptRoK5QFM8C9X;_[C+tzS+OlQRFA6Qeg_ffI/swHSHw+yq/Kz+q,6,giAK^@Fbu' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
/*if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}*/

// Chèn đoạn này vào đây (Dưới WP_DEBUG và TRÊN wp-settings.php)
define('WP_HOME', 'http://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST']);

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// Chỉ thực hiện xóa số cổng (Port) nếu đang chạy HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    if (isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
    }
}

define( 'WP_MEMORY_LIMIT', '256M' );
define( 'AUTOSAVE_INTERVAL', 86400 );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
