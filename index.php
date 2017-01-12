<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016-2017 Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;
use IvoPetkov\BearFrameworkAddons\Form;

$app = App::get();
$context = $app->getContext(__FILE__);

$context->classes->add(Form::class, 'classes/Form.php');
$context->classes->add(Form\Constraints::class, 'classes/Form/Constraints.php');
$context->classes->add(Form\Internal\ErrorException::class, 'classes/Form/Internal/ErrorException.php');

$app->components->addAlias('form', 'file:' . $context->dir . '/components/form.php');
$context->assets->addDir('assets');

$app->serverRequests->add('ivopetkov-form', function($data) use ($app) {
    if (isset($data['serverData']) && isset($data['values'])) {
        $serverDataKey = $data['serverData'];
        if (filter_var($serverDataKey, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-f0-9]{32}$/']]) === false) {
            return;
        }
        $tempData = $app->data->get([
            'key' => '.temp/form/' . $serverDataKey,
            'result' => ['body']
        ]);
        $serverData = null;
        if (isset($tempData['body'])) {
            $serverData = json_decode($tempData['body'], true);
        }
        if (!is_array($serverData)) {
            return;
        }

        if (isset($serverData['componentHTML'])) {
            $form = new Form();
            $app->components->process($serverData['componentHTML'], ['variables' => ['form' => $form]]);
            if (is_callable($form->onSubmit)) {
                $tempValues = $data['values'];
                $values = json_decode($tempValues, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $errorsList = [];
                if (!$form->constraints->validate($values, $errorsList)) {
                    return json_encode([
                        'status' => '0',
                        'error' => [
                            'message' => (string) $errorsList[0]['errorMessage'],
                            'element' => (string) $errorsList[0]['elementName'],
                        ]
                    ]);
                }

                try {
                    $closure = Closure::bind($form->onSubmit, $form);
                    $returnValue = call_user_func($closure, new ArrayObject($values));
                } catch (Form\Internal\ErrorException $e) {
                    return json_encode([
                        'status' => '0',
                        'error' => [
                            'message' => (string) $e->errorMessage,
                            'element' => (string) $e->elementName,
                        ]
                    ]);
                }
                return json_encode([
                    'status' => '1',
                    'result' => $returnValue
                ]);
            }
        }
    }
});
