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
$context = $app->contexts->get(__FILE__);

$context->classes
        ->add('IvoPetkov\BearFrameworkAddons\Form', 'classes/Form.php')
        ->add('IvoPetkov\BearFrameworkAddons\Form\*', 'classes/Form/*.php');

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
        ->add('POST /-ivopetkov-form-files-upload/', function() use ($app) {
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
                        }
                        return json_encode([
                            'status' => '1',
                            'result' => $returnValue
                        ]);
                    }
                }
            }
        });

$app->clientShortcuts
        ->add('form', function(IvoPetkov\BearFrameworkAddons\ClientShortcut $shortcut) {
            $shortcut->requirements[] = [// taken from dev/form.js // file_get_contents(__DIR__ . '/dev/form.js')
                'type' => 'text',
                'value' => 'var ivoPetkov=ivoPetkov||{};ivoPetkov.bearFrameworkAddons=ivoPetkov.bearFrameworkAddons||{};ivoPetkov.bearFrameworkAddons.form=ivoPetkov.bearFrameworkAddons.form||function(){var g=[],h=function(a){return"undefined"!==typeof g[a]?document.querySelector(\'form[data-form-id="\'+a+\'"]\'):null},l=function(a){var d=h(a);if(null!==d){var b=g[a];if(1!==b.status){var e=function(a,b){if("function"===typeof Event)var c=new Event(a);else c=document.createEvent("Event"),c.initEvent(a,!1,!1);if("undefined"!==typeof b)for(var e in b)c[e]=b[e];e=!1;"true"===d.getAttribute("disabled")&&(d.removeAttribute("disabled"),e=!0);c=d.dispatchEvent(c);e&&d.setAttribute("disabled","true");return c};e("beforesubmit")&&(b.status=1,k(a,!0),e("submitstart"),clientShortcuts.get("-form-submit").then(function(c){c.submit(d,b,e,function(){b.status=0;k(a,!1)})}))}}},k=function(a,d){var b=h(a);if(null!==b){d?b.setAttribute("disabled","true"):b.removeAttribute("disabled");b=b.querySelectorAll("input, select, textarea");for(var e=b.length,c=0;c<e;c++){var f=b[c];d?(f.setAttribute("disabled","true"),f.ipfrmds=1):"undefined"!==typeof f.ipfrmds&&(f.removeAttribute("disabled"),delete f.ipfrmds)}}};return{initialize:function(a){var d=a[0];g[d]={serverData:a[1],errorMessage:a[2],status:0};var b=h(d);null!==b&&(b.submit=function(){l(d)},a=function(a){var c=b.getAttribute("on"+a);null!==c&&b.addEventListener(a,function(a){(new Function("return function(event){"+c+"}"))().bind(this)(a)})},a("beforesubmit"),a("submitstart"),a("submitend"),a("submitsuccess"),a("submiterror"))},submit:l}}();',
                'mimeType' => 'text/javascript'
            ];
            $shortcut->requirements[] = [
                'type' => 'text',
                'value' => 'form[data-form-id][disabled]{position:relative;}'
                . 'form[data-form-id][disabled],form[data-form-id][disabled] input, form[data-form-id][disabled] select,form[data-form-id][disabled] textarea{user-select:none;-moz-user-select:none;-khtml-user-select:none;-webkit-user-select:none;-o-user-select:none;pointer-events:none;}'
                . 'form[data-form-id][disabled]:before{content:"";display:block;position:absolute;width:100%;height:100%;}',
                'mimeType' => 'text/css'
            ];
            $shortcut->get = 'return ivoPetkov.bearFrameworkAddons.form;';
        })
        ->add('-form-submit', function(IvoPetkov\BearFrameworkAddons\ClientShortcut $shortcut) use ($app, $context) {
            $shortcut->requirements[] = [
                'type' => 'file',
                'url' => $context->assets->getURL('assets/form-submit.min.js', ['cacheMaxAge' => 999999999, 'version' => 2, 'robotsNoIndex' => true]),
                'mimeType' => 'text/javascript'
            ];

            $getTooltipData = function($style = '') use ($shortcut) { //$style = 'background:rgba(255,0,0,.8);arrow-size:8px;';
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

                $shortcut->requirements[] = [
                    'type' => 'text',
                    'value' => $style,
                    'mimeType' => 'text/css'
                ];

                return [
                    'className' => $tooltipClassName,
                    'arrowSize' => $arrowSize
                ];
            };

            $initializeData = [
                $getTooltipData(),
                $app->urls->get('/-ivopetkov-form-files-upload/')
            ];

            $shortcut->init = 'ivoPetkov.bearFrameworkAddons.formSubmit.initialize(' . json_encode($initializeData) . ');';
            $shortcut->get = 'return ivoPetkov.bearFrameworkAddons.formSubmit;';
        });

