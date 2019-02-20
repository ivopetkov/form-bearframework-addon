/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
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

                var event = document.createEvent('Event');
                event.initEvent('requestsent', false, false);
                formElement.dispatchEvent(event);

                var responseReceived = function () {
                    forms[id].status = 0;
                    var event = document.createEvent('Event');
                    event.initEvent('responsereceived', false, false);
                    formElement.dispatchEvent(event);
                };

                var sendSubmitRequest = function () {
                    var data = {};
                    data['serverData'] = formData.serverData;
                    data['values'] = JSON.stringify(values);

                    ivoPetkov.bearFrameworkAddons.serverRequests.send('ivopetkov-form', data, function (responseText) {
                        responseReceived();
                        try {
                            var response = JSON.parse(responseText);
                        } catch (e) {
                            var response = {};
                        }
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
                        createTooltip(id, formElement, formData.errorMessage);
                    });
                };

                var pendingUploads = {};
                var hasPendingUploads = false;
                var onFileUploaded = function (elementName, fileData) {
                    pendingUploads[elementName] = 1;
                    values[elementName] = {
                        'type': 'file',
                        'value': fileData
                    };
                    for (var k in pendingUploads) {
                        if (pendingUploads[k] === null) {
                            return;
                        }
                    }
                    sendSubmitRequest();
                };

                var elements = formElement.querySelectorAll('input, select, textarea');
                var elementsCount = elements.length;
                for (var j = 0; j < elementsCount; j++) {
                    var element = elements[j];
                    if (element.name.length > 0) {
                        var elementName = element.name;
                        var elementType = element.getAttribute('type');
                        if (elementType === null) {
                            elementType = 'text';
                        }
                        if (elementType === 'file') {
                            if (typeof element.files !== 'undefined' && element.files.length > 0) {
                                (function (elementName) {
                                    pendingUploads[elementName] = null;
                                    var request = new XMLHttpRequest();
                                    request.addEventListener("load", function () {
                                        if (request.status === 200) {
                                            if (request.responseText.indexOf('"filename":') > 0) {
                                                onFileUploaded(elementName, request.responseText);
                                                return;
                                            }
                                        }
                                        responseReceived();
                                    });
                                    request.addEventListener("error", function () {
                                        responseReceived();
                                    });
                                    request.addEventListener("abort", function () {
                                        responseReceived();
                                    });
                                    request.addEventListener("timeout", function () {
                                        responseReceived();
                                    });

                                    var filesData = new FormData();
                                    for (var i = 0; i < element.files.length; i++) {
                                        filesData.append("file" + i, element.files[i]);
                                    }
                                    request.open('POST', formData.filesUploadUrl, true);
                                    request.send(filesData);
                                    hasPendingUploads = true;
                                })(elementName);
                            } else {
                                values[elementName] = {
                                    'type': elementType,
                                    'value': ''
                                };
                            }
                        } else {
                            values[elementName] = {
                                'type': elementType,
                                'value': element.value
                            };
                        }
                    }
                }
                if (!hasPendingUploads) {
                    sendSubmitRequest();
                }

            }
        }
    };

    var getElementSize = function (element) {
        var rectangle = element.getBoundingClientRect();
        return [rectangle.width, rectangle.height];
    };

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

    var getElementCoordinates = function (element) {
        var rectangle = element.getBoundingClientRect();
        var left = Math.round(rectangle.left);
        var top = Math.round(rectangle.top);
        left += window.pageXOffset;
        top += window.pageYOffset;
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
                var targetHasFixedParent = hasFixedParent(target);
                if (targetHasFixedParent) {
                    element.style.position = 'fixed';
                }
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
                    if (targetHasFixedParent) {
                        left -= window.pageXOffset;
                        top -= window.pageYOffset;
                    }
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
        }
    };

    return {
        'initialize': initialize,
        'submit': submit
    };

}());