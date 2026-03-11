<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Проверка на запуск только через CLI (терминал)
if (php_sapi_name() !== 'cli') {
    die('Этот скрипт можно запускать только через терминал (CLI).');
}

// Явное определение DOCUMENT_ROOT для CLI и Bitrix
$_SERVER['DOCUMENT_ROOT'] = '/home/i/itkipae3/test2.owen.kipaso.ru/public_html';
define("B_PROLOG_INCLUDED", true); // Для Bitrix, чтобы избежать ошибок включения

// Подключение Bitrix API
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Отключение буферизации для избежания конфликтов
ob_end_clean();
define('BX_BUFFER_USED', false);

// Увеличение лимитов
ini_set('memory_limit', '1G');
set_time_limit(0);

// Константы
$PRODUCT_IBLOCK_ID = 16;
$OFFERS_IBLOCK_ID = 17;
$ARTICLE_PROPERTY_CODE = 'CML2_ARTICLE';
$LINK_PROP_ID = 'CML2_LINK';
$DRY_RUN = false;

// Пути
$xmlPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/catalogOven.xml';
$xlsxPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/korotkoeopicanietovarov.xlsx';

// Логирование (без file_put_contents)
function logm($msg) {
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/import_log.txt';
    $handle = fopen($logFile, 'a');
    if ($handle) {
        fwrite($handle, $msg . PHP_EOL);
        fclose($handle);
    }
    echo $msg . "\n";
}
function flushLogAndExit($code) {
    exit($code);
}

// Парсинг XML
$xml = simplexml_load_file($xmlPath);
if (!$xml) {
    logm('[ERR] Не удалось загрузить XML: ' . $xmlPath);
    flushLogAndExit(1);
}

$byArticle = [];
foreach ($xml->xpath('//product') as $product) {
    $article = (string)$product->id; // Пример: <id>itp17</id>
    $productName = (string)$product->name; // Пример: <name>ИТП-17 трехцветный измеритель-индикатор с универсальным входом</name>
    logm("[XML] Обработка товара article={$article}, name={$productName}");
    
    $offers = [];
    foreach ($product->prices->price as $priceItem) {
        $mod_name = (string)$priceItem->name; // Пример: <name>ИТП-17.Щ9.К</name>
        $izd = (string)$priceItem->izd_code; // Пример: <izd_code>140098</izd_code>
        $priceVal = (float)$priceItem->price; // Пример: <price>5880.00</price>
        
        $name_long_xml = $productName . ' ' . $mod_name; // Пример: <name>ИТП-17 трехцветный измеритель-индикатор с универсальным входом</name> <name>ИТП-17.Щ9.К</name>
        
        $offers[] = [
            'izd_code' => $izd,
            'mod_name' => $mod_name,
            'name_long' => $name_long_xml,
            'price' => $priceVal
        ];
    }
    
    $byArticle[$article] = [
        'product_name' => $productName,
        'offers' => $offers
    ];
}
logm('[OK] Найдено товаров в XML: ' . count($byArticle));

// Парсинг XLSX (оптимизировано)

// Столбец - А = Текущая дата
// Стоблец- B = Артикул как раз для сверки izd code 
// Дальше идут столбцы которые нас совершенно не интересует просто показываю порядок общий - 
// А - B - C - D - E - F - G - H - I - J - K - L - M - N - O - P - Q - R
// Как раз стоблец R - отвечает за наименование которое мы перезаписываем если оно сопоставляется с артикулом на сайте, если в столбце R пусто то мы вообще не используем данные из этой таблицы
 




$rows = readXlsxRows($xlsxPath);
$overrides = [];
$processed = 0;
foreach ($rows as $index => $row) {
    if ($index < 4) continue;
    
    $izdFromXlsx = trim($row[2]);
    if (!$izdFromXlsx) continue; // Пропуск пустых
    
    $name_short_xlsx = trim($row[3]);
    $name_long_xlsx = trim($row[5]);
    $price_xlsx = (float)str_replace(',', '.', trim($row[6]));
    $desc_short_xlsx = trim($row[18]);
    
    $overrides[$izdFromXlsx] = [
        'name_short' => $name_short_xlsx,
        'name_long' => $name_long_xlsx,
        'price' => $price_xlsx,
        'desc_short' => $desc_short_xlsx
    ];
    $processed++;
    if ($processed % 1000 === 0) {
        logm("[PROGRESS] Обработано {$processed} строк XLSX");
    }
}

