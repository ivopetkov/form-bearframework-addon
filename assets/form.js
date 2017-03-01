/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) 2016-2017 Ivo Petkov
 * Free to use under the MIT license.
 */

var ivoPetkov = ivoPetkov || {};
ivoPetkov.bearFrameworkAddons = ivoPetkov.bearFrameworkAddons || {};
ivoPetkov.bearFrameworkAddons.form = (function () {

    var forms = [];

    var submit = function (id) {
        var formElement = document.querySelector('form[data-form-id="' + id + '"]');
        if (formElement) {
            var formData = forms[id].data;
            if (typeof formData !== 'undefined') {
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

                var values = {};

                var elements = formElement.querySelectorAll('input, select, textarea');
                var elementsCount = elements.length;
                for (var j = 0; j < elementsCount; j++) {
                    var element = elements[j];
                    if (element.name.length > 0) {
                        values[element.name] = element.value;
                    }
                }

                var data = {};
                data['serverData'] = formData.serverData;
                data['values'] = JSON.stringify(values);

                var event = document.createEvent('Event');
                event.initEvent('requestsent', false, false);
                formElement.dispatchEvent(event);
                
                var responseReceived = function(){
                    var event = document.createEvent('Event');
                    event.initEvent('responsereceived', false, false);
                    formElement.dispatchEvent(event);
                }

                ivoPetkov.bearFrameworkAddons.serverRequests.send('ivopetkov-form', data, function (responseText) {
                    responseReceived();
                    forms[id].status = 0;
                    var response = JSON.parse(responseText);
                    if (typeof response.status !== 'undefined') {
                        if (response.status === '0') {
                            if (typeof response.error.element !== 'undefined' && response.error.element.length > 0) {
                                var invalidElement = formElement.querySelector('[name="' + response.error.element + '"]');
                                if (invalidElement !== null) {
                                    invalidElement.focus();
                                }
                            }
                            if (typeof response.error.message !== 'undefined' && response.error.message.length > 0) {
                                if (typeof response.error.element !== 'undefined' && response.error.element.length > 0 && invalidElement !== null) {
                                    createTooltip(id, invalidElement, response.error.message);
                                } else {
                                    createTooltip(id, formElement, response.error.message);
                                }
                            }
                        } else if (response.status === '1') {
                            var event = document.createEvent('Event');
                            event.initEvent('submitdone', false, false);
                            event.result = response.result;
                            formElement.dispatchEvent(event);
                        }
                    }
                }, function () {
                    responseReceived();
                    createTooltip(id, formElement, 'Error occurred. Please, try again later.');
                });

            }
        }
    };

    var getElementSize = function (element) {
        var rectangle = element.getBoundingClientRect();
        return [rectangle.width, rectangle.height];
    };

    var getElementCoordinates = function (element) {
        var hasFixedParent = function (element) {
            while (element.parentNode && typeof element.parentNode.tagName !== "undefined") {
                var style = window.getComputedStyle(element, null);
                if (style === null) {
                    return false;
                }
                if (style.position === "fixed") {
                    return true;
                }
                element = element.parentNode;
            }
            return false;
        };

        var rectangle = element.getBoundingClientRect();
        var left = Math.round(rectangle.left);
        var top = Math.round(rectangle.top);
        if (!hasFixedParent(element)) {
            left += window.pageXOffset;
            top += window.pageYOffset;
        }
        return [left, top];
    };

    var createTooltip = function (id, target, text) {
        if (typeof forms[id] !== 'undefined') {
            var formData = forms[id].data;
            if (typeof formData !== 'undefined') {
                var element = document.createElement('a');
                element.className = formData.errorTooltipData['className'];
                element.innerText = text;
                element.style.left = '-1000px';
                element.style.top = '-1000px';
                document.body.appendChild(element);
                var updatePosition = function () {
                    var targetCoordinates = getElementCoordinates(target);
                    var targetSize = getElementSize(target);
                    var tooltipSize = getElementSize(element);
                    var left = targetCoordinates[0] + (targetSize[0] - tooltipSize[0]) / 2;
                    if (left < 5) {
                        left = 5;
                    }
                    var top = targetCoordinates[1] - tooltipSize[1] - 2;
                    element.style.left = left + 'px';
                    element.style.top = 'calc(' + top + 'px - ' + formData.errorTooltipData['arrowSize'] + ')';
                };
                updatePosition();
                var intervalID = window.setInterval(updatePosition, 100);
                var hide = function () {
                    element.parentNode.removeChild(element);
                    window.removeEventListener('click', hide);
                    window.clearInterval(intervalID);
                };
                window.addEventListener('click', hide);

                return element;
            }
        }
        return null;
    };

    var initialize = function (id, data) {
        forms[id] = {
            'data': data,
            'status': 0 // 1 - submitting
        };
        var formElement = document.querySelector('form[data-form-id="' + id + '"]');
        if (formElement) {
            formElement.submit = function () {
                submit(id);
            };
            var onBeforeSubmitValue = formElement.getAttribute('onbeforesubmit');
            if (onBeforeSubmitValue !== null) {
                formElement.addEventListener('beforesubmit', function (event) {
                    var f = (new Function("return function(event){" + onBeforeSubmitValue + "}"))();
                    (f.bind(this))(event);
                });
            }
            var onSubmitDoneValue = formElement.getAttribute('onsubmitdone');
            if (onSubmitDoneValue !== null) {
                formElement.addEventListener('submitdone', function (event) {
                    var f = (new Function("return function(event){" + onSubmitDoneValue + "}"))();
                    (f.bind(this))(event);
                });
            }
            var onRequestSentValue = formElement.getAttribute('onrequestsent');
            if (onRequestSentValue !== null) {
                formElement.addEventListener('requestsent', function (event) {
                    var f = (new Function("return function(event){" + onRequestSentValue + "}"))();
                    (f.bind(this))(event);
                });
            }
            var onResponseReceivedValue = formElement.getAttribute('onresponsereceived');
            if (onResponseReceivedValue !== null) {
                formElement.addEventListener('responsereceived', function (event) {
                    var f = (new Function("return function(event){" + onResponseReceivedValue + "}"))();
                    (f.bind(this))(event);
                });
            }
            formElement.addEventListener('submit', function (event) { // for input type=submit and enter in inputs
                this.submit();
                event.preventDefault();
            });
        }
    };

    return {
        'initialize': initialize,
        'submit': submit
    };

}());