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
$context = $app->context->get(__FILE__);

$context->classes
        ->add(Form::class, 'classes/Form.php')
        ->add(Form\Constraints::class, 'classes/Form/Constraints.php')
        ->add(Form\Internal\ErrorException::class, 'classes/Form/Internal/ErrorException.php');

$context->assets
        ->addDir('assets');

$app->components
        ->addAlias('form', 'file:' . $context->dir . '/components/form.php');

$app->serverRequests
        ->add('ivopetkov-form', function($data) use ($app) {
            if (isset($data['serverData'], $data['values'])) {
                $serverDataKey = $data['serverData'];
                if (preg_match('/^[a-f0-9]{32}$/', $serverDataKey) !== 1) {
                    return;
                }
                $tempData = $app->data->getValue('.temp/form/' . $serverDataKey);
                $serverData = $tempData !== null ? json_decode($tempData, true) : null;
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
                            $closure = \Closure::bind($form->onSubmit, $form);
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