// Оверрайд
foreach ($byArticle as $article => &$productData) {
    $offers = &$productData['offers'];
    foreach ($offers as &$offer) {
        $izd = $offer['izd_code'];
        if (isset($overrides[$izd])) {
            $override = $overrides[$izd];
            $offer['name_short'] = $override['name_short'] ?: $offer['mod_name'];
            $offer['name_long'] = $override['desc_short'] ?: $override['name_long'] ?: $offer['name_long'];
            $offer['price'] = $override['price'] ?: $offer['price'];
            $offer['from_xlsx'] = true;
            logm("[OVERRIDE] Для izd={$izd}: name_long='{$offer['name_long']}' (desc_short priority)");
        } else {
            $offer['from_xlsx'] = false;
            logm("[NO OVERRIDE] Для izd={$izd}: XML name_long='{$offer['name_long']}'");
        }
    }
    // Сортировка: XLSX первыми
    usort($offers, function($a, $b) {
        return ($a['from_xlsx'] ? 0 : 1) - ($b['from_xlsx'] ? 0 : 1);
    });
    logm("[SORT] Offers для article={$article} отсортированы: XLSX первыми");
}

// Базовая цена
$basePriceGroup = CCatalogGroup::GetBaseGroup();
$basePriceTypeId = $basePriceGroup ? (int)$basePriceGroup['ID'] : 1;
logm('[OK] Базовый тип цены ID=' . $basePriceTypeId);

$created = 0;
$updated = 0;
$skipped = 0;
$noPrice = 0;
$noProduct = 0;

