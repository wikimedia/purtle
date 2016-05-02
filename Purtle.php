<?php

if ( defined( 'PURTLE_VERSION' ) ) {
	// Do not initialize more than once.
	return 1;
}

define( 'PURTLE_VERSION', '1.0.3' );

// Include the composer autoloader if it is present.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( defined( 'MEDIAWIKI' ) ) {
	call_user_func( function() {
		require_once __DIR__ . '/init.mw.php';
	} );
}
