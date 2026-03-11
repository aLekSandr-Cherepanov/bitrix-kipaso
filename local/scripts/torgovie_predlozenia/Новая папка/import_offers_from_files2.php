<?php
// Столбец - А = Текущая дата
// Стоблец- B = Артикул как раз для сверки izd code 
// Дальше идут столбцы которые нас совершенно не интересует просто показываю порядок общий - 
// А - B - C - D - E - F - G - H - I - J - K - L - M - N - O - P - Q - R
// Как раз стоблец R - отвечает за наименование которое мы перезаписываем если оно сопоставляется с артикулом на сайте, если в столбце R пусто то мы вообще не используем данные из этой таблицы
// $byArticle = [
//     'trm500' => [
//         'product_name' => 'Терморегулятор ТРМ500',
//         'offers' => [
//             [
//                 'izd_code' => '128646',
//                 'mod_name' => 'ТРМ500-Щ2.5А',
//                 'name_long' => 'ТРМ500-Щ2.5А', // после обработки может стать "ТРМ500-Щ2.5А Контроллер для упр-я..."
//                 'from_xlsx' => true // или false
//             ],
//             [
//                 'izd_code' => '128647',
//                 'mod_name' => 'ТРМ500-Щ4.5А',
//                 'name_long' => 'ТРМ500-Щ4.5А',
//                 'from_xlsx' => false
//             ]
//         ]
//     ],
//     'uzd1' => [
//         'product_name' => 'Устройство защиты двигателя УЗД1',
//         'offers' => [
//             [
//                 'izd_code' => '119480',
//                 'mod_name' => 'УЗД1-RS',
//                 'name_long' => 'УЗД1-RS Контроллер для упр-я насосами, алгоритм работы 01-09...',
//                 'from_xlsx' => true
//             ]
//         ]
//     ]
// ];
// Столбец - А = Текущая дата
// Стоблец- B = Артикул как раз для сверки izd code 
// Дальше идут столбцы которые нас совершенно не интересует просто показываю порядок общий - 
// А - B - C - D - E - F - G - H - I - J - K - L - M - N - O - P - Q - R
// Как раз стоблец R - отвечает за наименование которое мы перезаписываем если оно сопоставляется с артикулом на сайте, если в столбце R пусто то мы вообще не используем данные из этой таблицы
//  Строка 4:
//   [0] (A): "22.06.2025"
//   [1] (B): "82508"
//   [2] (C): "СУНА-121.220.00.00"
//   [3] (D): ""
//   [4] (E): "Система управления насосами автоматическая СУНА-121.220.00.00"
//   [5] (F): "12265"
//   [6] (G): "14718"
//   [7] (H): "Стандартный"
//   [8] (I): "СУНА"
//   [9] (J): "2"
//   [10] (K): "13"
//   [11] (L): "0"
//   [12] (M): "1"
//   [13] (N): "8537109100"
//   [14] (O): "Производимый"
//   [15] (P): "120"
//   [16] (Q): ""
//   [17] (R): "Контроллер для упр-я насосами, алгоритм работы 01-09  ЖК дисплей, DIN-рейка 123*90*58 IP20, 8 дискр. входов, 4 аналог. входов (4...20мА/0…4 кОм), 8 вых. реле (5А 250В), встр. БП 24В DC(50mA), пит. 90…264VAC, T󠇔°окр -20…+55󠇔°C, miniUSB, RS-485"
//   [18] (S): ""






error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (php_sapi_name() !== 'cli') {
    die('Этот скрипт можно запускать только через терминал (CLI).');
}

$_SERVER['DOCUMENT_ROOT'] = '/home/i/itkipae3/test2.owen.kipaso.ru/public_html';
define("B_PROLOG_INCLUDED", true);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
Loader::includeModule('iblock');
Loader::includeModule('catalog');

ob_end_clean();
define('BX_BUFFER_USED', false);

ini_set('memory_limit', '1G');
set_time_limit(0);

