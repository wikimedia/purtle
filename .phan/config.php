<?php
declare( strict_types = 1 );

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgument';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgumentProbablyReal';

return $cfg;
