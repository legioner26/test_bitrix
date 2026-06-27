<?php
$_SERVER['DOCUMENT_ROOT'] = '/home/bitrix/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('SITE_ID', 's1');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('socialnetwork');
$ref = new ReflectionMethod('CSocNetGroup', 'CreateGroup');
echo $ref->getFileName() . ':' . $ref->getStartLine() . "\n";