foreach ($byArticle as $article => $productData) {
    $productName = $productData['product_name'];
    $offers = $productData['offers'];
    
    $productId = null;
    $res = CIBlockElement::GetList(
        ['ID' => 'ASC'],
        [
            'IBLOCK_ID' => $PRODUCT_IBLOCK_ID,
            'ACTIVE' => 'Y',
            'PROPERTY_' . $ARTICLE_PROPERTY_CODE => $article,
        ],
        false,
        ['nTopCount' => 1],
        ['ID', 'NAME']
    );
    if ($item = $res->GetNext()) {
        $productId = (int)$item['ID'];
        logm("[FOUND] Товар ID={$productId} для article={$article}");
    }
    
    if (!$productId) {
        logm("[NOT FOUND] Создаём товар для article={$article}");
        $el = new CIBlockElement();
        $fields = [
            'IBLOCK_ID' => $PRODUCT_IBLOCK_ID,
            'ACTIVE' => 'Y',
            'NAME' => $productName,
            'CODE' => $article,
            'XML_ID' => $article,
            'PROPERTY_VALUES' => [
                $ARTICLE_PROPERTY_CODE => $article,
            ],
        ];
        if (!$DRY_RUN) {
            $productId = (int)$el->Add($fields);
            if ($productId <= 0) {
                logm('[ERR] Ошибка создания товара: ' . $el->LAST_ERROR);
                $noProduct += count($offers);
                continue;
            }
            logm("[ADD PRODUCT] Создан ID={$productId}");
        } else {
            logm("[DRY] Пропуск создания товара");
            $noProduct += count($offers);
            continue;
        }
    }
    
    // Если нет offers — простой товар
    if (empty($offers)) {
        // Простой товар без модификаций
        logm("[SIMPLE] Простой товар article={$article}, присваиваем CODE из XLSX/XML если есть");
        // Поиск izd в XLSX для override
        $izd = ''; // Fallback, если нет - использовать article
        $name_long = $productName;
        $priceVal = 0;
        // Если есть override по article или fallback
        // Предполагаем, что для простых izd = article или из XLSX по article (если XLSX имеет column для article)
        // Но по задаче - если в XLSX, использовать
        if (isset($overrides[$article])) { // Если XLSX имеет по article
            $override = $overrides[$article];
            $name_long = $override['desc_short'] ?: $override['name_long'] ?: $name_long;
            $priceVal = $override['price'] ?: $priceVal;
            $izd = $article;
        }
        
        // Update CODE for simple product
        $el = new CIBlockElement();
        $updateFields = [
            'CODE' => $izd ?: $article,
            'XML_ID' => $izd ?: $article,
        ];
        if (!$DRY_RUN) {
            $el->Update($productId, $updateFields, false, false, true);
            // Добавляем в каталог, если не
            ensureCatalogProduct($productId);
            upsertPrice($productId, $priceVal, $basePriceTypeId);
        }
        $updated++;
        logm("[UPD SIMPLE] Обновлён простой товар ID={$productId} (article={$article}), CODE={$izd}, name_long='{$name_long}'");
        continue;
    }
    
    // Для товаров с offers
    foreach ($offers as $offerRow) {
        $izd = $offerRow['izd_code'];
        $name_short = $offerRow['name_short'];
        $name_long = $offerRow['name_long'];
        $priceVal = $offerRow['price'];
        
        if ($priceVal === 0) {
            $noPrice++;
            logm("[WARN] Нет цены для izd={$izd}, article={$article}");
            continue;
        }
        
        [$existingOfferId, $duplicateIds] = findExistingOffer($OFFERS_IBLOCK_ID, $LINK_PROP_ID, $productId, $izd);
        
        if (!empty($duplicateIds)) {
            logm('[DUP] Дубли для izd=' . $izd . ': ' . implode(',', $duplicateIds));
            if (!$DRY_RUN) {
                foreach ($duplicateIds as $dupId) {
                    if ($dupId != $existingOfferId) {
                        CIBlockElement::Delete($dupId);
                        logm("[DEL] Удалён дубликат ID={$dupId}");
                    }
                }
            }
        }
        
        $sort = $offerRow['from_xlsx'] ? 100 : 200; // XLSX = 100 (выше), XML = 200
        
        if ($existingOfferId) {
            $el = new CIBlockElement();
            $updateFields = [
                'NAME' => $name_short,
                'CODE' => $izd,
                'XML_ID' => $izd,
                'SORT' => $sort,
            ];
            if (!$DRY_RUN) {
                $el->Update($existingOfferId, $updateFields, false, false, true);
                CIBlockElement::SetPropertyValuesEx($existingOfferId, $OFFERS_IBLOCK_ID, [
                    $LINK_PROP_ID => $productId,
                    'modific' => $name_long,
                    'IZD' => $izd,
                ]);
                upsertPrice($existingOfferId, $priceVal, $basePriceTypeId);
            }
            $updated++;
            logm("[UPD] Обновлено ТП ID={$existingOfferId} (izd={$izd}), name_long='{$name_long}', sort={$sort}");
        } else {
            $el = new CIBlockElement();
            $fields = [
                'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
                'ACTIVE' => 'Y',
                'NAME' => $name_short,
                'CODE' => $izd,
                'XML_ID' => $izd,
                'SORT' => $sort,
                'PROPERTY_VALUES' => [
                    $LINK_PROP_ID => $productId,
                    'modific' => $name_long,
                    'IZD' => $izd,
                ],
            ];
            $newId = 0;
            if (!$DRY_RUN) {
                $newId = (int)$el->Add($fields);
                if ($newId <= 0) {
                    logm('[ERR] Ошибка создания ТП izd=' . $izd . ': ' . $el->LAST_ERROR);
                    $skipped++;
                    continue;
                }
                ensureCatalogProduct($newId);
                upsertPrice($newId, $priceVal, $basePriceTypeId);
            }
            $created++;
            logm("[ADD] Создано ТП ID={$newId} (izd={$izd}), name_long='{$name_long}', sort={$sort}");
        }
    }
}

