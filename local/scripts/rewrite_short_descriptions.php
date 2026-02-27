<?php

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

header('Content-Type: text/plain; charset=utf-8');

function getParam(string $key, $default = null)
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        $args = $argv ?? [];
        foreach ($args as $a) {
            $a = (string)$a;
            if ($a === '--' . $key) {
                return 1;
            }
            $prefix = '--' . $key . '=';
            if (strpos($a, $prefix) === 0) {
                return substr($a, strlen($prefix));
            }
        }
        return $default;
    }

    return $_GET[$key] ?? $default;
}

function mbContains(string $haystack, string $needle): bool
{
    return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
}

function normalizeSpaces(string $s): string
{
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim((string)$s);
}

function extractModelFromName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    if (preg_match('~\bОВЕН\s+(.+)$~u', $name, $m)) {
        $model = trim((string)$m[1]);
        if ($model !== '') {
            return $model;
        }
    }

    if (preg_match('~([A-ZА-Я0-9][A-ZА-Я0-9\.\-\/]{2,})$~u', $name, $m)) {
        return trim((string)$m[1]);
    }

    return $name;
}

function detectDeviceType(string $name): string
{
    $n = mb_strtolower($name, 'UTF-8');

    if (mb_strpos($n, 'контроллер', 0, 'UTF-8') !== false) {
        return 'Контроллер';
    }
    if (mb_strpos($n, 'пид', 0, 'UTF-8') !== false) {
        return 'ПИД-регулятор';
    }
    if (mb_strpos($n, 'погодозавис', 0, 'UTF-8') !== false) {
        return 'Погодозависимый регулятор';
    }
    if (mb_strpos($n, 'измеритель', 0, 'UTF-8') !== false && mb_strpos($n, 'регулятор', 0, 'UTF-8') !== false) {
        return 'Измеритель-регулятор';
    }
    if (mb_strpos($n, 'терморегулятор', 0, 'UTF-8') !== false) {
        return 'Терморегулятор';
    }

    return 'Устройство';
}

function rewriteTextOffline(string $text): string
{
    $text = normalizeSpaces($text);
    if ($text === '') {
        return '';
    }

    $replacements = [
        '~\s+—\s+это\s+~ui' => ' — ',
        '~\bэто\s+многофункциональн(ый|ая|ое|ые)\s+прибор\b~ui' => 'универсальное устройство',
        '~\bмногофункциональн(ый|ая|ое|ые)\s+прибор\b~ui' => 'универсальное устройство',
        '~\bпредназначен\s+для\b~ui' => 'используется для',
        '~\bпредназначена\s+для\b~ui' => 'используется для',
        '~\bпредназначенное\s+для\b~ui' => 'которое применяется для',
        '~\bпредназначенный\s+для\b~ui' => 'который применяется для',
        '~\bпредназначенная\s+для\b~ui' => 'которая применяется для',
        '~\bизмерения,\s*регистрации\s+или\s+регулирования\b~ui' => 'измерения, индикации и регулирования',
        '~\bсоздания\s+многоканальных\s+автоматизированных\s+систем\b~ui' => 'построения многоканальных автоматизированных систем',
        '~\bмониторинга,\s*контроля\s+и\s+управления\b~ui' => 'мониторинга, контроля и управления',
        '~\bразличных\s+сред\b~ui' => 'разных сред',
        '~\bшироко\s+используется\b~ui' => 'применяется',
        '~\bв\s+различных\s+отраслях\b~ui' => 'в разных сферах',
        '~\bв\s+холодильной\s+технике\b~ui' => 'в оборудовании холодильной техники',
        '~\bпечах\s+различного\s+назначения\b~ui' => 'печах разного назначения',
        '~\bи\s+другом\s+технологическом\s+оборудовании\b~ui' => 'а также в другом технологическом оборудовании',
        '~\bа\s+также\b~ui' => 'также',
        '~\bт\.?\s*п\.?\b~ui' => 'и т. п.',
        '~\bпр\.?\b~ui' => 'и др.',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text);
    }

    $text = normalizeSpaces($text);

    if (!preg_match('~[\.!\?]$~u', $text)) {
        $text .= '.';
    }

    return $text;
}

