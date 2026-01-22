<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Config\Option;

$moduleId = 'dw.deluxe';
$optionName = 'dw_settings';
$flagKey = 'TEMPLATE_USE_AUTO_SAVE_PRICE';
$flagValue = 'Y';
$siteId = 's1';

echo "Module: {$moduleId}\nOption: {$optionName}\nKey: {$flagKey}\n\n";

$raw = Option::get($moduleId, $optionName, '');
echo "Raw type: " . gettype($raw) . "\n";
echo "Raw length: " . (is_string($raw) ? strlen($raw) : 0) . "\n";

$settings = [];
$parsedOk = false;
$hadRaw = is_string($raw) && $raw !== '';

if ($hadRaw) {
    $tmp = @unserialize($raw, ['allowed_classes' => false]);
    if (is_array($tmp)) {
        $settings = $tmp;
        $parsedOk = true;
        echo "Parse: OK (serialized array)\n";
        echo "Top-level keys: " . implode(", ", array_map("strval", array_keys($settings))) . "\n";
    } else {
        echo "Parse: FAILED (dw_settings is not a serialized array)\n";
    }
} else {
    echo "Parse: SKIPPED (dw_settings is empty)\n";
}

if (!$parsedOk && $hadRaw) {
    echo "\nERROR: Not changing settings because dw_settings cannot be parsed safely.\n";
    exit(3);
}

$backupName = $optionName . '_backup_' . date('Ymd_His');
Option::set($moduleId, $backupName, is_string($raw) ? $raw : '');
echo "Backup saved to option: {$backupName}\n";


$prevGlobal = $settings[$flagKey] ?? null;
$prevSiteVal = null;
$prevSiteType = null;

if (isset($settings[$siteId])) {
    $prevSiteType = gettype($settings[$siteId]);
    if (is_array($settings[$siteId])) {
        $prevSiteVal = $settings[$siteId][$flagKey] ?? null;
    }
}

echo "Site: {$siteId}\n";
echo "Previous global {$flagKey}: " . var_export($prevGlobal, true) . "\n";
echo "Previous {$siteId} type: " . var_export($prevSiteType, true) . "\n";
echo "Previous {$siteId} {$flagKey}: " . var_export($prevSiteVal, true) . "\n";


if (!isset($settings[$siteId])) {
    $settings[$siteId] = array();
}

if (!is_array($settings[$siteId])) {
    echo "\nERROR: Cannot set {$flagKey} for site {$siteId} because dw_settings['{$siteId}'] is not an array.\n";
    echo "No changes were made.\n";
    exit(4);
}

$settings[$siteId][$flagKey] = $flagValue;


$newRaw = serialize($settings);
Option::set($moduleId, $optionName, $newRaw);

echo "\nWrite: DONE\n";
echo "Write mode: " . ($parsedOk ? "updated existing settings array" : "created NEW settings array (check WARNING above)") . "\n";


$afterRaw = Option::get($moduleId, $optionName, '');
$after = @unserialize($afterRaw, ['allowed_classes' => false]);

echo "\nVerify:\n";
if (!is_array($after)) {
    echo "ERROR: After-save dw_settings is not a serialized array. Something is wrong.\n";
    exit(1);
}


$afterGlobalVal = $after[$flagKey] ?? null;
$afterSiteVal = null;
$afterSiteType = null;

if (isset($after[$siteId])) {
    $afterSiteType = gettype($after[$siteId]);
    if (is_array($after[$siteId])) {
        $afterSiteVal = $after[$siteId][$flagKey] ?? null;
    }
}

echo "After global {$flagKey}: " . var_export($afterGlobalVal, true) . "\n";
echo "After {$siteId} type: " . var_export($afterSiteType, true) . "\n";
echo "After {$siteId} {$flagKey}: " . var_export($afterSiteVal, true) . "\n";

if ($afterSiteVal === $flagValue) {
    echo "\nOK: {$siteId} {$flagKey}={$flagValue}\n";
} else {
    echo "\nERROR: {$siteId} {$flagKey} was not set correctly\n";
    exit(2);
}

echo "\nDone.\n";