// Очистка кэша
if (!$DRY_RUN) {
    BXClearCache(true, "/");
    CIBlock::cleanCache($PRODUCT_IBLOCK_ID);
    CIBlock::cleanCache($OFFERS_IBLOCK_ID);
    CCatalog::ClearCache();
    logm("[CACHE] Кэш очищен");
}

// Итоги
logm('--- Итоги ---');
logm("Создано ТП: {$created}");
logm("Обновлено ТП: {$updated}");
logm("Пропущено без цены: {$noPrice}");
logm("Не найден товар: {$noProduct}");
logm("Ошибок: {$skipped}");
logm($DRY_RUN ? '[DRY-RUN] Без изменений' : '[APPLY] Изменения применены');
flushLogAndExit(200);

// Функции
function findExistingOffer($offersIblockId, $linkPropId, $productId, $izd) {
    $existingId = null;
    $duplicates = [];
    $res = CIBlockElement::GetList(
        ['ID' => 'ASC'],
        [
            'IBLOCK_ID' => $offersIblockId,
            'ACTIVE' => 'Y',
            'XML_ID' => $izd,
            'PROPERTY_' . $linkPropId => $productId,
        ],
        false,
        false,
        ['ID']
    );
    while ($item = $res->Fetch()) {
        if (!$existingId) {
            $existingId = (int)$item['ID'];
        } else {
            $duplicates[] = (int)$item['ID'];
        }
    }
    return [$existingId, $duplicates];
}

function ensureCatalogProduct($offerId) {
    $exist = CCatalogProduct::GetByID($offerId);
    if (!$exist) {
        CCatalogProduct::Add(['ID' => $offerId]);
    }
}

function upsertPrice($productId, $price, $priceTypeId) {
    $currency = 'RUB';
    $res = CPrice::GetList([], ['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => $priceTypeId]);
    if ($ar = $res->Fetch()) {
        CPrice::Update($ar['ID'], ['PRICE' => $price, 'CURRENCY' => $currency]);
    } else {
        CPrice::Add(['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => $priceTypeId, 'PRICE' => $price, 'CURRENCY' => $currency]);
    }
}

function readXlsxRows($path) {
    $rows = [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        logm('[ERR] Не удалось открыть XLSX: ' . $path);
        return $rows;
    }
    $sharedStrings = [];
    $ssIndex = $zip->locateName('xl/sharedStrings.xml');
    if ($ssIndex !== false) {
        $xml = simplexml_load_string($zip->getFromIndex($ssIndex));
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $acc = '';
                    foreach ($si->r as $r) {
                        $acc .= (string)$r->t;
                    }
                    $sharedStrings[] = $acc;
                }
            }
        }
    }
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if ($zip->locateName($sheetPath) === false) {
        for ($i = 1; $i <= 10; $i++) {
            $try = 'xl/worksheets/sheet' . $i . '.xml';
            if ($zip->locateName($try) !== false) {
                $sheetPath = $try;
                break;
            }
        }
    }
    $sheetXmlStr = $zip->getFromName($sheetPath);
    if ($sheetXmlStr === false) {
        logm('[ERR] Не найден лист в XLSX (sheet1.xml)');
        $zip->close();
        return $rows;
    }
    $sheet = simplexml_load_string($sheetXmlStr);
    if (!$sheet) {
        logm('[ERR] Ошибка парсинга sheet XML');
        $zip->close();
        return $rows;
    }
    $maxCol = 0;
    foreach ($sheet->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $c) {
            $r = (string)$c['r'];
            $colLetters = preg_replace('/\d+/', '', $r);
            $colIndex = excelColToIndex($colLetters);
            if ($colIndex > $maxCol) $maxCol = $colIndex;
            $t = (string)$c['t'];
            $val = '';
            if ($t === 's') {
                $idx = (int)$c->v;
                $val = $sharedStrings[$idx] ?? '';
            } elseif ($t === 'inlineStr') {
                $val = isset($c->is->t) ? (string)$c->is->t : '';
            } else {
                $val = isset($c->v) ? (string)$c->v : '';
            }
            $row[$colIndex] = $val;
        }
        if (!empty($row)) {
            for ($i = 1; $i <= $maxCol; $i++) {
                if (!array_key_exists($i, $row)) $row[$i] = '';
            }
            ksort($row);
            $rows[] = $row;
        }
    }
    $zip->close();
    return $rows;
}

