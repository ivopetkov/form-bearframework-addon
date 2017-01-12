<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016-2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Form;

/**
 * Constraints
 */
class Constraints
{

    /**
     *
     * @var array 
     */
    private $data = [];

    /**
     * Marks an element as required
     * 
     * @param string $elementName The element name
     * @param string $errorMessage Error message
     */
    public function setRequired(string $elementName, string $errorMessage = null): void
    {
        if ($errorMessage === null) {
            $errorMessage = 'This field is required';
        }
        $this->data[] = ['required', $errorMessage, $elementName];
    }

    /**
     * Sets a minimum length requirement for an element
     * 
     * @param string $elementName The element name
     * @param int $minLength
     * @param string $errorMessage Error message
     */
    public function setMinLength(string $elementName, int $minLength, string $errorMessage = null): void
    {
        if ($errorMessage === null) {
            $errorMessage = 'The length of this field must be atleast ' . $minLength . ' characters';
        }
        $this->data[] = ['minLength', $errorMessage, $elementName, $minLength];
    }

    /**
     * Sets a maximum length requirement for an element
     * 
     * @param string $elementName The element name
     * @param int $maxLength
     * @param string $errorMessage Error message
     */
    public function setMaxLength(string $elementName, int $maxLength, string $errorMessage = null): void
    {
        if ($errorMessage === null) {
            $errorMessage = 'The length of this field must be atmost ' . $maxLength . ' characters';
        }
        $this->data[] = ['maxLength', $errorMessage, $elementName, $maxLength];
    }

    /**
     * Requires the element value to be a valid email address
     * 
     * @param string $elementName The element name
     * @param string $errorMessage Error message
     */
    public function setEmail(string $elementName, string $errorMessage = null): void
    {
        if ($errorMessage === null) {
            $errorMessage = 'This is not a valid email address';
        }
        $this->data[] = ['email', $errorMessage, $elementName];
    }

    /**
     * Performs a regular expression validation
     * 
     * @param string $elementName The element name
     * @param string $regularExpression
     * @param string $errorMessage Error message
     */
    public function setRegularExpression(string $elementName, string $regularExpression, string $errorMessage = null): void
    {
        if ($errorMessage === null) {
            $errorMessage = 'This is not a valid value';
        }
        $this->data[] = ['regExp', $errorMessage, $elementName, $regularExpression];
    }

    /**
     * Validates the values passed
     * 
     * @param array $values The values to checks
     * @param array $errorsList List of validation errors
     * @return bool TRUE if no validation errors found. FALSE otherwise.
     */
    public function validate(array $values, array &$errorsList): bool
    {
        $hasErrors = false;
        foreach ($this->data as $item) {
            $type = $item[0];
            $errorMessage = $item[1];
            $elementName = $item[2];
            $value = isset($values[$elementName]) ? (string) $values[$elementName] : '';
            $hasError = false;
            if ($type === 'required') {
                if (strlen($value) === 0) {
                    $hasError = true;
                }
            } elseif ($type === 'minLength') {
                if (strlen($value) < $item[3]) {
                    $hasError = true;
                }
            } elseif ($type === 'maxLength') {
                if (strlen($value) > $item[3]) {
                    $hasError = true;
                }
            } elseif ($type === 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $hasError = true;
                }
            } elseif ($type === 'regExp') {
                if (!filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $item[3]]])) {
                    $hasError = true;
                }
            }
            if ($hasError) {
                $errorsList[] = [
                    'elementName' => $elementName,
                    'errorMessage' => $errorMessage
                ];
                $hasErrors = true;
            }
        }
        return !$hasErrors;
    }

}
