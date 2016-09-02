<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

$id = md5(uniqid() . 'salt');

$form = new IvoPetkov\BearFramework\Addons\Form();
$output = $app->components->processFile($component->filename, [], '', ['form' => $form]);
$domDocument = new IvoPetkov\HTML5DOMDocument();
$domDocument->loadHTML($output);

$formElement = $domDocument->querySelector('form');
if ($formElement) {
    $formElement->setAttribute('onsubmit', 'return false;');
    $formElement->setAttribute('data-form-id', $id);
}
$initializeData = [
    'serverData' => [
        'filename' => $component->filename
    ],
    'submitUrl' => $app->request->base . '/'
];
$initializeJsCode = 'ivoPetkov.bearFramework.addons.form.initialize(\'' . $id . '\',' . json_encode($initializeData) . ');';
$domDocument->insertHTML('<body>'
        . '<script src="' . $context->assets->getUrl('assets/form.js') . '" />'
        . '<script>' . $initializeJsCode . '</script>'
        . '</body>');

$output = $domDocument->saveHTML();
echo $output;
