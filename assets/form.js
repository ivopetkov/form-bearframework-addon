/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

var ivoPetkov = ivoPetkov || {};
ivoPetkov.bearFramework = ivoPetkov.bearFramework || {};
ivoPetkov.bearFramework.addons = ivoPetkov.bearFramework.addons || {};
ivoPetkov.bearFramework.addons.form = (function () {

    var formsData = [];

    var sendRequest = function (url, data, callback) {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function ()
        {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200)
            {
                callback(xmlhttp.responseText);
            }
        }
        var params = [];
        for (var key in data) {
            params.push(key + '=' + encodeURIComponent(data[key]));
        }
        params = params.join('&');
        xmlhttp.open('POST', url, true);
        xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xmlhttp.send(params);
    };

    var submit = function (id) {
        var formElement = document.querySelector('form[data-form-id="' + id + '"]');
        if (formElement) {
            var formData = formsData[id];
            if (typeof formData !== 'undefined') {

                var data = [];
                data['ivopetkov-form-bearframework-addon'] = JSON.stringify(formData.serverData);

                var elements = formElement.querySelectorAll('input, select, textarea');
                var elementsCount = elements.length;
                for (var j = 0; j < elementsCount; j++) {
                    var element = elements[j];
                    if (element.name.length > 0) {
                        data[element.name] = element.value;
                    }
                }

                sendRequest(formData.submitUrl, data, function (result) {
                    var result = JSON.parse(result);
                    if (typeof result.message !== 'undefined' && result.message.length > 0) {
                        alert(result.message);
                    }
                    if (typeof result.status !== 'undefined' && result.status === 1) {
                        var onSubmitSuccessfulHandler = formElement.getAttribute('data-form-on-submit-successful');
                        if (onSubmitSuccessfulHandler !== null) {
                            (new Function(onSubmitSuccessfulHandler))();
                        }
                    }
                });
            }
        }
    };
    var initialize = function (id, data) {
        formsData[id] = data;
        var formElement = document.querySelector('form[data-form-id="' + id + '"]');
        if (formElement) {
            var submitButtons = formElement.querySelectorAll('[data-form-submit-button]');
            for (var i = 0; i < submitButtons.length; i++) {
                var submitButton = submitButtons[i];
                submitButton.addEventListener('click', function () {
                    submit(id);
                });
            }
            //var submitButton = form.querySelector('input[type="submit"]');
        }
    };
    return {
        'initialize': initialize,
        'submit': submit
    }

}());