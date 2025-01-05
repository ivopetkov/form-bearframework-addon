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
     * @var callable|null 
     */
    public $onSubmit = null;

    /**
     *
     * @var callable|null 
     */
    public $onError = null;

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
     * @var \IvoPetkov\BearFrameworkAddons\Form\Transformers
     */
    public $transformers;

    /**
     * 
     */
    function __construct()
    {
        $this->constraints = new Form\Constraints();
        $this->dependencies = new Form\Dependencies();
        $this->transformers = new Form\Transformers();
    }

    /**
     * 
     * @param string $message
     * @throws \IvoPetkov\BearFrameworkAddons\Form\Internal\ErrorException
     */
    public function throwError(?string $message = null): void
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
    public function throwElementError(string $elementName, ?string $message = null): void
    {
        $exception = new Form\Internal\ErrorException('');
        $exception->elementName = $elementName;
        $exception->errorMessage = $message !== null ? $message : __('ivopetkov.form.error');
        throw $exception;
    }
}
