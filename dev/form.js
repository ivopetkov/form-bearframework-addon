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


    var makeEvent = function (name) {
        if (typeof Event === 'function') {
            return new Event(name);
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
        }
    };

    var submit = async (id) => {
        var formElement = getFormElement(id);
        if (formElement !== null) {
            var formData = forms[id];
            if (formData.status === 1) {
                return;
            }

            var dispatchEvent = async (name, data) => {
                var event = makeEvent(name);
                if (typeof data !== 'undefined') {
                    for (var key in data) {
                        event[key] = data[key];
                    }
                }
                var updateDisabled = false;
                if (formElement.getAttribute('disabled') === 'true') {
                    formElement.removeAttribute('disabled'); // The events does not work in IE 11 if disabled
                    updateDisabled = true;
                }
                event.promisesToWait = [];
                var result = formElement.dispatchEvent(event);
                if (event.promisesToWait.length > 0) {
                    await Promise.allSettled(event.promisesToWait);
                }
                if (updateDisabled) {
                    formElement.setAttribute('disabled', 'true');
                }
                return result;
            };

            var result = await dispatchEvent('beforesubmit');
            if (!result) {
                return;
            }
            formData.status = 1;

            disableOrEnable(id, true);
            await dispatchEvent('submitstart');

            clientPackages.get('-form-submit').then(function (formSubmit) {
                formSubmit.submit(formElement, formData, dispatchEvent, () => {
                    formData.status = 0;
                    disableOrEnable(id, false);
                });
            });
        }
    };

    var disableOrEnable = function (id, disable) {
        var formElement = getFormElement(id);
        if (formElement !== null) {
            if (disable) {
                formElement.setAttribute('disabled', 'true');
            } else {
                formElement.removeAttribute('disabled');
            }
            var elements = formElement.querySelectorAll('input, select, textarea');
            var elementsCount = elements.length;
            for (var i = 0; i < elementsCount; i++) {
                var element = elements[i];
                if (disable) {
                    element.setAttribute('disabled', 'true');
                    element.ipfrmds = 1;
                } else {
                    if (typeof element.ipfrmds !== 'undefined') {
                        element.removeAttribute('disabled');
                        delete element.ipfrmds;
                    }
                }
            }
        }
    };

    return {
        'initialize': initialize,
        'submit': submit
    };

}());