function buildParamsSentence(array $params): string
{
    $ip = '';
    $mount = '';
    $rs = '';
    $dims = '';
    $channels = '';
    $io = '';
    $out = '';

    foreach ($params as $k => $v) {
        $k = trim((string)$k);
        $v = trim((string)$v);
        if ($v === '') {
            continue;
        }

        if ($ip === '' && preg_match('~Степень\s+защиты~ui', $k)) {
            $ip = $v;
            continue;
        }
        if ($mount === '' && preg_match('~Вид\s+монтажа~ui', $k)) {
            $mount = $v;
            continue;
        }
        if ($rs === '' && preg_match('~RS-?485~ui', $k)) {
            $rs = $v;
            continue;
        }
        if ($dims === '' && preg_match('~Габаритн~ui', $k)) {
            $dims = $v;
            continue;
        }
        if ($channels === '' && preg_match('~Количество\s+каналов~ui', $k)) {
            $channels = $v;
            continue;
        }
        if ($io === '' && preg_match('~входов/выходов~ui', $k)) {
            $io = $v;
            continue;
        }
        if ($out === '' && preg_match('~Тип\s+выход~ui', $k)) {
            $out = $v;
            continue;
        }
    }

    $parts = [];

    if ($mount !== '') {
        $parts[] = 'Монтаж: ' . $mount;
    }

    if ($ip !== '') {
        $parts[] = 'Степень защиты: ' . $ip;
    }

    if ($rs !== '') {
        $rsNorm = mb_strtolower($rs, 'UTF-8');
        if ($rsNorm === 'нет' || $rsNorm === 'не поддерживает' || $rsNorm === 'не поддерживает rs-485') {
            $parts[] = 'Интерфейс RS-485 не предусмотрен';
        } elseif ($rsNorm === 'да' || $rsNorm === 'есть') {
            $parts[] = 'Есть интерфейс RS-485';
        } else {
            $parts[] = 'RS-485: ' . $rs;
        }
    }

    if ($channels !== '') {
        $parts[] = 'Каналов: ' . $channels;
    }

    if ($io !== '') {
        $parts[] = 'Входы/выходы: ' . $io;
    }

    if ($out !== '') {
        $parts[] = 'Тип выхода: ' . $out;
    }

    if ($dims !== '') {
        $parts[] = 'Габариты: ' . $dims . ' мм';
    }

    if (!$parts) {
        return '';
    }

    return implode(', ', $parts) . '.';
}

function buildFallbackBody(string $name): string
{
    $n = mb_strtolower($name, 'UTF-8');

    if (mb_strpos($n, 'отоплен', 0, 'UTF-8') !== false || mb_strpos($n, 'гвс', 0, 'UTF-8') !== false) {
        return 'Используется для регулирования температуры в системах отопления и горячего водоснабжения.';
    }
    if (mb_strpos($n, 'пид', 0, 'UTF-8') !== false) {
        return 'Применяется для измерения параметров и управления нагрузкой по ПИД-закону.';
    }
    if (mb_strpos($n, 'погодозавис', 0, 'UTF-8') !== false) {
        return 'Предназначен для управления температурой по заданной уставке или отопительному графику.';
    }

    return 'Применяется для измерения и/или регулирования технологических параметров в составе промышленного оборудования.';
}

