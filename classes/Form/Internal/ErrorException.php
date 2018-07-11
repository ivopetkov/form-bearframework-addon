<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Form\Internal;

/**
 * Error exception
 */
class ErrorException extends \Exception
{

    public $errorMessage = null;
    public $elementName = null;

}
