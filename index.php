<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;
use IvoPetkov\BearFramework\Addons\Form;

$context->classes->add(Form::class, 'src/Form.php');

$app->components->addAlias('form', 'file:' . $context->dir . '/components/form.php');
$context->assets->addDir('assets');

$app->routes->add('/', function() use ($app) {
    if (isset($_POST['ivopetkov-form-bearframework-addon'])) {
        $result = [];
        $serverData = json_decode($_POST['ivopetkov-form-bearframework-addon'], true);
        if (is_array($serverData) && isset($serverData['filename'])) {
            $form = new IvoPetkov\BearFramework\Addons\Form();
            $app->components->processFile($serverData['filename'], [], '', ['form' => $form]);
            if (is_callable($form->onSubmit)) {
                $values = $_POST;
                unset($values['ivopetkov-form-bearframework-addon']);
                call_user_func($form->onSubmit, new ArrayObject($values));
                $result = $form->_internal_onSubmitResult;
                if (is_array($result) && isset($result[0])) {
                    if ($result[0] === 1) {
                        $result = ['status' => 1, 'message' => isset($result[1]) ? (string) $result[1] : ''];
                    } elseif ($result[0] === 0) {
                        $result = ['status' => 0, 'message' => isset($result[1]) ? (string) $result[1] : '', 'elementID' => isset($result[2]) ? (string) $result[2] : ''];
                    }
                }
            }
        }
        return new App\Response\JSON(json_encode($result));
    }
}, ['POST']);
