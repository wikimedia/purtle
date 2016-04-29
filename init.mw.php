<?php

// Integration with MediaWiki extension registration system.

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

$GLOBALS['wgExtensionCredits']['purtle'][] = array(
	'path' => __FILE__,
	'name' => 'Purtle',
	'version' => PURTLE_VERSION,
	'author' => array(
		'Daniel Kinzler',
		'Stas Malyshev',
		'Thiemo Mättig'
	),
	'url' => 'https://mediawiki.org/wiki/Purtle',
	'description' => 'Fast streaming RDF serializer',
	'license-name' => 'GPL-2.0+'
);


$GLOBALS['wgHooks']['UnitTestsList'][] = function( array &$paths ) {
	$paths[] = __DIR__ . '/tests/phpunit';
};
