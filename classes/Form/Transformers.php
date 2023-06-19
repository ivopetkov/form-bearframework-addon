<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Form;

/**
 * Constraints
 */
class Transformers
{

    /**
     *
     * @var array 
     */
    private $data = [];

    /**
     * Trims the value
     * 
     * @param string $elementName The element name
     * @return self Returns a reference to itself.
     */
    public function addTrim(string $elementName): self
    {
        $this->data[] = ['trim', $elementName];
        return $this;
    }

    /**
     * Converts to lower case
     * 
     * @param string $elementName The element name
     * @return self Returns a reference to itself.
     */
    public function addToLowerCase(string $elementName): self
    {
        $this->data[] = ['toLowerCase', $elementName];
        return $this;
    }

    /**
     * Converts to upper case
     * 
     * @param string $elementName The element name
     * @return self Returns a reference to itself.
     */
    public function addToUpperCase(string $elementName): self
    {
        $this->data[] = ['toUpperCase', $elementName];
        return $this;
    }

    /**
     * Calls a custom transformer
     * 
     * @param string $elementName The element name
     * @param callable $callback
     * @return self
     */
    public function addCustom(string $elementName, callable $callback): self
    {
        $this->data[] = ['custom', $elementName, $callback];
        return $this;
    }

    /**
     * Applies the transformers
     *
     * @param array $values
     * @return array
     */
    public function apply(array $values): array
    {
        foreach ($this->data as $item) {
            $type = $item[0];
            $elementName = $item[1];
            $value = isset($values[$elementName]) ? (string) $values[$elementName] : '';
            if ($type === 'trim') {
                $value = trim($value);
            } elseif ($type === 'toLowerCase') {
                $value = mb_strtolower($value);
            } elseif ($type === 'toUpperCase') {
                $value = mb_strtoupper($value);
            } elseif ($type === 'custom') {
                $value = call_user_func($item[2], $value);
            }
            $values[$elementName] = $value;
        }
        return $values;
    }
}
