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
$context = $app->contexts->get(__DIR__);

$id = md5(uniqid() . 'form');
$component->src = "file:" . $component->filename;
unset($component->filename);
$componentHTML = (string) $component;
$form = new IvoPetkov\BearFrameworkAddons\Form();
$output = $app->components->process($componentHTML, ['variables' => ['form' => $form], 'recursive' => false]);
$domDocument = new HTML5DOMDocument();
$domDocument->loadHTML($output, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);

$formElement = $domDocument->querySelector('form');
if ($formElement !== null) {
    $formElement->setAttribute('data-form-id', $id);
    $formElement->setAttribute('onsubmit', "this.submit();event.preventDefault();return false;");
}

$serverData = json_encode(['form', $componentHTML]);
$encryptedServerData = md5($serverData) . base64_encode($app->encryption->encrypt(gzcompress($serverData)));

$initializeData = [
    $id,
    $encryptedServerData,
    __('ivopetkov.form.error'),
    $form->dependencies->getClientData()
];

$html = '<head><link rel="client-packages-embed" name="form"></head>';
$html .= '<body><script>clientPackages.get(\'form\').then(function(form){form.initialize(' . json_encode($initializeData) . ');});</script></body>';
$domDocument->insertHTML($html);
$html = $domDocument->saveHTML();
$html = $form->dependencies->apply($html);
echo $app->components->process($html);