function extractArticleAndShortDescription(string $block): array
{
    $article = '';
    $short = '';

    libxml_use_internal_errors(true);
    try {
        $sx = simplexml_load_string($block);
        if ($sx instanceof SimpleXMLElement) {
            $article = normalizeSpaces((string)$sx->article);
            $short = normalizeSpaces((string)$sx->short_description);
        }
    } catch (Throwable $e) {
        // ignore
    }
    libxml_clear_errors();

    if ($article === '' && preg_match('~<article>(.*?)</article>~su', $block, $m)) {
        $article = normalizeSpaces(strip_tags((string)$m[1]));
    }
    if ($short === '' && preg_match('~<short_description>(.*?)</short_description>~su', $block, $m)) {
        $short = normalizeSpaces((string)$m[1]);
    }

    return [$article, $short];
}

function buildNeutralFromName(string $name): string
{
    $name = normalizeSpaces($name);
    if ($name === '') {
        return '';
    }

    $deviceType = detectDeviceType($name);
    $model = extractModelFromName($name);

    $title = $deviceType;
    if ($model !== '') {
        $title .= ' ' . $model;
    }
    if ($title === $deviceType) {
        $title = $name;
    }

    $title = normalizeSpaces($title);
    if (!preg_match('~[\.!\?]$~u', $title)) {
        $title .= '.';
    }

    return $title;
}

function rewriteProductBlock(string $block, int &$changedCount, bool $useOrig, array $articleToShortDesc): string
{
    $sx = null;

    libxml_use_internal_errors(true);
    try {
        $sx = simplexml_load_string($block);
    } catch (Throwable $e) {
        $sx = null;
    }
    libxml_clear_errors();

    $name = '';
    $orig = '';
    $article = '';
    $params = [];

    if ($sx instanceof SimpleXMLElement) {
        $name = trim((string)$sx->name);
        $article = trim((string)$sx->article);
        $orig = trim((string)$sx->short_description);

        if (isset($sx->card_params) && isset($sx->card_params->param)) {
            foreach ($sx->card_params->param as $p) {
                $k = trim((string)$p->name);
                $v = trim((string)$p->value);
                if ($k !== '' && $v !== '') {
                    $params[$k] = $v;
                }
            }
        }
    } else {
        if (preg_match('~<name>(.*?)</name>~su', $block, $m)) {
            $name = trim(strip_tags((string)$m[1]));
        }
        if (preg_match('~<article>(.*?)</article>~su', $block, $m)) {
            $article = trim(strip_tags((string)$m[1]));
        }
        if (preg_match('~<short_description>(.*?)</short_description>~su', $block, $m)) {
            $orig = trim((string)$m[1]);
        }
    }

    $name = normalizeSpaces($name);
    $orig = normalizeSpaces($orig);
    $article = normalizeSpaces($article);

    $deviceType = detectDeviceType($name);
    $model = extractModelFromName($name);

    $newDesc = '';
    if ($useOrig && $orig !== '') {
        // В режиме переформулировки НЕ добавляем новых утверждений из параметров,
        // чтобы не появлялись свойства, которых не было в исходном описании.
        $newDesc = rewriteTextOffline($orig);
    } elseif ($useOrig && $orig === '') {
        // Если описание отсутствует у конкретного товара, пробуем взять базовое описание
        // по такому же ARTICLE у другого товара в этом же файле.
        $fallback = '';
        if ($article !== '' && isset($articleToShortDesc[$article])) {
            $fallback = normalizeSpaces((string)$articleToShortDesc[$article]);
        }
        if ($fallback !== '') {
            $newDesc = rewriteTextOffline($fallback);
        } else {
            // Если исходного описания нет нигде — делаем максимально нейтральную строку,
            // не добавляя назначений/обещаний.
            $newDesc = buildNeutralFromName($name);
        }
    } else {
        // Шаблонный режим (или если исходное описание пустое).
        $body = $deviceType . ' ' . ($model !== '' ? $model : $name) . '. ' . buildFallbackBody($name);
        $paramsSentence = buildParamsSentence($params);
        $newDesc = normalizeSpaces(trim($body . ' ' . $paramsSentence));
    }

    $escaped = htmlspecialchars($newDesc, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $newBlock = preg_replace('~(<short_description>)(.*?)(</short_description>)~su', '$1' . $escaped . '$3', $block, 1, $replaced);

    if ((int)$replaced > 0) {
        $changedCount++;
        return (string)$newBlock;
    }

    return $block;
}

$run = (int)getParam('run', 0);
$apply = (int)getParam('apply', 0);
$limit = (int)getParam('limit', 0);
$useOrig = (int)getParam('use_orig', 1);

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    $resolved = realpath(__DIR__ . '/../..');
    if ($resolved === false || !is_dir($resolved)) {
        exit("Не удалось определить DOCUMENT_ROOT\n");
    }
    $docRoot = $resolved;
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
}