// === Константы ===
$PRODUCT_IBLOCK_ID = 16;
$OFFERS_IBLOCK_ID = 17;
$ARTICLE_PROPERTY_CODE = 'CML2_ARTICLE';
$LINK_PROP_ID = 'CML2_LINK';
$DRY_RUN = false;

// === Пути ===
$xmlPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/catalogOven.xml';
$xlsxPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/korotkoeopicanietovarov.xlsx';
$logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/import_log.txt';

// === Логирование с принудительной записью ===
function logm($msg) {
    global $logFile;
    $line = date('Y-m-d H:i:s') . ' | ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
    flush(); // немедленный вывод в консоль
}

logm("=== ЗАПУСК ИМПОРТА ===");

// === 1. Парсинг XML ===
logm("Чтение XML-файла...");
$xml = simplexml_load_file($xmlPath);
if (!$xml) {
    logm('[ERR] Не удалось загрузить XML: ' . $xmlPath);
    exit(1);
}

$byArticle = [];
$allIzdsFromXml = [];

foreach ($xml->xpath('//product') as $product) {
    $article = (string)$product->id;
    $productName = (string)$product->name;

    $offers = [];
    if (!empty($product->prices)) {
        foreach ($product->prices->price as $priceItem) {
            $izd = trim((string)$priceItem->izd_code);
            if ($izd === '') continue;
            $mod_name = (string)$priceItem->name;
            $offers[] = [
                'izd_code' => $izd,
                'mod_name' => $mod_name,
                'product_name' => $productName
            ];
            $allIzdsFromXml[$izd] = true;
        }
    }

    if (count($offers) === 0) continue;
    elseif (count($offers) === 1) {
        $byArticle[$article] = ['type' => 'simple', 'offer' => $offers[0], 'product_name' => $productName];
    } else {
        $byArticle[$article] = ['type' => 'with_offers', 'offers' => $offers, 'product_name' => $productName];
    }
}
logm('[OK] Всего товаров: ' . count($byArticle));

// === 2. Парсинг XLSX ===
logm("Чтение XLSX-файла...");
$rows = readXlsxRows($xlsxPath);
$overrides = [];
foreach ($rows as $index => $row) {
    if ($index < 4) continue;
    $izd = isset($row[1]) ? trim($row[1]) : '';
    $desc = isset($row[17]) ? trim($row[17]) : '';
    if ($izd && $desc) {
        $overrides[$izd] = $desc;
    }
}
logm('[XLSX] Загружено описаний: ' . count($overrides));

// === 3. Кэширование существующих товаров ===
logm("Кэширование существующих товаров...");
$existingProducts = [];
$res = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $PRODUCT_IBLOCK_ID, 'PROPERTY_' . $ARTICLE_PROPERTY_CODE => array_keys($byArticle)],
    false, false, ['ID', 'PROPERTY_' . $ARTICLE_PROPERTY_CODE]
);
while ($item = $res->Fetch()) {
    $art = $item['PROPERTY_' . $ARTICLE_PROPERTY_CODE . '_VALUE'];
    $existingProducts[$art] = (int)$item['ID'];
}
logm("Найдено существующих товаров: " . count($existingProducts));

// === 4. Кэширование существующих ТП ===
logm("Кэширование существующих торговых предложений...");
$existingOffers = [];
if (!empty($allIzdsFromXml)) {
    $res = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $OFFERS_IBLOCK_ID, 'XML_ID' => array_keys($allIzdsFromXml)],
        false, false, ['ID', 'XML_ID', 'PROPERTY_' . $LINK_PROP_ID]
    );
    while ($item = $res->Fetch()) {
        $izd = $item['XML_ID'];
        $pid = (int)$item['PROPERTY_' . $LINK_PROP_ID . '_VALUE'];
        $existingOffers[$izd][$pid] = (int)$item['ID'];
    }
}
logm("Найдено существующих ТП: " . count($existingOffers));

// === 5. Основной цикл импорта ===
$created = $updated = $deletedOrphan = 0;
$total = count($byArticle);
$counter = 0;

