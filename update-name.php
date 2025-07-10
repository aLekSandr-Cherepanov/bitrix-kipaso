<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    die('Не удалось загрузить модуль iblock');
}

// Счетчики для статистики
$totalFiles = 0;
$processedFiles = 0;
$updatedCount = 0;
$skippedCount = 0;

// Выводим заголовок для HTML
echo "<pre>";
echo "Отчет по обработке файлов:\n";
echo "--------------------------------\n";

// Загружаем XML-файл
$xmlPath = $_SERVER["DOCUMENT_ROOT"]."/catalogOwen.xml";
echo "Попытка загрузки XML из {$xmlPath}\n";

if (!file_exists($xmlPath)) {
    die("Файл XML не найден по пути {$xmlPath}");
}

$xml = simplexml_load_file($xmlPath);
if (!$xml) {
    die("Ошибка при загрузке XML-файла. Проверьте его структуру.");
}

// Создаем массив соответствия имен файлов и их названий из XML
$fileNames = [];
echo "Извлечение названий файлов из XML...\n";

// Проверяем существование нужных узлов в XML
if (isset($xml->categories) && isset($xml->categories->category)) {
    foreach ($xml->categories->category as $cat) {
        if (isset($cat->items) && isset($cat->items->item)) {
            foreach ($cat->items->item as $sub) {
                if (isset($sub->products) && isset($sub->products->product)) {
                    foreach ($sub->products->product as $p) {
                        if (isset($p->docs)) {
                            foreach ($p->docs->doc as $docGroup) {
                                if (isset($docGroup->items)) {
                                    foreach ($docGroup->items->item as $docItem) {
                                        $docName = (string)$docItem->name;
                                        $docLink = (string)$docItem->link;
                                        
                                        // Получаем имя файла из URL
                                        $fileName = basename($docLink);
                                        
                                        // Сохраняем соответствие имени файла и названия из XML
                                        if (!empty($docName) && !empty($fileName)) {
                                            $fileNames[$fileName] = $docName;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

echo "Найдено " . count($fileNames) . " файлов в XML с названиями\n";

// Для отладки выведем несколько примеров
$counter = 0;
echo "\nПримеры соответствия имен файлов и названий:\n";
foreach ($fileNames as $fileName => $displayName) {
    echo "- {$fileName} => {$displayName}\n";
    $counter++;
    if ($counter >= 5) break; // Выводим только первые 5 записей для примера
}
echo "...и еще " . (count($fileNames) - $counter) . " файлов\n\n";

// Теперь обрабатываем файлы в базе данных
echo "Обновление описаний файлов в базе данных...\n";
$fileRes = CFile::GetList(array(), array());

while ($arFile = $fileRes->Fetch()) {
    $totalFiles++;
    $originalName = $arFile["ORIGINAL_NAME"];
    
    // Проверяем, есть ли файл с таким именем в нашем массиве соответствия
    if (isset($fileNames[$originalName])) {
        $processedFiles++;
        $newName = $fileNames[$originalName];
        
        // Выводим информацию о файле
        echo "ID файла: " . $arFile["ID"] . "\n";
        echo "Имя файла: " . $originalName . "\n";
        echo "Текущее описание: " . $arFile["DESCRIPTION"] . "\n";
        echo "Новое название из XML: " . $newName . "\n";
        
        // Обновляем описание, если оно отличается
        if (empty($arFile["DESCRIPTION"]) || $arFile["DESCRIPTION"] != $newName) {
            // Обновляем поле DESCRIPTION
            CFile::UpdateDesc($arFile["ID"], $newName);
            $updatedCount++;
            echo "► Описание обновлено на: {$newName}\n";
        } else {
            echo "✓ Описание уже актуально\n";
            $skippedCount++;
        }
        echo "--------------------------------\n";
    }
    
    // Выводим промежуточную статистику каждые 100 файлов
    if ($totalFiles % 100 == 0) {
        echo "\nОбработано {$totalFiles} файлов из базы данных\n";
    }
}

echo "\nСтатистика:\n";
echo "Всего файлов в базе: $totalFiles\n";
echo "Файлов с названиями из XML: $processedFiles\n";
echo "Файлов обработано без изменений: $skippedCount\n";
echo "Обновлено названий файлов: $updatedCount\n";
echo "</pre>";
?>