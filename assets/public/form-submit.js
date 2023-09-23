/*
 * Form addon for Bear Framework
 * https://github.com/ivopetkov/form-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/* global clientPackages */

var ivoPetkov = ivoPetkov || {};
ivoPetkov.bearFrameworkAddons = ivoPetkov.bearFrameworkAddons || {};
ivoPetkov.bearFrameworkAddons.formSubmit = ivoPetkov.bearFrameworkAddons.formSubmit || (() => {

    var formSubmitData = null;

    var initialize = (data) => {
        if (formSubmitData === null) {
            formSubmitData = {
                'filesUploadUrl': data[0]
            };
        }
    };

    var submit = (formElement, formData, dispatchEvent, onEnd) => {
        var values = {};

        var dispatchEnd = async () => {
            await dispatchEvent('submitend');
            await onEnd();
        };

        var dispatchSuccess = async (result) => {
            await dispatchEvent('submitsuccess', { 'result': result });
            await dispatchEnd();
        };

        var dispatchError = async (message, element) => {
            if (typeof message === 'undefined') {
                message = '';
            }
            if (typeof element === 'undefined') {
                element = '';
            }
            var result = await dispatchEvent('submiterror', { errorMessage: message, errorElement: element }, true);
            await dispatchEnd();
            return result; // false if preventDefault() called.
        };

        var showFormError = function (message) {
            var element = formElement.querySelector('[data-form-element-type="submit-button"]');
            if (element !== null) {
                createTooltip(element, message);
            } else {
                alert(message);
            }
        };

        var sendSubmitRequest = function () {
            var data = {};
            data['serverData'] = formData.serverData;
            data['values'] = JSON.stringify(values);
            clientPackages.get('serverRequests').then(function (serverRequests) {
                serverRequests.send('ivopetkov-form', data)
                    .then(async (responseText) => {
                        try {
                            var response = JSON.parse(responseText);
                        } catch (e) {
                            var response = {};
                        }
                        if (typeof response.status !== 'undefined') {
                            if (response.status === '0') {
                                var errorElement = typeof response.error.element !== 'undefined' && response.error.element.length > 0 ? response.error.element : null;
                                var errorMessage = typeof response.error.message !== 'undefined' && response.error.message.length > 0 ? response.error.message : null;
                                var errorEventResult = await dispatchError(errorMessage, errorElement);
                                if (errorEventResult) { // not cancelled
                                    if (errorElement !== null) {
                                        var invalidElement = formElement.querySelector('[name="' + errorElement + '"]');
                                        if (invalidElement !== null) {
                                            invalidElement.focus();
                                        }
                                    }
                                    if (errorMessage !== null) {
                                        if (errorElement !== null && invalidElement !== null) {
                                            createTooltip(invalidElement, errorMessage);
                                        } else {
                                            showFormError(errorMessage);
                                        }
                                    }
                                }
                            } else if (response.status === '1') {
                                await dispatchSuccess(response.result);
                            }
                        } else {
                            await dispatchError(formData.errorMessage);
                        }
                    })
                    .catch(async () => {
                        var errorEventResult = await dispatchError(formData.errorMessage);
                        if (errorEventResult) { // not cancelled
                            showFormError(formData.errorMessage);
                        }
                    });
            });
        };

        var uploadFile = function (file, onSuccess, onAbort, onFail, onProgress) {
            var request = new XMLHttpRequest();
            request.addEventListener("load", function () {
                if (request.status === 200 && request.responseText.indexOf('"filename":') > 0) {
                    onSuccess(JSON.parse(request.responseText));
                    return;
                }
                onFail();
            });
            request.addEventListener("error", function () {
                onFail();
            });
            request.addEventListener("abort", function () {
                onAbort();
            });
            request.addEventListener("timeout", function () {
                onFail();
            });
            request.upload.addEventListener("loadstart", function () {
                onProgress(0);
            });
            request.upload.addEventListener("loadend", function () {
                onProgress(100);
            });
            request.upload.addEventListener("progress", function (event) {
                var percent = typeof event.lengthComputable !== 'undefined' && event.lengthComputable ? Math.round(event.loaded / event.total * 100) : 0;
                onProgress(percent);
            });
            var formData = new FormData();
            formData.append("file", file);
            request.open('POST', formSubmitData.filesUploadUrl, true);
            request.send(formData);
        };

        var pendingFileUploads = {};
        var hasPendingUploads = false;

        var continueIfAllFilesUploaded = function () {
            for (var k in pendingFileUploads) {
                if (pendingFileUploads[k] > 0) {
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
                    if (typeof element.getFormElementContainer !== 'undefined') { // ivopetkov/form-elements-bearframework-addon element
                        var elementContainer = element.getFormElementContainer();
                        (function (elementContainer, elementType, elementName) {
                            var getFormElementContainerValue = function () {
                                values[elementName] = {
                                    'type': elementType,
                                    'value': elementContainer.getValue()
                                };
                            };
                            if (elementContainer.hasPendingUploads()) {
                                pendingFileUploads[elementName] = 1;
                                elementContainer.upload(
                                    function (file, onSuccess, onAbort, onFail, onProgress) {
                                        uploadFile(file, function (value) {
                                            onSuccess(value);
                                        }, onAbort, onFail, onProgress);
                                    },
                                    function () { // on success
                                        pendingFileUploads[elementName] = 0;
                                        getFormElementContainerValue();
                                        continueIfAllFilesUploaded();
                                    },
                                    function () { // on abort

                                    },
                                    function () { // on fail
                                        dispatchError();
                                    },
                                    function (progress) { // on progress
                                    },
                                );
                                hasPendingUploads = true;
                            }
                            getFormElementContainerValue();
                        })(elementContainer, elementType, elementName);
                    } else { // default input field
                        if (typeof element.files !== 'undefined' && element.files.length > 0) {
                            (function (elementName, files) {
                                var filesCount = files.length;
                                pendingFileUploads[elementName] = filesCount;
                                var uploadNextFile = function (index) {
                                    if (typeof files[index] === 'undefined') {
                                        return;
                                    }
                                    uploadFile(files[index],
                                        function (value) { // on success
                                            uploadNextFile(index + 1);
                                            pendingFileUploads[elementName]--;
                                            if (typeof values[elementName] === 'undefined') {
                                                values[elementName] = {
                                                    'type': 'file',
                                                    'value': []
                                                };
                                            }
                                            values[elementName].value.push(value);
                                            if (pendingFileUploads[elementName] === 0) {
                                                values[elementName].value = JSON.stringify(values[elementName].value);
                                            }
                                            continueIfAllFilesUploaded();
                                        },
                                        function () { // on abort

                                        },
                                        function () { // on fail
                                            dispatchError();
                                        },
                                        function () { // on progress

                                        },
                                    );
                                };
                                uploadNextFile(0);
                                hasPendingUploads = true;
                            })(elementName, element.files);
                        } else {
                            values[elementName] = {
                                'type': elementType,
                                'value': ''
                            };
                        }
                    }
                } else {
                    if (typeof element.getFormElementContainer !== 'undefined') { // ivopetkov/form-elements-bearframework-addon element
                        var elementContainer = element.getFormElementContainer();
                        var elementValue = elementContainer.getValue();
                        if (elementValue !== null) { // dont send if checkbox or radio, all others should return empty strings
                            values[elementName] = {
                                'type': elementType,
                                'value': elementValue
                            };
                        }
                    } else {
                        var elementValue = element.value;
                        if (elementType === 'checkbox' || elementType === 'radio') {
                            if (element.checked) {
                                values[elementName] = {
                                    'type': elementType,
                                    'value': elementValue
                                };
                            }
                        } else {
                            values[elementName] = {
                                'type': elementType,
                                'value': elementValue
                            };
                        }
                    }
                }
            }
        }
        if (!hasPendingUploads) {
            sendSubmitRequest();
        }

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
        for (var i = 0; i < 1000; i++) {
            var targetCoordinates = getElementCoordinates(target);
            if (targetCoordinates[0] === 0 && targetCoordinates[1] === 0) { // check may be hidden (radio box input for example)
                target = target.parentNode;
            } else {
                break;
            }
        }
        if (target === null || typeof target.tagName === 'undefined') {
            return;
        }
        clientPackages.get('form').then(function (form) {
            form.showTooltip(target, text);
        });
    };

    return {
        'initialize': initialize,
        'submit': submit
    };

})();