foreach ($byArticle as $article => $data) {
    $counter++;
    if ($counter % 10 === 0 || $counter === 1) {
        logm("[PROGRESS] Обработано {$counter}/{$total} товаров");
    }

    // === Создание/поиск товара ===
    $productId = $existingProducts[$article] ?? null;
    if (!$productId) {
        if (!$DRY_RUN) {
            $el = new CIBlockElement();
            $productId = (int)$el->Add([
                'IBLOCK_ID' => $PRODUCT_IBLOCK_ID,
                'ACTIVE' => 'Y',
                'NAME' => $data['product_name'],
                'CODE' => $article,
                'XML_ID' => $article,
                'PROPERTY_VALUES' => [$ARTICLE_PROPERTY_CODE => $article],
                'CATALOG' => ['TYPE' => 'S']
            ], false, false);
        }
        $productId = $productId ?: -1;
        if ($productId > 0) {
            $existingProducts[$article] = $productId; // обновляем кэш
        }
    }

    if ($data['type'] === 'simple') {
        $offer = $data['offer'];
        $izd = $offer['izd_code'];
        $name_long = isset($overrides[$izd])
            ? $offer['mod_name'] . ' ' . $overrides[$izd]
            : $offer['mod_name'] . ' ' . $data['product_name'];

        if (!$DRY_RUN && $productId > 0) {
            $el = new CIBlockElement();
            $el->Update($productId, [
                'NAME' => $name_long,
                'CODE' => $izd,
                'XML_ID' => $izd
            ], false, false, true);
        }
        $updated++;

    } else {
        $knownOfferIds = [];
        foreach ($data['offers'] as $offer) {
            $izd = $offer['izd_code'];
            $from_xlsx = isset($overrides[$izd]);
            $name_long = $from_xlsx
                ? $offer['mod_name'] . ' ' . $overrides[$izd]
                : $offer['mod_name'] . ' ' . $data['product_name'];
            $sort = $from_xlsx ? 100 : 200;

            $offerId = $existingOffers[$izd][$productId] ?? null;
            $fields = [
                'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
                'ACTIVE' => 'Y',
                'NAME' => $offer['mod_name'],
                'CODE' => $izd,
                'XML_ID' => $izd,
                'SORT' => $sort,
                'PROPERTY_VALUES' => [
                    $LINK_PROP_ID => $productId,
                    'modific' => $name_long,
                    'IZD' => $izd
                ],
                'CATALOG' => ['TYPE' => 'S']
            ];

            if ($offerId) {
                if (!$DRY_RUN) {
                    $el = new CIBlockElement();
                    $el->Update($offerId, $fields, false, false, true);
                }
                $knownOfferIds[] = $offerId;
                $updated++;
            } else {
                if (!$DRY_RUN) {
                    $el = new CIBlockElement();
                    $newId = (int)$el->Add($fields, false, false);
                    if ($newId > 0) {
                        $knownOfferIds[] = $newId;
                        $created++;
                    }
                } else {
                    $created++;
                }
            }
        }

        // Деактивация сверх 30
        if (count($knownOfferIds) > 30) {
            $toDeactivate = array_slice($knownOfferIds, 30);
            foreach ($toDeactivate as $id) {
                if (!$DRY_RUN) {
                    $el = new CIBlockElement();
                    $el->Update($id, ['ACTIVE' => 'N'], false, false, true);
                }
            }
        }
    }
}

// === 6. Удаление "мёртвых" ТП ===
logm("Удаление устаревших ТП...");
if (!empty($allIzdsFromXml)) {
    $res = CIBlockElement::GetList([], ['IBLOCK_ID' => $OFFERS_IBLOCK_ID], false, false, ['ID', 'XML_ID']);
    while ($item = $res->Fetch()) {
        if (!isset($allIzdsFromXml[$item['XML_ID']])) {
            if (!$DRY_RUN) {
                CIBlockElement::Delete((int)$item['ID']);
            }
            $deletedOrphan++;
        }
    }
}

// === 7. Очистка кэша ===
if (!$DRY_RUN) {
    logm("Очистка кэша...");
    BXClearCache(true, "/");
    CIBlock::cleanCache($PRODUCT_IBLOCK_ID);
    CIBlock::cleanCache($OFFERS_IBLOCK_ID);
    CCatalog::ClearCache();
}