function excelColToIndex($letters) {
    $letters = strtoupper($letters);
    $num = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $num = $num * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $num;
}



// Это рабочий код но без АРТИКУЛА для простого товара
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);

// // Проверка на запуск только через CLI (терминал)
// if (php_sapi_name() !== 'cli') {
//     die('Этот скрипт можно запускать только через терминал (CLI).');
// }

// // Явное определение DOCUMENT_ROOT для CLI и Bitrix
// $_SERVER['DOCUMENT_ROOT'] = '/home/i/itkipae3/test2.owen.kipaso.ru/public_html';
// define("B_PROLOG_INCLUDED", true); // Для Bitrix, чтобы избежать ошибок включения

// // Подключение Bitrix API
// require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
// use Bitrix\Main\Loader;
// Loader::includeModule('iblock');
// Loader::includeModule('catalog');

// // Отключение буферизации для избежания конфликтов
// ob_end_clean();
// define('BX_BUFFER_USED', false);

// // Увеличение лимитов
// ini_set('memory_limit', '1G');
// set_time_limit(0);

// // Константы
// $PRODUCT_IBLOCK_ID = 16;
// $OFFERS_IBLOCK_ID = 17;
// $ARTICLE_PROPERTY_CODE = 'CML2_ARTICLE';
// $LINK_PROP_ID = 'CML2_LINK';
// $DRY_RUN = false;

// // Пути
// $xmlPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/catalogOven.xml';
// $xlsxPath = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/korotkoeopicanietovarov.xlsx';

// // Логирование (без file_put_contents)
// function logm($msg) {
//     $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/scripts/torgovie_predlozenia/import_log.txt';
//     $handle = fopen($logFile, 'a');
//     if ($handle) {
//         fwrite($handle, $msg . PHP_EOL);
//         fclose($handle);
//     }
//     echo $msg . "\n";
// }
// function flushLogAndExit($code) {
//     exit($code);
// }

// // Парсинг XML
// $xml = simplexml_load_file($xmlPath);
// if (!$xml) {
//     logm('[ERR] Не удалось загрузить XML: ' . $xmlPath);
//     flushLogAndExit(1);
// }

// $byArticle = [];
// foreach ($xml->xpath('//product') as $product) {
//     $article = (string)$product->id;
//     $productName = (string)$product->name;
//     logm("[XML] Обработка товара article={$article}, name={$productName}");
    
//     foreach ($product->prices->price as $priceItem) {
//         $mod_name = (string)$priceItem->name;
//         $izd = (string)$priceItem->izd_code;
//         $priceVal = (float)$priceItem->price;
        
//         $name_long_xml = $productName . ' ' . $mod_name;
        
//         $byArticle[$article][] = [
//             'izd_code' => $izd,
//             'mod_name' => $mod_name,
//             'name_long' => $name_long_xml,
//             'price' => $priceVal
//         ];
//     }
// }
// logm('[OK] Найдено товаров в XML: ' . count($byArticle));

// // Парсинг XLSX (оптимизировано)
// $rows = readXlsxRows($xlsxPath);
// $overrides = [];
// $processed = 0;
// foreach ($rows as $index => $row) {
//     if ($index < 4) continue;
    
//     $izdFromXlsx = trim($row[2]);
//     if (!$izdFromXlsx) continue; // Пропуск пустых
    
//     $name_short_xlsx = trim($row[3]);
//     $name_long_xlsx = trim($row[5]);
//     $price_xlsx = (float)str_replace(',', '.', trim($row[6]));
//     $desc_short_xlsx = trim($row[18]);
    
//     $overrides[$izdFromXlsx] = [
//         'name_short' => $name_short_xlsx,
//         'name_long' => $name_long_xlsx,
//         'price' => $price_xlsx,
//         'desc_short' => $desc_short_xlsx
//     ];
//     $processed++;
//     if ($processed % 1000 === 0) {
//         logm("[PROGRESS] Обработано {$processed} строк XLSX");
//     }
// }

