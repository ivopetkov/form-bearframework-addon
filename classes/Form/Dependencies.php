<?php

/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Form;

use IvoPetkov\HTML5DOMDocument;

/**
 * Dependencies
 */
class Dependencies
{

    /**
     *
     * @var array 
     */
    private $data = [];

    /**
     * 
     * @param string $elementName
     * @param array $dependency
     * @return self
     */
    public function setVisible(string $elementName, array $dependency): self
    {
        $this->data[] = ['visible', $elementName, $dependency];
        return $this;
    }

    /**
     * 
     * @param array $dependency
     * @param callable $getValue
     * @param callable $isChecked
     * @return boolean
     */
    private function checkDependency(array $dependency, callable $getValue, callable $isChecked): bool
    {
        if ($dependency[0] === 'AND' || $dependency[0] === 'OR') { // Complex dependency
            $isAnd = $dependency[0] === 'AND';
            $result = false;
            foreach ($dependency as $i => $_dependency) {
                if ($i > 0) {
                    if ($this->checkDependency($_dependency, $getValue, $isChecked)) {
                        if ($isAnd) {
                            $result = true;
                        } else {
                            $result = true;
                            break;
                        }
                    } else {
                        if ($isAnd) {
                            return false;
                        }
                    }
                }
            }
            return $result;
        } else { // Simple dependency
            $elementName = $dependency[0];
            $checkType = $dependency[1];
            if ($checkType === 'value') {
                $checkValue = $dependency[2];
                $elementValue = $getValue($elementName);
                if (is_array($checkValue)) {
                    if (array_search($elementValue, $checkValue) !== false) {
                        return true;
                    }
                } else {
                    if ($elementValue === $checkValue) {
                        return true;
                    }
                }
            } elseif ($checkType === 'checked') {
                if ($isChecked($elementName)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 
     * @param array $values
     * @return array
     */
    public function getHiddenElements(array $values): array
    {
        $getValue = function ($name) use ($values) {
            return isset($values[$name]) ? $values[$name] : null;
        };
        $isChecked = function ($name) use ($values) {
            return isset($values[$name]) && (int)$values[$name] > 0;
        };
        $result = [];
        foreach ($this->data as $rule) {
            $elementName = $rule[1];
            $dependency = $rule[2];
            if ($rule[0] === 'visible') {
                $visible = $this->checkDependency($dependency, $getValue, $isChecked);
                if (!$visible) {
                    $result[] = $elementName;
                }
            }
        }
        return $result;
    }

    /**
     * 
     * @param string $content
     * @return string
     */
    public function apply(string $content): string
    {
        $domDocument = new HTML5DOMDocument();
        $domDocument->loadHTML($content, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
        $getElements = function ($name) use ($domDocument) {
            return $domDocument->querySelectorAll('[name="' . $name . '"]');
        };
        $getValue = function ($name) use ($getElements) {
            $elements = $getElements($name);
            if (sizeof($elements) > 0) {
                if ($elements[0]->tagName === 'form-element-radio') {
                    foreach ($elements as $element) {
                        if (strlen((string)$element->getAttribute('checked')) > 0) {
                            return $element->getAttribute('value');
                        }
                    }
                } else {
                    return $elements[0]->getAttribute('value');
                }
            }
            return null;
        };
        $isChecked = function ($name) use ($getElements) {
            $elements = $getElements($name);
            if (sizeof($elements) > 0) {
                if ($elements[0]->tagName === 'form-element-checkbox') {
                    return strlen((string)$elements[0]->getAttribute('checked')) > 0;
                }
            }
            return false;
        };
        foreach ($this->data as $rule) {
            $elementName = $rule[1];
            $dependency = $rule[2];
            $elements = $getElements($elementName);
            if ($rule[0] === 'visible') {
                $visible = $this->checkDependency($dependency, $getValue, $isChecked);
                foreach ($elements as $element) {
                    $element->setAttribute('visibility', $visible ? 'true' : 'false');
                }
            }
        }

        $content = $domDocument->saveHTML();
        return $content;
    }

    /**
     * 
     * @return array
     */
    public function getClientData(): array
    {
        return $this->data;
    }
}
