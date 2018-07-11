<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

BearFramework\Addons::register('ivopetkov/form-bearframework-addon', __DIR__, [
    'require' => [
        'bearframework/localization-addon',
        'ivopetkov/html-server-components-bearframework-addon',
        'ivopetkov/server-requests-bearframework-addon',
        'ivopetkov/encryption-bearframework-addon'
    ]
]);
