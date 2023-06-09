<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

/**
 * Form
 */
class Form
{

    /**
     *
     * @var string 
     */
    public $onSubmit = null;

    /**
     *
     * @var \IvoPetkov\BearFrameworkAddons\Form\Constraints
     */
    public $constraints;

    /**
     *
     * @var \IvoPetkov\BearFrameworkAddons\Form\Dependencies
     */
    public $dependencies;

    /**
     * 
     */
    function __construct()
    {
        $this->constraints = new Form\Constraints();
        $this->dependencies = new Form\Dependencies();
    }

    /**
     * 
     * @param string $message
     * @throws \IvoPetkov\BearFrameworkAddons\Form\Internal\ErrorException
     */
    public function throwError(string $message = null): void
    {
        $exception = new Form\Internal\ErrorException('');
        $exception->errorMessage = $message !== null ? $message : __('ivopetkov.form.error');
        throw $exception;
    }

    /**
     * 
     * @param string $elementName
     * @param string $message
     * @throws \IvoPetkov\BearFrameworkAddons\Form\Internal\ErrorException
     */
    public function throwElementError(string $elementName, string $message = null): void
    {
        $exception = new Form\Internal\ErrorException('');
        $exception->elementName = $elementName;
        $exception->errorMessage = $message !== null ? $message : __('ivopetkov.form.error');
        throw $exception;
    }
}
