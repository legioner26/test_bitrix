<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'PAGE_SIZE' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Количество записей на странице',
            'TYPE'    => 'STRING',
            'DEFAULT' => '20',
        ],
    ],
];
