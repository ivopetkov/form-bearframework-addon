<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016-2017 Ivo Petkov
 * Free to use under the MIT license.
 */

BearFramework\Addons::register('ivopetkov/form-bearframework-addon', __DIR__, [
    'require' => [
        'ivopetkov/html-server-components-bearframework-addon',
        'ivopetkov/server-requests-bearframework-addon'
    ]
]);
