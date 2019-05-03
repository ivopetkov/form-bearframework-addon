/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

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
            formElement.addEventListener('submitend', function () {
                disableOrEnable(id, false);
            });
        }
    };

    var submit = function (id) {
        var formElement = getFormElement(id);
        if (formElement !== null) {
            if (forms[id].status === 1) {
                return;
            }
            var event = document.createEvent('Event');
            event.initEvent('beforesubmit', false, true);
            var cancelled = !formElement.dispatchEvent(event);
            if (cancelled) {
                return;
            }
            forms[id].status = 1;

            disableOrEnable(id, true);
            var event = document.createEvent('Event');
            event.initEvent('submitstart', false, false);
            formElement.dispatchEvent(event);

            clientShortcuts.get('-form-submit').then(function (formSubmit) {
                formSubmit.submit(formElement, forms[id]);
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