// // Оверрайд
// foreach ($byArticle as $article => &$offers) {
//     foreach ($offers as &$offer) {
//         $izd = $offer['izd_code'];
//         if (isset($overrides[$izd])) {
//             $override = $overrides[$izd];
//             $offer['name_short'] = $override['name_short'] ?: $offer['mod_name'];
//             $offer['name_long'] = $override['desc_short'] ?: $override['name_long'] ?: $offer['name_long'];
//             $offer['price'] = $override['price'] ?: $offer['price'];
//             $offer['from_xlsx'] = true;
//             logm("[OVERRIDE] Для izd={$izd}: name_long='{$offer['name_long']}' (desc_short priority)");
//         } else {
//             $offer['from_xlsx'] = false;
//             logm("[NO OVERRIDE] Для izd={$izd}: XML name_long='{$offer['name_long']}'");
//         }
//     }
//     // Сортировка: XLSX первыми
//     usort($offers, function($a, $b) {
//         return ($a['from_xlsx'] ? 0 : 1) - ($b['from_xlsx'] ? 0 : 1);
//     });
//     logm("[SORT] Offers для article={$article} отсортированы: XLSX первыми");
// }

// // Базовая цена
// $basePriceGroup = CCatalogGroup::GetBaseGroup();
// $basePriceTypeId = $basePriceGroup ? (int)$basePriceGroup['ID'] : 1;
// logm('[OK] Базовый тип цены ID=' . $basePriceTypeId);

// $created = 0;
// $updated = 0;
// $skipped = 0;
// $noPrice = 0;
// $noProduct = 0;

// foreach ($byArticle as $article => $offers) {
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
//         logm("[FOUND] Товар ID={$productId} для article={$article}");
//     }
    
//     if (!$productId) {
//         logm("[NOT FOUND] Создаём товар для article={$article}");
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
//                 $noProduct += count($offers);
//                 continue;
//             }
//             logm("[ADD PRODUCT] Создан ID={$productId}");
//         } else {
//             logm("[DRY] Пропуск создания товара");
//             $noProduct += count($offers);
//             continue;
//         }
//     }
    
//     foreach ($offers as $offerRow) {
//         $izd = $offerRow['izd_code'];
//         $name_short = $offerRow['name_short'];
//         $name_long = $offerRow['name_long'];
//         $priceVal = $offerRow['price'];
        
//         if ($priceVal === 0) {
//             $noPrice++;
//             logm("[WARN] Нет цены для izd={$izd}, article={$article}");
//             continue;
//         }
        
//         [$existingOfferId, $duplicateIds] = findExistingOffer($OFFERS_IBLOCK_ID, $LINK_PROP_ID, $productId, $izd);
        
//         if (!empty($duplicateIds)) {
//             logm('[DUP] Дубли для izd=' . $izd . ': ' . implode(',', $duplicateIds));
//             if (!$DRY_RUN) {
//                 foreach ($duplicateIds as $dupId) {
//                     if ($dupId != $existingOfferId) {
//                         CIBlockElement::Delete($dupId);
//                         logm("[DEL] Удалён дубликат ID={$dupId}");
//                     }
//                 }
//             }
//         }
        
//         $sort = $offerRow['from_xlsx'] ? 100 : 200; // XLSX = 100 (выше), XML = 200
        
