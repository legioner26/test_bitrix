<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

/** @var array $arResult */
/** @var CBitrixComponent $component */
/** @var CMain $APPLICATION */

$APPLICATION->setTitle(Loc::getMessage('TITLE'));

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.filter',
    '',
    [
        'FILTER_ID' => $arResult['FILTER_ID'],
        'GRID_ID' => $arResult['GRID_ID'],
        'FILTER' => $arResult['FILTER'],
        'ENABLE_LIVE_SEARCH' => true,
        'ENABLE_LABEL' => true,
    ],
    $component,
    ['HIDE_ICONS' => 'Y']
);

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $arResult['GRID_ID'],
        'COLUMNS' => $arResult['COLUMNS'],
        'ROWS' => $arResult['ROWS'],
        'SHOW_ROW_CHECKBOXES' => false,
        'SHOW_GRID_SETTINGS_MENU' => true,
        'SHOW_PAGINATION' => true,
        'SHOW_SELECTED_COUNTER' => false,
        'SHOW_TOTAL_COUNTER' => true,
        'TOTAL_ROWS_COUNT' => $arResult['TOTAL_COUNT'],
        'ALLOW_COLUMNS_SORT' => true,
        'ALLOW_COLUMNS_RESIZE' => true,
        'ALLOW_HORIZONTAL_SCROLL' => true,
        'ALLOW_SORT' => true,
        'ALLOW_PIN_HEADER' => true,
        'AJAX_MODE' => 'Y',
        'AJAX_ID' => CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        'AJAX_OPTION_JUMP' => 'N',
        'AJAX_OPTION_STYLE' => 'N',
        'AJAX_OPTION_HISTORY' => 'N',
        'NAV_OBJECT' => $arResult['NAV_OBJECT'],
        'PAGE_SIZES' => [
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
            ['NAME' => '100', 'VALUE' => '100'],
        ],
    ],
    $component,
    ['HIDE_ICONS' => 'Y']
);