// === 8. Итоги ===
logm("--- ИТОГИ ---");
logm("Создано ТП: {$created}");
logm("Обновлено: {$updated}");
logm("Удалено устаревших ТП: {$deletedOrphan}");
logm($DRY_RUN ? '[DRY-RUN] Без изменений' : '[SUCCESS] Изменения применены');
logm("=== ИМПОРТ ЗАВЕРШЁН ===");
exit(0);

// === Вспомогательные функции ===
function readXlsxRows($path) {
    $rows = [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;

    $sharedStrings = [];
    if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $xml = simplexml_load_string($zip->getFromIndex($idx));
        if ($xml) {
            foreach ($xml->si as $si) {
                $sharedStrings[] = isset($si->t) ? (string)$si->t : implode('', array_map(function($r) { return (string)$r->t; }, $si->r));
            }
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    for ($i = 1; $i <= 10 && $zip->locateName($sheetPath) === false; $i++) {
        $sheetPath = 'xl/worksheets/sheet' . $i . '.xml';
    }

    if (($xmlStr = $zip->getFromName($sheetPath)) === false) {
        $zip->close();
        return $rows;
    }

    $sheet = simplexml_load_string($xmlStr);
    if (!$sheet) {
        $zip->close();
        return $rows;
    }

    $excelColToIndex = function($letters) {
        $letters = strtoupper($letters);
        $num = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $num = $num * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $num - 1;
    };

    foreach ($sheet->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $c) {
            $colLetters = preg_replace('/\d+/', '', (string)$c['r']);
            $colIndex = $excelColToIndex($colLetters);
            $t = (string)$c['t'];
            $val = '';
            if ($t === 's') {
                $val = $sharedStrings[(int)$c->v] ?? '';
            } elseif ($t === 'inlineStr') {
                $val = (string)($c->is->t ?? '');
            } else {
                $val = (string)($c->v ?? '');
            }
            while (count($row) <= $colIndex) $row[] = '';
            $row[$colIndex] = $val;
        }
        $rows[] = $row;
    }
    $zip->close();
    return $rows;
}

// Это рабочий код без оптимизации и без выключения > 30 торговых предложений 
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);

// // === Проверка запуска только из терминала (CLI) ===
// if (php_sapi_name() !== 'cli') {
//     die('Этот скрипт можно запускать только через терминал (CLI).');
// }

// // === Настройка окружения Bitrix для CLI ===
// $_SERVER['DOCUMENT_ROOT'] = '/home/i/itkipae3/test2.owen.kipaso.ru/public_html';
// define("B_PROLOG_INCLUDED", true);
// require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// use Bitrix\Main\Loader;
// Loader::includeModule('iblock');
// Loader::includeModule('catalog');

// ob_end_clean();
// define('BX_BUFFER_USED', false);

// ini_set('memory_limit', '1G');
// set_time_limit(0);

// // === Константы инфоблоков ===
// $PRODUCT_IBLOCK_ID = 16;
// $OFFERS_IBLOCK_ID = 17;
// $ARTICLE_PROPERTY_CODE = 'CML2_ARTICLE';
// $LINK_PROP_ID = 'CML2_LINK';
// $DRY_RUN = false;

// // === Пути к файлам ===
// $xmlPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/catalogOven.xml';
// $xlsxPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/korotkoeopicanietovarov.xlsx';

// // === Функция логирования ===
// function logm($msg) {
//     $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/import_log.txt';
//     $handle = fopen($logFile, 'a');
//     if ($handle) {
//         fwrite($handle, date('Y-m-d H:i:s') . ' | ' . $msg . PHP_EOL);
//         fclose($handle);
//     }
//     echo $msg . "\n";
// }
// function flushLogAndExit($code) {
//     exit($code);
// }

// // === 1. Парсинг XML-каталога Owen ===
// $xml = simplexml_load_file($xmlPath);
// if (!$xml) {
//     logm('[ERR] Не удалось загрузить XML: ' . $xmlPath);
//     flushLogAndExit(1);
// }

