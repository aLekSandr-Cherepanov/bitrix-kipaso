<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
    "NAME" => "Аккордеон категорий каталога",
    "DESCRIPTION" => "Выводит категории каталога в виде аккордеона с брендами и подкатегориями",
    "ICON" => "/images/icon.gif",
    "CACHE_PATH" => "Y",
    "SORT" => 30,
    "PATH" => array(
        "ID" => "custom_components",
        "NAME" => "Пользовательские компоненты",
    ),
);
