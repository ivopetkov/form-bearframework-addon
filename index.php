<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;
use IvoPetkov\BearFrameworkAddons\Form;

$app = App::get();
$context = $app->contexts->get(__DIR__);

$context->classes
    ->add('IvoPetkov\BearFrameworkAddons\Form', 'classes/Form.php')
    ->add('IvoPetkov\BearFrameworkAddons\Form\*', 'classes/Form/*.php');

$context->assets
    ->addDir('assets/public');

$app->localization
    ->addDictionary('en', function () use ($context) {
        return include $context->dir . '/locales/en.php';
    })
    ->addDictionary('bg', function () use ($context) {
        return include $context->dir . '/locales/bg.php';
    })
    ->addDictionary('ru', function () use ($context) {
        return include $context->dir . '/locales/ru.php';
    });

$app->components
    ->addAlias('form', 'file:' . $context->dir . '/components/form.php');

$app->routes
    ->add('POST /-ivopetkov-form-files-upload/', function () use ($app) {
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
                copy($fileItem->filename, $newFilepath); // rename() - Cannot rename a file across wrapper types
                unlink($fileItem->filename);
                $response[] = [
                    'value' => $fileItem->value,
                    'filename' => $filename,
                    'size' => $fileItem->size,
                    'type' => $fileItem->type
                ];
            }
        }
        return new App\Response\JSON(json_encode($response));
    });

$app->serverRequests
    ->add('ivopetkov-form', function ($data, \BearFramework\App\Response $response) use ($app) {
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

                    $hiddenElements = $form->dependencies->getHiddenElements($values);
                    foreach ($hiddenElements as $hiddenElement) {
                        $values[$hiddenElement] = '';
                    }

                    $errorsList = [];
                    if (!$form->constraints->validate($values, $errorsList, $hiddenElements)) {
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
                    }
                    return json_encode([
                        'status' => '1',
                        'result' => $returnValue
                    ]);
                }
            }
        }
    });

$app->clientPackages
    ->add('form', function (IvoPetkov\BearFrameworkAddons\ClientPackage $package) use ($context) {
        $code = include $context->dir . '/assets/form.min.js.php';
        //$code = file_get_contents($context->dir . '/dev/form.js');
        $package->addJSCode($code);

        $code = 'form[data-form-id][disabled]{position:relative;}'
            . 'form[data-form-id][disabled],form[data-form-id][disabled] input, form[data-form-id][disabled] select,form[data-form-id][disabled] textarea{user-select:none;-moz-user-select:none;-khtml-user-select:none;-webkit-user-select:none;-o-user-select:none;pointer-events:none;}'
            . 'form[data-form-id][disabled]:before{content:"";display:block;position:absolute;width:100%;height:100%;}';
        $package->addCSSCode($code);

        $package->get = 'return ivoPetkov.bearFrameworkAddons.form;';
    })
    ->add('-form-submit', function (IvoPetkov\BearFrameworkAddons\ClientPackage $package) use ($app, $context) {
        //$package->addJSCode(file_get_contents($context->dir . '/assets/public/form-submit.js'));
        $package->addJSFile($context->assets->getURL('assets/public/form-submit.min.js', ['cacheMaxAge' => 999999999, 'version' => 7, 'robotsNoIndex' => true]));

        $getTooltipData = function ($style = '') use ($package) { //$style = 'background:rgba(255,0,0,.8);arrow-size:8px;';
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
            $style = '.' . $tooltipClassName . '{' . $elementStyle . '}'
                . '.' . $tooltipClassName . ':before{' . $elementBeforeStyle . '}';

            $package->addCSSCode($style);

            return [
                'className' => $tooltipClassName,
                'arrowSize' => $arrowSize
            ];
        };

        $initializeData = [
            $getTooltipData(),
            $app->urls->get('/-ivopetkov-form-files-upload/')
        ];

        $package->get = 'ivoPetkov.bearFrameworkAddons.formSubmit.initialize(' . json_encode($initializeData) . ');return ivoPetkov.bearFrameworkAddons.formSubmit;';
    });