// $byArticle = [];
// $allIzdsFromXml = []; // Для последующего удаления "мусора"

// foreach ($xml->xpath('//product') as $product) {
//     $article = (string)$product->id;
//     $productName = (string)$product->name;

//     $offers = [];
//     if (!empty($product->prices)) {
//         foreach ($product->prices->price as $priceItem) {
//             $izd = trim((string)$priceItem->izd_code);
//             if ($izd === '') continue;

//             $mod_name = (string)$priceItem->name;
//             $offers[] = [
//                 'izd_code' => $izd,
//                 'mod_name' => $mod_name,
//                 'product_name' => $productName
//             ];
//             $allIzdsFromXml[$izd] = true; // помечаем izd как актуальный
//         }
//     }

//     if (count($offers) === 0) {
//         logm("[SKIP] Товар article={$article} не содержит izd_code — пропущен");
//         continue;
//     } elseif (count($offers) === 1) {
//         $byArticle[$article] = [
//             'type' => 'simple',
//             'product_name' => $productName,
//             'offer' => $offers[0]
//         ];
//         logm("[SIMPLE] Обнаружен простой товар article={$article}");
//     } else {
//         $byArticle[$article] = [
//             'type' => 'with_offers',
//             'product_name' => $productName,
//             'offers' => $offers
//         ];
//         logm("[COMPLEX] Обнаружен сложный товар article={$article} с " . count($offers) . " ТП");
//     }
// }
// logm('[OK] Всего обработано товаров: ' . count($byArticle));

// // === 2. Парсинг XLSX-файла (только столбцы B и R) ===
// $rows = readXlsxRows($xlsxPath);
// $overrides = [];
// $processed = 0;

// foreach ($rows as $index => $row) {
//     if ($index < 4) continue;

//     $izdFromXlsx = isset($row[1]) ? trim($row[1]) : '';
//     if (!$izdFromXlsx) continue;

//     $descFromXlsx = isset($row[17]) ? trim($row[17]) : '';
//     if ($descFromXlsx === '') continue;

//     $overrides[$izdFromXlsx] = $descFromXlsx;
//     $processed++;
//     if ($processed % 1000 === 0) {
//         logm("[PROGRESS] Обработано {$processed} строк XLSX");
//     }
// }
// logm("[XLSX] Загружено описаний из XLSX: " . count($overrides));

// // === 3. Применение оверрайдов и формирование финальных названий ===
// foreach ($byArticle as $article => &$productData) {
//     if ($productData['type'] === 'simple') {
//         $offer = $productData['offer'];
//         $izd = $offer['izd_code'];
//         if (isset($overrides[$izd])) {
//             $productData['name_long'] = $offer['mod_name'] . ' ' . $overrides[$izd];
//             $productData['from_xlsx'] = true;
//         } else {
//             $productData['name_long'] = $offer['mod_name'] . ' ' . $productData['product_name'];
//             $productData['from_xlsx'] = false;
//         }
//         logm("[NAME] Простой товар article={$article}: name='{$productData['name_long']}'");

//     } elseif ($productData['type'] === 'with_offers') {
//         foreach ($productData['offers'] as &$offer) {
//             $izd = $offer['izd_code'];
//             if (isset($overrides[$izd])) {
//                 $offer['name_long'] = $offer['mod_name'] . ' ' . $overrides[$izd];
//                 $offer['from_xlsx'] = true;
//             } else {
//                 $offer['name_long'] = $offer['mod_name'] . ' ' . $productData['product_name'];
//                 $offer['from_xlsx'] = false;
//             }
//         }
//         usort($productData['offers'], function($a, $b) {
//             return ($a['from_xlsx'] ? 0 : 1) - ($b['from_xlsx'] ? 0 : 1);
//         });
//         logm("[SORT] ТП для article={$article} отсортированы: XLSX первыми");
//     }
// }

// // === 4. Основной цикл импорта в Bitrix ===
// $created = 0;
// $updated = 0;
// $skipped = 0;
// $noProduct = 0;
// $deletedOrphanOffers = 0;

