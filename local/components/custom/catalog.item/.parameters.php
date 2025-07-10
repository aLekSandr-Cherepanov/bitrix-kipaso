<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Подключим параметры от оригинального компонента
$arComponentParameters = include $_SERVER["DOCUMENT_ROOT"].'/bitrix/components/dresscode/catalog.item/.parameters.php';

// Можно добавить дополнительные параметры при необходимости
$arComponentParameters["PARAMETERS"]["CUSTOM_SHOW_SPECIFICATIONS"] = array(
    "PARENT" => "BASE",
    "NAME" => "Показывать спецификации только в разделе характеристик",
    "TYPE" => "CHECKBOX",
    "DEFAULT" => "Y"
);

return $arComponentParameters;
?>
