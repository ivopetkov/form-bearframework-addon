/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/* global clientPackages */

var ivoPetkov = ivoPetkov || {};
ivoPetkov.bearFrameworkAddons = ivoPetkov.bearFrameworkAddons || {};
ivoPetkov.bearFrameworkAddons.formSubmit = ivoPetkov.bearFrameworkAddons.formSubmit || (function () {

    var formSubmitData = null;

    var initialize = function (data) {
        formSubmitData = {
            'errorTooltipData': data[0],
            'filesUploadUrl': data[1]
        };
    };

    var submit = function (formElement, formData, dispatchEvent, onEnd) {
        var values = {};

        var dispatchEnd = function () {
            dispatchEvent('submitend');
            onEnd();
        };

        var dispatchSuccess = function (result) {
            dispatchEvent('submitsuccess', {'result': result});
            dispatchEnd();
        };

        var dispatchError = function () {
            dispatchEvent('submiterror');
            dispatchEnd();
        };

        var sendSubmitRequest = function () {
            var data = {};
            data['serverData'] = formData.serverData;
            data['values'] = JSON.stringify(values);

            clientPackages.get('serverRequests').then(function (serverRequests) {
                serverRequests.send('ivopetkov-form', data)
                        .then(function (responseText) {
                            try {
                                var response = JSON.parse(responseText);
                            } catch (e) {
                                var response = {};
                            }
                            if (typeof response.status !== 'undefined') {
                                if (response.status === '0') {
                                    dispatchError();
                                    if (typeof response.error.element !== 'undefined' && response.error.element.length > 0) {
                                        var invalidElement = formElement.querySelector('[name="' + response.error.element + '"]');
                                        if (invalidElement !== null) {
                                            invalidElement.focus();
                                        }
                                    }
                                    if (typeof response.error.message !== 'undefined' && response.error.message.length > 0) {
                                        if (typeof response.error.element !== 'undefined' && response.error.element.length > 0 && invalidElement !== null) {
                                            createTooltip(invalidElement, response.error.message);
                                        } else {
                                            createTooltip(formElement, response.error.message);
                                        }
                                    }
                                } else if (response.status === '1') {
                                    dispatchSuccess(response.result);
                                }
                            } else {
                                dispatchError();
                            }
                        })
                        .catch(function () {
                            dispatchError();
                            createTooltip(formElement, formData.errorMessage);
                        });
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
                                dispatchError();
                            });
                            request.addEventListener("error", function () {
                                dispatchError();
                            });
                            request.addEventListener("abort", function () {
                                dispatchError();
                            });
                            request.addEventListener("timeout", function () {
                                dispatchError();
                            });
                            var filesData = new FormData();
                            for (var i = 0; i < element.files.length; i++) {
                                filesData.append("file" + i, element.files[i]);
                            }
                            request.open('POST', formSubmitData.filesUploadUrl, true);
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

    var createTooltip = function (target, text) {
        var element = document.createElement('a');
        element.className = formSubmitData.errorTooltipData['className'];
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
            element.style.top = 'calc(' + top + 'px - ' + formSubmitData.errorTooltipData['arrowSize'] + ')';
        };
        updatePosition();
        var intervalID = window.setInterval(updatePosition, 100);
        var hide = function () {
            element.parentNode.removeChild(element);
            window.removeEventListener('click', hide);
            window.clearInterval(intervalID);
        };
        window.addEventListener('click', hide);
    };

    return {
        'initialize': initialize,
        'submit': submit
    };

}());