// foreach ($byArticle as $article => $productData) {
//     $productName = $productData['product_name'];

//     // === Поиск или создание товара ===
//     $productId = null;
//     $res = CIBlockElement::GetList(
//         ['ID' => 'ASC'],
//         [
//             'IBLOCK_ID' => $PRODUCT_IBLOCK_ID,
//             'ACTIVE' => 'Y',
//             'PROPERTY_' . $ARTICLE_PROPERTY_CODE => $article,
//         ],
//         false,
//         ['nTopCount' => 1],
//         ['ID', 'NAME']
//     );
//     if ($item = $res->GetNext()) {
//         $productId = (int)$item['ID'];
//         logm("[FOUND] Найден товар ID={$productId} для article={$article}");
//     } else {
//         logm("[CREATE] Создаём новый товар для article={$article}");
//         $el = new CIBlockElement();
//         $fields = [
//             'IBLOCK_ID' => $PRODUCT_IBLOCK_ID,
//             'ACTIVE' => 'Y',
//             'NAME' => $productName,
//             'CODE' => $article,
//             'XML_ID' => $article,
//             'PROPERTY_VALUES' => [
//                 $ARTICLE_PROPERTY_CODE => $article,
//             ],
//         ];
//         if (!$DRY_RUN) {
//             $productId = (int)$el->Add($fields);
//             if ($productId <= 0) {
//                 logm('[ERR] Ошибка создания товара: ' . $el->LAST_ERROR);
//                 $noProduct += ($productData['type'] === 'simple' ? 1 : count($productData['offers']));
//                 continue;
//             }
//             logm("[ADD PRODUCT] Создан товар ID={$productId}");
//         } else {
//             logm("[DRY] Пропуск создания товара (DRY_RUN)");
//             $noProduct += ($productData['type'] === 'simple' ? 1 : count($productData['offers']));
//             continue;
//         }
//     }

//     // === Обработка в зависимости от типа товара ===
//     if ($productData['type'] === 'simple') {
//         if (!$DRY_RUN) {
//             $el = new CIBlockElement();
//             $el->Update($productId, [
//                 'NAME' => $productData['name_long'],
//                 'CODE' => $productData['offer']['izd_code'],
//                 'XML_ID' => $productData['offer']['izd_code'],
//             ], false, false, true);
//             ensureCatalogProduct($productId);
//         }
//         $updated++;
//         logm("[UPD SIMPLE] Обновлён простой товар ID={$productId}, NAME='{$productData['name_long']}'");

//     } elseif ($productData['type'] === 'with_offers') {
//         $knownOfferIds = [];

//         foreach ($productData['offers'] as $offerRow) {
//             $izd = $offerRow['izd_code'];
//             $name_short = $offerRow['mod_name'];
//             $name_long = $offerRow['name_long'];
//             $sort = $offerRow['from_xlsx'] ? 100 : 200;

//             [$existingOfferId, $duplicateIds] = findExistingOffer($OFFERS_IBLOCK_ID, $LINK_PROP_ID, $productId, $izd);

//             if (!empty($duplicateIds)) {
//                 logm('[DUP] Найдены дубли для izd=' . $izd . ': ' . implode(',', $duplicateIds));
//                 if (!$DRY_RUN) {
//                     foreach ($duplicateIds as $dupId) {
//                         if ($dupId != $existingOfferId) {
//                             CIBlockElement::Delete($dupId);
//                             logm("[DEL] Удалён дубликат ТП ID={$dupId}");
//                         }
//                     }
//                 }
//             }

