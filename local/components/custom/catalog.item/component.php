<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Включаем оригинальный компонент
$APPLICATION->IncludeComponent(
    "dresscode:catalog.item",
    $arParams["TEMPLATE_THEME"],
    $arParams,
    $component,
    array("HIDE_ICONS" => "Y")
);
?>
