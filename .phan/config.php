<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

$cfg['suppress_issue_types'][] = 'PhanInvalidFQSENInCallable';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgument';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgumentProbablyReal';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchProperty';

return $cfg;