$defaultIn = $docRoot . '/local/owenkomplekt_izmeriteli_regulyatory.xml';
$inPath = (string)getParam('in', $defaultIn);
$outPath = (string)getParam('out', $inPath . '.rewritten.xml');

if (!$run) {
    echo "Скрипт рерайта short_description готов. По умолчанию пишет в новый файл.\n";
    echo "CLI пример: php rewrite_short_descriptions.php --run=1 --limit=10\n";
    echo "CLI применить в файл: php rewrite_short_descriptions.php --run=1 --out=... --apply=1\n";
    echo "WEB пример: /local/scripts/rewrite_short_descriptions.php?run=1&limit=10\n";
    echo "Параметры: run=1, in=PATH, out=PATH, apply=1 (разрешить перезапись out, если совпадает), limit=N (0=все), use_orig=1 (по умолчанию: переформулировать исходный short_description), use_orig=0 (сгенерировать текст из name + card_params).\n";
    exit;
}

if (!file_exists($inPath)) {
    exit("Не найден входной файл: {$inPath}\n");
}

$realIn = realpath($inPath) ?: $inPath;
$realOut = realpath($outPath) ?: $outPath;

if ($realIn === $realOut && !$apply) {
    exit("out совпадает с in. Для перезаписи укажи apply=1\n");
}

$xml = file_get_contents($inPath);
if (!is_string($xml) || $xml === '') {
    exit("Не удалось прочитать входной файл или он пустой\n");
}

$pattern = '~<product\b[^>]*>.*?</product>~su';
if (!preg_match_all($pattern, $xml, $matches, PREG_OFFSET_CAPTURE)) {
    exit("Не найдено ни одного блока <product>\n");
}

$blocks = $matches[0];
$total = count($blocks);

// Для режима use_orig: собираем справочник ARTICLE -> непустой short_description,
// чтобы можно было подставлять его в товары-дубли с пустым описанием.
$articleToShortDesc = [];
foreach ($blocks as $b) {
    $block = (string)$b[0];
    [$a, $sd] = extractArticleAndShortDescription($block);
    if ($a === '' || $sd === '') {
        continue;
    }
    if (!isset($articleToShortDesc[$a])) {
        $articleToShortDesc[$a] = $sd;
    }
}

$pieces = [];
$pos = 0;
$processed = 0;
$changed = 0;

foreach ($blocks as $b) {
    $block = (string)$b[0];
    $offset = (int)$b[1];

    $pieces[] = substr($xml, $pos, $offset - $pos);

    if ($limit > 0 && $processed >= $limit) {
        $pieces[] = $block;
    } else {
        $pieces[] = rewriteProductBlock($block, $changed, $useOrig === 1, $articleToShortDesc);
        $processed++;
    }

    $pos = $offset + strlen($block);
}

$pieces[] = substr($xml, $pos);

$outXml = implode('', $pieces);

$bytes = file_put_contents($outPath, $outXml);
if ($bytes === false) {
    exit("Не удалось записать выходной файл: {$outPath}\n");
}

echo "DONE\n";
echo "in={$inPath}\n";
echo "out={$outPath}\n";
echo "products_total={$total}\n";
echo "products_processed={$processed}\n";
echo "short_description_changed={$changed}\n";
