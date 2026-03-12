<?php
use Bitrix\Main\Loader;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_BUFFER_USED', true);
define('NO_AGENT_STATISTIC', true);
define('DisableEventsCheck', true);

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../');
if (!$_SERVER['DOCUMENT_ROOT'] || !is_dir($_SERVER['DOCUMENT_ROOT'])) {
    exit("Не удалось определить DOCUMENT_ROOT\n");
}

$docRoot = $_SERVER['DOCUMENT_ROOT'];
$prologPath = $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!file_exists($prologPath)) {
    exit("Не найден файл prolog_before.php по пути: {$prologPath}\n");
}

require $prologPath;

if (!Loader::includeModule('iblock')) {
    exit("Ошибка: не удалось подключить модуль iblock\n");
}

if (!Loader::includeModule('catalog')) {
    exit("Ошибка: не удалось подключить модуль catalog\n");
}

$offerIblockId = 17;
$propertyCode = 'XML_ID_TP';

$propertyRes = CIBlockProperty::GetList(
    [],
    [
        'IBLOCK_ID' => $offerIblockId,
        'CODE' => $propertyCode
    ]
);

if ($property = $propertyRes->Fetch()) {
    echo "Свойство {$propertyCode} уже существует в инфоблоке с ID {$offerIblockId}\n";
} else {
    echo "Свойство {$propertyCode} не найдено в инфоблоке с ID {$offerIblockId}. Создаем...\n";
}

$el = new CIBlockElement();

$res = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    [
        'IBLOCK_ID' => $offerIblockId,
        '!CODE' => false
    ],
    false,
    ['nTopCount' => 5], // Выбираем только первые 5 элементов для примера
    ['ID', 'IBLOCK_ID', 'NAME', 'CODE']
);

while ($item = $res->Fetch()) {
    $elementId = (int)$item['ID'];
    $codeValue = trim((string)$item['CODE']);

    if ($codeValue === '') {
        echo "Элемент с ID {$elementId} имеет пустой CODE. Пропускаем...\n";
        continue;
    }

    
    CIBlockElement::SetPropertyValuesEx(
        $elementId,
        $offerIblockId,
        [
            $propertyCode => $codeValue
        ]
    );

    
    $checkRes = CIBlockElement::GetList(
        [],
        [
            'ID' => $elementId,
            'IBLOCK_ID' => $offerIblockId
        ],
        false,
        false,
        ['ID', 'IBLOCK_ID']
    );

    if ($checkElement = $checkRes->GetNextElement()) {
        $props = $checkElement->GetProperties();
        $xmlIdTpValue = trim((string)($props[$propertyCode]['VALUE'] ?? ''));

        if ($xmlIdTpValue !== $codeValue) {
            echo "ID {$elementId}: XML_ID_TP не записалось, CODE не очищать\n";
            continue;
        }
    } else {
        echo "ID {$elementId}: не удалось повторно получить элемент, CODE не очищать\n";
        continue;
    }

    
    $result = $el->Update($elementId, [
        'CODE' => ''
    ]);

    if ($result) {
        echo "ID {$elementId}: XML_ID_TP заполнено, CODE очищен\n";
    } else {
        echo "ID {$elementId}: ошибка очистки CODE - {$el->LAST_ERROR}\n";
    }
}




