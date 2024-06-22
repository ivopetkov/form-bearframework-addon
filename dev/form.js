/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/* global clientPackages */

var ivoPetkov = ivoPetkov || {};
ivoPetkov.bearFrameworkAddons = ivoPetkov.bearFrameworkAddons || {};
ivoPetkov.bearFrameworkAddons.form = ivoPetkov.bearFrameworkAddons.form || (function () {

    var forms = [];

    var getFormElement = function (id) {
        if (typeof forms[id] !== 'undefined') {
            return document.querySelector('form[data-form-id="' + id + '"]');
        }
        return null;
    };

    var makeEvent = function (name, cancelable) {
        if (typeof cancelable === 'undefined') {
            cancelable = false;
        }
        if (typeof Event === 'function') {
            return new Event(name, { cancelable: cancelable });
        } else {
            var event = document.createEvent('Event');
            event.initEvent(name, false, false);
            return event;
        }
    };

    var initialize = function (data) {
        var id = data[0];
        forms[id] = {
            'serverData': data[1],
            'errorMessage': data[2],
            'status': 0 // 1 - submitting
        };
        var dependencies = data[3];
        var formElement = getFormElement(id);
        if (formElement !== null) {
            formElement.submit = function () {
                submit(id);
            };
            var processEventAttributes = function (name) {
                var value = formElement.getAttribute('on' + name);
                if (value !== null) {
                    formElement.addEventListener(name, function (event) {
                        var f = (new Function("return function(event){" + value + "}"))();
                        (f.bind(this))(event);
                    });
                }
            };
            processEventAttributes('beforesubmit');
            processEventAttributes('submitstart');
            processEventAttributes('submitend');
            processEventAttributes('submitsuccess');
            processEventAttributes('submiterror');

            var getElements = function (name) {
                if (typeof name === 'undefined') {
                    name = null;
                }
                var elements = formElement.querySelectorAll('[data-form-element-type]');
                var result = [];
                for (var i = 0; i < elements.length; i++) {
                    var element = elements[i];
                    if (name === null) {
                        result.push(element);
                    } else if (element.querySelector('[name="' + name + '"]') !== null) {
                        result.push(element);
                    }
                }
                return result;
            };

            var getValue = function (name) {
                var elements = getElements(name);
                if (elements.length > 0) {
                    if (elements[0].getAttribute('data-form-element-type') === 'radio') {
                        for (var i = 0; i < elements.length; i++) {
                            if (elements[i].isChecked()) {
                                return elements[i].getValue();
                            }
                        }
                    } else {
                        return elements[0].getValue();
                    }
                }
                return null;
            };

            var checkDependency = function (dependency) {
                if (dependency[0] === 'AND' || dependency[0] === 'OR') { // Complex dependency
                    var isAnd = dependency[0] === 'AND';
                    var result = false;
                    for (var i = 1; i < dependency.length; i++) {
                        if (checkDependency(dependency[i])) {
                            if (isAnd) {
                                var result = true;
                            } else {
                                var result = true;
                                break;
                            }
                        } else {
                            if (isAnd) {
                                return false;
                            }
                        }
                    }
                    return result;
                } else { // Simple dependency
                    var elementName = dependency[0];
                    var checkType = dependency[1];
                    if (checkType === 'value') {
                        var checkValue = dependency[2];
                        var elementValue = getValue(elementName);
                        if (typeof checkValue !== 'string') { // is array
                            if (checkValue.indexOf(elementValue) !== -1) {
                                return true;
                            }
                        } else {
                            if (elementValue === checkValue) {
                                return true;
                            }
                        }
                    } else if (checkType === 'checked') {
                        var elements = getElements(elementName);
                        if (elements.length > 0) {
                            if (elements[0].getAttribute('data-form-element-type') === 'checkbox') {
                                return elements[0].isChecked();
                            }
                        }
                    }
                }
                return false;
            }

            var getDependentByElementNames = function (dependency) {
                var result = [];
                if (dependency[0] === 'AND' || dependency[0] === 'OR') { // Complex dependency
                    for (var i = 1; i < dependency.length; i++) {
                        result = result.concat(getDependentByElementNames(dependency[i]));
                    }
                    return result;
                } else { // Simple dependency
                    result.push(dependency[0]);
                }
                return result;
            };
            var onChangeElements = [];
            for (var i = 0; i < dependencies.length; i++) {
                var dependency = dependencies[i][2];
                onChangeElements = onChangeElements.concat(getDependentByElementNames(dependency));
            }

            var update = function () {
                for (var i = 0; i < dependencies.length; i++) {
                    var elementName = dependencies[i][1];
                    var dependency = dependencies[i][2];
                    var elements = getElements(elementName);
                    if (dependencies[i][0] === 'visible') {
                        var visible = checkDependency(dependency);
                        for (var j = 0; j < elements.length; j++) {
                            if (typeof elements[j].setVisibility !== 'undefined') {
                                elements[j].setVisibility(visible);
                            }
                        }
                    }
                }
            };

            var updatedElements = [];
            for (var i = 0; i < onChangeElements.length; i++) {
                var elementName = onChangeElements[i];
                if (typeof updatedElements[elementName] !== 'undefined') {
                    continue;
                }
                updatedElements[elementName] = 1;
                var elements = getElements(elementName);
                for (var j = 0; j < elements.length; j++) {
                    elements[j].addEventListener('change', update); // the event bubbles from the inputs
                }
            }

            var submitOnEnterKey = function () {
                submit(id);
                var allElements = getElements();

                // scroll to the first submit button
                for (var i = 0; i < allElements.length; i++) {
                    var element = allElements[i];
                    if (element.getAttribute('data-form-element-type') === 'submit-button') {
                        element.scrollIntoView(false);
                        break;
                    }
                }
            };

            var allElements = getElements();
            for (var i = 0; i < allElements.length; i++) {
                var element = allElements[i];
                if (element.getAttribute('data-form-element-type') === 'textbox') {
                    element.addEventListener('keydown', function (e) {
                        if (e.keyCode === 13) {
                            submitOnEnterKey();
                        }
                    });
                }
            }

            formElement.addEventListener('reset', function () {
                var elements = formElement.querySelectorAll('input, select, textarea');
                var elementsCount = elements.length;
                for (var i = 0; i < elementsCount; i++) {
                    var element = elements[i];
                    element.dispatchEvent(new Event('change')); // some elements need this to update custom values
                }
            });
        }
    };

    var submit = async (id) => {
        var formElement = getFormElement(id);
        if (formElement !== null) {
            var formData = forms[id];
            if (formData.status === 1) {
                return;
            }

            var showFormError = function (message) {
                if (typeof message === "undefined") {
                    message = formData.errorMessage;
                }
                var element = formElement.querySelector('[data-form-element-type="submit-button"]');
                if (element !== null) {
                    showTooltip(element, message);
                } else {
                    alert(message);
                }
            };

            var showElementError = function (element, message) {
                showTooltip(element, message);
            };

            var dispatchEvent = async (name, data, cancelable) => {
                var event = makeEvent(name, cancelable);
                if (typeof data !== 'undefined') {
                    for (var key in data) {
                        event[key] = data[key];
                    }
                }
                event.promisesToWait = [];
                var result = formElement.dispatchEvent(event);
                if (event.promisesToWait.length > 0) {
                    await Promise.allSettled(event.promisesToWait);
                }
                return result; // false if preventDefault() called.
            };

            var result = await dispatchEvent('beforesubmit', {}, true);
            if (!result) {
                return;
            }
            formData.status = 1;

            disableOrEnable(id, true);
            await dispatchEvent('submitstart');

            var onEndSubmit = function () {
                formData.status = 0;
                disableOrEnable(id, false);
            };

            var dispatchEnd = async () => {
                await dispatchEvent('submitend');
                onEndSubmit();
            };

            var dispatchSuccess = async (result) => {
                await dispatchEvent('submitsuccess', { 'result': result });
                await dispatchEnd();
            };

            var dispatchError = async (message, element, showError) => {
                if (typeof message === 'undefined') {
                    message = '';
                }
                if (typeof element === 'undefined') {
                    element = '';
                }
                var result = await dispatchEvent('submiterror', { errorMessage: message, errorElement: element }, true);
                await dispatchEnd();

                if (showError && result) { // not cancelled
                    showFormError();
                }

                return result; // false if preventDefault() called.
            };

            clientPackages.get('-form-submit').then(function (formSubmit) {
                formSubmit.submit(formElement, formData, showFormError, showElementError, dispatchSuccess, dispatchError);
            }).catch(async () => {
                var errorEventResult = await dispatchError();
                if (errorEventResult) { // not cancelled
                    showFormError();
                }
            });
        }
    };

    var disableOrEnable = function (id, disable) {
        var formElement = getFormElement(id);
        if (formElement !== null) {
            if (disable) {
                formElement.setAttribute('inert', 'true');
            } else {
                formElement.removeAttribute('inert');
            }
        }
    };

    var isElementOutsideViewport = function (element) {
        var rect = element.getBoundingClientRect();
        return rect.bottom < 0 || rect.right < 0 || rect.left > window.innerWidth || rect.top > window.innerHeight;
    };

    var showTooltip = function (target, text) {
        var elementIsVisible = function (element) {
            var rectangle = element.getBoundingClientRect();
            return rectangle.width > 0 || rectangle.height > 0;
        };
        for (var i = 0; i < 1000; i++) { // find visible element arount the target (if the target is not visible)
            if (elementIsVisible(target)) {
                break;
            } else {
                var newTargetFound = false;
                var previousSibling = target.previousSibling;
                for (var j = 0; j < 1000; j++) {
                    if (previousSibling === null) {
                        break;
                    }
                    if (elementIsVisible(previousSibling)) {
                        target = previousSibling;
                        newTargetFound = true;
                        break;
                    }
                    previousSibling = previousSibling.previousSibling;
                }
                if (newTargetFound) {
                    break;
                }
                var nextSibling = target.nextSibling;
                for (var j = 0; j < 1000; j++) {
                    if (nextSibling === null) {
                        break;
                    }
                    if (elementIsVisible(nextSibling)) {
                        target = nextSibling;
                        newTargetFound = true;
                        break;
                    }
                    nextSibling = nextSibling.nextSibling;
                }
                if (newTargetFound) {
                    break;
                }
                target = target.parentNode;
            }
        }
        if (target === null || typeof target.tagName === 'undefined') {
            return;
        }
        if (isElementOutsideViewport(target)) {
            target.scrollIntoView();
        }
        clientPackages.get('tooltip').then(function (tooltip) {
            tooltip.show('ip-form-tooltip', target, '', {
                preferedPositions: ['top', 'bottom', 'right', 'left'],
                hideOnClick: true,
                hideOnKeyDown: true,
                onBeforeShow: function (element) {
                    element.setAttribute('data-form-component', 'tooltip');
                    element.innerText = text;
                }
            });
        });
    };

    return {
        'initialize': initialize,
        'submit': submit,
        'showTooltip': showTooltip
    };

}());