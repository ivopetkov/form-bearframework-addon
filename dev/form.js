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

    var initialize = function (data) {
        var id = data[0];
        forms[id] = {
            'serverData': data[1],
            'errorMessage': data[2],
            'status': 0 // 1 - submitting
        };
        var formElement = document.querySelector('form[data-form-id="' + id + '"]');
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

    var submit = function (id) {
        if (typeof forms[id] !== 'undefined') {
            var formElement = document.querySelector('form[data-form-id="' + id + '"]');
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

                var event = document.createEvent('Event');
                event.initEvent('submitstart', false, false);
                formElement.dispatchEvent(event);

                clientShortcuts.get('-form-submit').then(function (formSubmit) {
                    formSubmit.submit(formElement, forms[id]);
                });
            }
        }
    };

    return {
        'initialize': initialize,
        'submit': submit
    };

}());