<?php

require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/header.php');

global $APPLICATION;

$APPLICATION->IncludeComponent(
    'custom:employee.labor.report',
    '',
    [
        'PAGE_SIZE' => 20,
    ]
);

require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/footer.php');
?>



