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
$options = $app->addons->get('ivopetkov/form-bearframework-addon')->options;

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
            for ($i = 0; $i < 100000; $i++) {
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
        ->add('ivopetkov-form', function($data, \BearFramework\App\Response $response) use ($app) {
            if (isset($data['serverData'], $data['values'])) {
                $serverData = $data['serverData'];
                $encryptedServerDataHash = substr($serverData, 0, 32);
                try {
                    $encryptedServerData = gzuncompress($app->encryption->decrypt(base64_decode(substr($serverData, 32))));
                } catch (\Exception $e) {
                    return;
                }
                if (md5($encryptedServerData) !== $encryptedServerDataHash) {
                    return;
                }
                $encryptedServerData = json_decode($encryptedServerData, true);
                if (is_array($encryptedServerData) && isset($encryptedServerData[0], $encryptedServerData[1]) && $encryptedServerData[0] === 'form') {
                    $componentHTML = $encryptedServerData[1];
                    $form = new Form();
                    $app->components->process($componentHTML, ['variables' => ['form' => $form]]);
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
                            $returnValue = call_user_func($closure, new ArrayObject($values), $response);
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
                                    'message' => __('ivopetkov.form.Error occurred. Please, try again later.')
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