//             if ($existingOfferId) {
//                 if (!$DRY_RUN) {
//                     $el = new CIBlockElement();
//                     $el->Update($existingOfferId, [
//                         'NAME' => $name_short,
//                         'CODE' => $izd,
//                         'XML_ID' => $izd,
//                         'SORT' => $sort,
//                         'ACTIVE' => 'Y', // гарантируем активность
//                     ], false, false, true);
//                     CIBlockElement::SetPropertyValuesEx($existingOfferId, $OFFERS_IBLOCK_ID, [
//                         $LINK_PROP_ID => $productId,
//                         'modific' => $name_long,
//                         'IZD' => $izd,
//                     ]);
//                     ensureCatalogProduct($existingOfferId);
//                 }
//                 $updated++;
//                 $knownOfferIds[] = $existingOfferId;
//                 logm("[UPD OFFER] Обновлено ТП ID={$existingOfferId}, NAME='{$name_long}', SORT={$sort}");
//             } else {
//                 if (!$DRY_RUN) {
//                     $el = new CIBlockElement();
//                     $newId = (int)$el->Add([
//                         'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
//                         'ACTIVE' => 'Y',
//                         'NAME' => $name_short,
//                         'CODE' => $izd,
//                         'XML_ID' => $izd,
//                         'SORT' => $sort,
//                         'PROPERTY_VALUES' => [
//                             $LINK_PROP_ID => $productId,
//                             'modific' => $name_long,
//                             'IZD' => $izd,
//                         ],
//                     ]);
//                     if ($newId <= 0) {
//                         logm('[ERR] Ошибка создания ТП izd=' . $izd . ': ' . $el->LAST_ERROR);
//                         $skipped++;
//                         continue;
//                     }
//                     ensureCatalogProduct($newId);
//                     $created++;
//                     $knownOfferIds[] = $newId;
//                     logm("[ADD OFFER] Создано ТП ID={$newId}, NAME='{$name_long}', SORT={$sort}");
//                 } else {
//                     $created++;
//                 }
//             }
//         }

//         // === Деактивация лишних ТП (>30) ===
//         if (count($knownOfferIds) > 30) {
//             logm("[LIMIT] У товара article={$article} найдено " . count($knownOfferIds) . " ТП. Ограничиваем до 30.");
//             $activeIds = array_slice($knownOfferIds, 0, 30);
//             $inactiveIds = array_slice($knownOfferIds, 30);

//             foreach ($inactiveIds as $offerId) {
//                 if (!$DRY_RUN) {
//                     $el = new CIBlockElement();
//                     $el->Update($offerId, ['ACTIVE' => 'N'], false, false, true);
//                 }
//                 logm("[DEACTIVATE] Деактивировано ТП ID={$offerId}");
//             }
//         }
//     }
// }

// // === 5. УДАЛЕНИЕ "СИРОТ" — ТП, которых нет в XML ===
// logm("[CLEANUP] Поиск устаревших ТП (отсутствуют в XML)...");
// $resAllOffers = CIBlockElement::GetList(
//     [],
//     [
//         'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
//         'ACTIVE' => 'Y'
//     ],
//     false,
//     false,
//     ['ID', 'XML_ID']
// );
// while ($offer = $resAllOffers->Fetch()) {
//     $izd = $offer['XML_ID'];
//     if (!isset($allIzdsFromXml[$izd])) {
//         $deletedOrphanOffers++;
//         if (!$DRY_RUN) {
//             CIBlockElement::Delete((int)$offer['ID']);
//         }
//         logm("[DELETE ORPHAN] Удалён устаревший ТП ID={$offer['ID']} (izd={$izd})");
//     }
// }
// logm("[CLEANUP] Удалено устаревших ТП: {$deletedOrphanOffers}");

// // === 6. Очистка кэша ===
// if (!$DRY_RUN) {
//     BXClearCache(true, "/");
//     CIBlock::cleanCache($PRODUCT_IBLOCK_ID);
//     CIBlock::cleanCache($OFFERS_IBLOCK_ID);
//     CCatalog::ClearCache();
//     logm("[CACHE] Кэш очищен");
// }

// // === 7. Итоги ===
// logm('--- ИТОГИ ---');
// logm("Создано ТП: {$created}");
// logm("Обновлено ТП: {$updated}");
// logm("Удалено устаревших ТП: {$deletedOrphanOffers}");
// logm("Не найдено товаров: {$noProduct}");
// logm("Ошибок: {$skipped}");
// logm($DRY_RUN ? '[DRY-RUN] Изменения НЕ применены' : '[SUCCESS] Изменения применены');
// flushLogAndExit(0);

