<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFramework\Addons;

/**
 * Form
 */
class Form
{

    public $onSubmit = null;
    public $_internal_onSubmitResult = null;

    public function showSuccess($message)
    {
        $this->_internal_onSubmitResult = [1, $message];
    }

    public function showError($message, $elementID = null)
    {
        $this->_internal_onSubmitResult = [0, $message, $elementID];
    }

}
