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

$app->localization
        ->addDictionary('en', function() use ($context) {
            return include $context->dir . '/locales/en.php';
        })
        ->addDictionary('bg', function() use ($context) {
            return include $context->dir . '/locales/bg.php';
        })
        ->addDictionary('ru', function() use ($context) {
            return include $context->dir . '/locales/ru.php';
        });

$app->components
        ->addAlias('form', 'file:' . $context->dir . '/components/form.php');

$app->routes
        ->add('/ivopetkov-form-files-upload/', function() use ($app) {
            $response = [];
            for ($i = 0; $i < 100; $i++) {
                $fileItem = $app->request->formData->getFile('file' . $i);
                if ($fileItem === null) {
                    break;
                }
                if (is_file($fileItem->filename)) {
                    $filename = md5(uniqid() . $fileItem->filename) . '.tmp';
                    $newFilepath = $app->data->getFilename('.temp/form/files/' . $filename);
                    $pathInfo = pathinfo($newFilepath);
                    if (isset($pathInfo['dirname'])) {
                        if (!is_dir($pathInfo['dirname'])) {
                            mkdir($pathInfo['dirname'], 0777, true);
                        }
                    }
                    rename($fileItem->filename, $newFilepath);
                    $response[] = [
                        'value' => $fileItem->value,
                        'filename' => $filename,
                        'size' => $fileItem->size,
                        'type' => $fileItem->type
                    ];
                }
            }
            return new App\Response\JSON(json_encode($response));
        }, ['POST']);

$app->serverRequests
        ->add('ivopetkov-form', function($data) use ($app) {
            if (isset($data['serverData'], $data['values'])) {
                $serverDataKey = $data['serverData'];
                if (preg_match('/^[a-f0-9]{32}$/', $serverDataKey) !== 1) {
                    return;
                }
                $tempData = \BearCMS\Internal\Data::getValue('.temp/form/' . $serverDataKey);
                $serverData = $tempData !== null ? json_decode($tempData, true) : null;
                if (!is_array($serverData)) {
                    return;
                }
                if (isset($serverData['componentHTML'])) {
                    $form = new Form();
                    $app->components->process($serverData['componentHTML'], ['variables' => ['form' => $form]]);
                    if (is_callable($form->onSubmit)) {
                        $tempValues = json_decode($data['values'], true);
                        $values = [];
                        if (is_array($tempValues)) {
                            foreach ($tempValues as $tempValueName => $tempValueData) {
                                if (isset($tempValueData['type'], $tempValueData['value'])) {
                                    if ($tempValueData['type'] === 'file') {
                                        $filesData = json_decode($tempValueData['value'], true);
                                        if (is_array($filesData)) {
                                            $okCount = 0;
                                            foreach ($filesData as $i => $fileData) {
                                                if (is_array($fileData) && isset($fileData['filename']) && preg_match('/^[a-f0-9]{32}\.tmp$/', $fileData['filename']) === 1) {
                                                    $okCount++;
                                                    $filesData[$i]['filename'] = $app->data->getFilename('.temp/form/files/' . $fileData['filename']);
                                                }
                                            }
                                            if (sizeof($filesData) === $okCount) {
                                                $values[$tempValueName] = json_encode($filesData);
                                            }
                                        }
                                        if (!isset($values[$tempValueName])) {
                                            $values[$tempValueName] = '';
                                        }
                                    } else {
                                        $values[$tempValueName] = $tempValueData['value'];
                                    }
                                }
                            }
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
                        } catch (\Exception $e) {
                            \BearFramework\App\ErrorHandler::handleException($e);
                            return json_encode([
                                'status' => '0',
                                'error' => [
                                    'message' => 'Error occurred. Please, try again later.'
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