// // === Вспомогательные функции ===

// function findExistingOffer($offersIblockId, $linkPropId, $productId, $izd) {
//     $existingId = null;
//     $duplicates = [];
//     $res = CIBlockElement::GetList(
//         ['ID' => 'ASC'],
//         [
//             'IBLOCK_ID' => $offersIblockId,
//             'ACTIVE' => 'Y',
//             'XML_ID' => $izd,
//             'PROPERTY_' . $linkPropId => $productId,
//         ],
//         false,
//         false,
//         ['ID']
//     );
//     while ($item = $res->Fetch()) {
//         if (!$existingId) {
//             $existingId = (int)$item['ID'];
//         } else {
//             $duplicates[] = (int)$item['ID'];
//         }
//     }
//     return [$existingId, $duplicates];
// }

// function ensureCatalogProduct($offerId) {
//     $exist = CCatalogProduct::GetByID($offerId);
//     if (!$exist) {
//         CCatalogProduct::Add(['ID' => $offerId]);
//     }
// }

// function readXlsxRows($path) {
//     $rows = [];
//     $zip = new ZipArchive();
//     if ($zip->open($path) !== true) {
//         logm('[ERR] Не удалось открыть XLSX: ' . $path);
//         return $rows;
//     }

//     $sharedStrings = [];
//     $ssIndex = $zip->locateName('xl/sharedStrings.xml');
//     if ($ssIndex !== false) {
//         $xml = simplexml_load_string($zip->getFromIndex($ssIndex));
//         if ($xml) {
//             foreach ($xml->si as $si) {
//                 if (isset($si->t)) {
//                     $sharedStrings[] = (string)$si->t;
//                 } else {
//                     $acc = '';
//                     foreach ($si->r as $r) {
//                         $acc .= (string)$r->t;
//                     }
//                     $sharedStrings[] = $acc;
//                 }
//             }
//         }
//     }

//     $sheetPath = 'xl/worksheets/sheet1.xml';
//     if ($zip->locateName($sheetPath) === false) {
//         for ($i = 1; $i <= 10; $i++) {
//             $try = 'xl/worksheets/sheet' . $i . '.xml';
//             if ($zip->locateName($try) !== false) {
//                 $sheetPath = $try;
//                 break;
//             }
//         }
//     }

//     $sheetXmlStr = $zip->getFromName($sheetPath);
//     if ($sheetXmlStr === false) {
//         logm('[ERR] Не найден лист в XLSX');
//         $zip->close();
//         return $rows;
//     }

//     $sheet = simplexml_load_string($sheetXmlStr);
//     if (!$sheet) {
//         logm('[ERR] Ошибка парсинга sheet XML');
//         $zip->close();
//         return $rows;
//     }

//     function excelColToIndex($letters) {
//         $letters = strtoupper($letters);
//         $num = 0;
//         for ($i = 0; $i < strlen($letters); $i++) {
//             $num = $num * 26 + (ord($letters[$i]) - ord('A') + 1);
//         }
//         return $num - 1;
//     }

//     foreach ($sheet->sheetData->row as $rowNode) {
//         $row = [];
//         foreach ($rowNode->c as $c) {
//             $r = (string)$c['r'];
//             $colLetters = preg_replace('/\d+/', '', $r);
//             $colIndex = excelColToIndex($colLetters);
//             $t = (string)$c['t'];
//             $val = '';
//             if ($t === 's') {
//                 $idx = (int)$c->v;
//                 $val = $sharedStrings[$idx] ?? '';
//             } elseif ($t === 'inlineStr') {
//                 $val = isset($c->is->t) ? (string)$c->is->t : '';
//             } else {
//                 $val = isset($c->v) ? (string)$c->v : '';
//             }
//             while (count($row) <= $colIndex) {
//                 $row[] = '';
//             }
//             $row[$colIndex] = $val;
//         }
//         $rows[] = $row;
//     }

//     $zip->close();
//     return $rows;
// }