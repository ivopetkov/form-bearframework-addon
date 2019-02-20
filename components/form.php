<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;
use IvoPetkov\HTML5DOMDocument;

$app = App::get();
$context = $app->contexts->get(__FILE__);
//$options = $app->addons->get('ivopetkov/form-bearframework-addon')->options;

$id = md5(uniqid() . 'form');
$component->src = "file:" . $component->filename;
unset($component->filename);
$componentHTML = (string) $component;
$form = new IvoPetkov\BearFrameworkAddons\Form();
$output = $app->components->process($componentHTML, ['variables' => ['form' => $form]]);
$domDocument = new HTML5DOMDocument();
$domDocument->loadHTML($output, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);

$formElement = $domDocument->querySelector('form');
if ($formElement) {
    $formElement->setAttribute('data-form-id', $id);
}
$formElement->setAttribute('onsubmit', "this.submit();event.preventDefault();return false;");

$serverData = json_encode(['form', $componentHTML]);
$encryptedServerData = md5($serverData) . base64_encode($app->encryption->encrypt(gzcompress($serverData)));

$style = 'background:rgba(255,0,0,.8);arrow-size:8px;';

$getTooltipData = function($style) use (&$domDocument) {
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

    $elementStyle = 'display:inline-block;background:' . $backgroundColor . ';border-radius:2px;font-family:Arial;font-size:14px;color:#fff;padding:13px 15px;position:absolute;z-index:10030000;max-width:220px;user-select:none;-moz-user-select:none;-khtml-user-select:none;-webkit-user-select:none;-o-user-select:none;cursor:default;text-align:center;' . $style;
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
    'serverData' => $encryptedServerData,
    'errorTooltipData' => $getTooltipData(''),
    'filesUploadUrl' => $app->urls->get('/ivopetkov-form-files-upload/'),
    'errorMessage' => __('ivopetkov.form.Error occurred. Please, try again later.')
];

$disableSubmitJs = 'var formElement = document.querySelector(\'form[data-form-id="' . $id . '"]\');if(formElement){formElement.submit=function(){};}';
$html = '<script>' . $disableSubmitJs . 'var script=document.createElement(\'script\');script.src=\'' . $context->assets->getURL('assets/form.min.js', ['cacheMaxAge' => 999999999, 'version' => 2]) . '\';script.onload=function(){ivoPetkov.bearFrameworkAddons.form.initialize(\'' . $id . '\',' . json_encode($initializeData) . ');};document.head.appendChild(script);</script>';

$domDocument->insertHTML($html);
echo $domDocument->saveHTML();
