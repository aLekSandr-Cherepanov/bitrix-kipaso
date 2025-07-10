<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "PARAMETERS" => [
        "IBLOCK_ID" => [
            "PARENT" => "BASE",
            "NAME" => "ID инфоблока с товарами",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "CACHE_TIME" => ["DEFAULT" => 3600],
    ]
];
