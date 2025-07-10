<?php
// Подключаем ядро Bitrix
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Подгружаем модуль iblock
if(!\Bitrix\Main\Loader::includeModule("iblock")) {
    die("Не удалось подключить модуль iblock");
}

// Включаем отображение ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ID инфоблока с товарами (тот же, что используется в newPhpCatalog.php)
$iblockId = 16;

// Объект для работы с элементами
$el = new CIBlockElement;

// Получаем все элементы инфоблока
$elements = CIBlockElement::GetList(
    [], 
    ["IBLOCK_ID" => $iblockId], 
    false, 
    false, 
    ["ID", "XML_ID", "NAME"]
);

$count = 0;

// Обходим элементы и очищаем свойство SPECIFICATIONS_TEXT
while ($element = $elements->Fetch()) {
    // Устанавливаем пустое значение для свойства SPECIFICATIONS_TEXT
    $propertyValues = [
        "SPECIFICATIONS_TEXT" => [
            "VALUE" => [
                "TEXT" => "",
                "TYPE" => "HTML"
            ]
        ]
    ];
    
    // Обновляем элемент
    if ($el->SetPropertyValuesEx($element["ID"], $iblockId, $propertyValues)) {
        $count++;
        echo "Очищено свойство SPECIFICATIONS_TEXT для товара: " . $element["NAME"] . " (ID: " . $element["ID"] . ")<br>";
    } else {
        echo "Ошибка при очистке свойства для товара: " . $element["NAME"] . " (ID: " . $element["ID"] . ")<br>";
    }
}

echo "<br>Всего обработано товаров: " . $count;