//         if ($existingOfferId) {
//             $el = new CIBlockElement();
//             $updateFields = [
//                 'NAME' => $name_short,
//                 'CODE' => $izd,
//                 'XML_ID' => $izd,
//                 'SORT' => $sort,
//             ];
//             if (!$DRY_RUN) {
//                 $el->Update($existingOfferId, $updateFields, false, false, true);
//                 CIBlockElement::SetPropertyValuesEx($existingOfferId, $OFFERS_IBLOCK_ID, [
//                     $LINK_PROP_ID => $productId,
//                     'modific' => $name_long,
//                     'IZD' => $izd,
//                 ]);
//                 upsertPrice($existingOfferId, $priceVal, $basePriceTypeId);
//             }
//             $updated++;
//             logm("[UPD] Обновлено ТП ID={$existingOfferId} (izd={$izd}), name_long='{$name_long}', sort={$sort}");
//         } else {
//             $el = new CIBlockElement();
//             $fields = [
//                 'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
//                 'ACTIVE' => 'Y',
//                 'NAME' => $name_short,
//                 'CODE' => $izd,
//                 'XML_ID' => $izd,
//                 'SORT' => $sort,
//                 'PROPERTY_VALUES' => [
//                     $LINK_PROP_ID => $productId,
//                     'modific' => $name_long,
//                     'IZD' => $izd,
//                 ],
//             ];
//             $newId = 0;
//             if (!$DRY_RUN) {
//                 $newId = (int)$el->Add($fields);
//                 if ($newId <= 0) {
//                     logm('[ERR] Ошибка создания ТП izd=' . $izd . ': ' . $el->LAST_ERROR);
//                     $skipped++;
//                     continue;
//                 }
//                 ensureCatalogProduct($newId);
//                 upsertPrice($newId, $priceVal, $basePriceTypeId);
//             }
//             $created++;
//             logm("[ADD] Создано ТП ID={$newId} (izd={$izd}), name_long='{$name_long}', sort={$sort}");
//         }
//     }
// }

// // Очистка кэша
// if (!$DRY_RUN) {
//     BXClearCache(true, "/");
//     CIBlock::cleanCache($PRODUCT_IBLOCK_ID);
//     CIBlock::cleanCache($OFFERS_IBLOCK_ID);
//     CCatalog::ClearCache();
//     logm("[CACHE] Кэш очищен");
// }

// // Итоги
// logm('--- Итоги ---');
// logm("Создано ТП: {$created}");
// logm("Обновлено ТП: {$updated}");
// logm("Пропущено без цены: {$noPrice}");
// logm("Не найден товар: {$noProduct}");
// logm("Ошибок: {$skipped}");
// logm($DRY_RUN ? '[DRY-RUN] Без изменений' : '[APPLY] Изменения применены');
// flushLogAndExit(200);

// // Функции
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

// function upsertPrice($productId, $price, $priceTypeId) {
//     $currency = 'RUB';
//     $res = CPrice::GetList([], ['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => $priceTypeId]);
//     if ($ar = $res->Fetch()) {
//         CPrice::Update($ar['ID'], ['PRICE' => $price, 'CURRENCY' => $currency]);
//     } else {
//         CPrice::Add(['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => $priceTypeId, 'PRICE' => $price, 'CURRENCY' => $currency]);
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
//         logm('[ERR] Не найден лист в XLSX (sheet1.xml)');
//         $zip->close();
//         return $rows;
//     }
//     $sheet = simplexml_load_string($sheetXmlStr);
//     if (!$sheet) {
//         logm('[ERR] Ошибка парсинга sheet XML');
//         $zip->close();
//         return $rows;
//     }
//     $maxCol = 0;
//     foreach ($sheet->sheetData->row as $rowNode) {
//         $row = [];
//         foreach ($rowNode->c as $c) {
//             $r = (string)$c['r'];
//             $colLetters = preg_replace('/\d+/', '', $r);
//             $colIndex = excelColToIndex($colLetters);
//             if ($colIndex > $maxCol) $maxCol = $colIndex;
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
//             $row[$colIndex] = $val;
//         }
//         if (!empty($row)) {
//             for ($i = 1; $i <= $maxCol; $i++) {
//                 if (!array_key_exists($i, $row)) $row[$i] = '';
//             }
//             ksort($row);
//             $rows[] = $row;
//         }
//     }
//     $zip->close();
//     return $rows;
// }

// function excelColToIndex($letters) {
//     $letters = strtoupper($letters);
//     $num = 0;
//     for ($i = 0; $i < strlen($letters); $i++) {
//         $num = $num * 26 + (ord($letters[$i]) - ord('A') + 1);
//     }
//     return $num;
// }
?>