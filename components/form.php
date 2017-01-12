<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016-2017 Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->getContext(__FILE__);

$id = md5(uniqid() . 'form');
$component->src = "file:" . $component->filename;
unset($component->filename);
$componentAsHTML = (string) $component;
$form = new IvoPetkov\BearFrameworkAddons\Form();
$output = $app->components->process($componentAsHTML, ['variables' => ['form' => $form]]);
$domDocument = new IvoPetkov\HTML5DOMDocument();
$domDocument->loadHTML($output);

$formElement = $domDocument->querySelector('form');
if ($formElement) {
    $formElement->setAttribute('data-form-id', $id);
}
$serverData = [
    'componentHTML' => $componentAsHTML
];

$encodedServerData = json_encode($serverData);
$serverDataKey = md5($encodedServerData);
$temp = $app->data->get([
    'key' => '.temp/form/' . $serverDataKey,
    'result' => ['key']
        ]);
if (!isset($temp['key'])) {
    $app->data->set([
        'key' => '.temp/form/' . $serverDataKey,
        'body' => $encodedServerData
    ]);
}

$style = 'background:rgba(0,0,0,.8);arrow-size:8px;';

$getTooltipData = function($style) use ($domDocument) {
    $arrowSize = null;
    $backgroundColor = null;
    $styleParts = explode(';', $style);
    $arrowSizeIndex = null;
    foreach ($styleParts as $index => $stylePart) {
        $stylePart = explode(':', $stylePart);
        if (isset($stylePart[0], $stylePart[1])) {
            if ($stylePart[0] === 'arrow-size') {
                $arrowSize = $stylePart[1];
                $arrowSizeIndex = $index;
            } elseif ($stylePart[0] === 'background') {
                $backgroundColor = $stylePart[1]; // todo find color
            } elseif ($stylePart[0] === 'background-color') {
                $backgroundColor = $stylePart[1];
            }
        }
    }
    if ($arrowSizeIndex !== null) {
        unset($styleParts[$arrowSizeIndex]);
        $style = implode(';', $styleParts);
    }
    if ($arrowSize === null) {
        $arrowSize = '8px';
    }
    if ($backgroundColor === null) {
        $backgroundColor = 'rgba(0,0,0,.9)';
    }

    $elementStyle = 'display:inline-block;background:' . $backgroundColor . ';border-radius:2px;font-family:Arial;font-size:14px;color:#fff;padding:13px 15px;position:absolute;z-index:10000;max-width:220px;user-select:none;-moz-user-select:none;-khtml-user-select:none;-webkit-user-select:none;-o-user-select:none;cursor:default;' . $style;
    $elementBeforeStyle = 'border:solid;border-color:' . $backgroundColor . ' transparent;border-width:' . $arrowSize . ' ' . $arrowSize . ' 0 ' . $arrowSize . ';bottom:-' . $arrowSize . ';content:"";left:calc(50% - ' . $arrowSize . ');position:absolute;';

    $tooltipClassName = 'ipform' . md5($elementStyle . $elementBeforeStyle);
    $domDocument->insertHTML('<html><head><style>'
            . '.' . $tooltipClassName . '{' . $elementStyle . '}'
            . '.' . $tooltipClassName . ':before{' . $elementBeforeStyle . '}'
            . '</style></head></html>');
    return [
        'className' => $tooltipClassName,
        'arrowSize' => $arrowSize
    ];
};

$initializeData = [
    'serverData' => $serverDataKey,
    'errorTooltipData' => $getTooltipData('')
];

$html = '<script>var script=document.createElement(\'script\');script.src=\'' . $context->assets->getUrl('assets/form.js') . '\';script.onload=function(){ivoPetkov.bearFrameworkAddons.form.initialize(\'' . $id . '\',' . json_encode($initializeData) . ');};document.head.appendChild(script);</script>';

$domDocument->insertHTML($html);
echo $domDocument->saveHTML();
