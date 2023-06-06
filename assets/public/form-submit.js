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

    var tooltipsToHide = [];

    var submit = (formElement, formData, dispatchEvent, onEnd) => {
        var values = {};

        // Clear previous tooltips when submiting with Enter key
        for (var i = 0; i < tooltipsToHide.length; i++) {
            tooltipsToHide[i]();
        }
        tooltipsToHide = [];

        var dispatchEnd = async () => {
            await dispatchEvent('submitend');
            await onEnd();
        };

        var dispatchSuccess = async (result) => {
            await dispatchEvent('submitsuccess', { 'result': result });
            await dispatchEnd();
        };

        var dispatchError = async () => {
            await dispatchEvent('submiterror');
            await dispatchEnd();
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
                                await dispatchError();
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
                                        showFormError(response.error.message);
                                    }
                                }
                            } else if (response.status === '1') {
                                await dispatchSuccess(response.result);
                            }
                        } else {
                            await dispatchError();
                        }
                    })
                    .catch(async () => {
                        await dispatchError();
                        showFormError(formData.errorMessage);
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

    var getElementSize = function (element) {
        var rectangle = element.getBoundingClientRect();
        return [rectangle.width, rectangle.height];
    };

    var getTooltipFixedContainer = function (element) {
        while (element.parentNode && typeof element.parentNode.tagName !== "undefined") {
            var style = window.getComputedStyle(element, null);
            if (style === null) {
                return null;
            }
            if (style.position === "fixed" && element.getAttribute('data-form-tooltip-container') !== null) {
                return element;
            }
            element = element.parentNode;
        }
        return null;
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

    var isElementOutsideViewport = function (element) {
        var rect = element.getBoundingClientRect();
        return rect.bottom < 0 || rect.right < 0 || rect.left > window.innerWidth || rect.top > window.innerHeight;
    }

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

        var element = document.createElement('a');
        element.setAttribute('data-form-component', 'tooltip');
        element.innerText = text;
        element.style.left = '-1000px';
        element.style.top = '-1000px';
        var fixedContainer = getTooltipFixedContainer(target);
        var hasFixedContainer = fixedContainer !== null;
        var targetHasFixedParent = !hasFixedContainer && hasFixedParent(target);
        if (targetHasFixedParent) {
            element.style.position = 'fixed';
        }
        if (hasFixedContainer) {
            fixedContainer.appendChild(element);
        } else {
            document.body.appendChild(element);
        }
        var updatePosition = function () {
            var targetCoordinates = getElementCoordinates(target);
            var targetSize = getElementSize(target);
            var tooltipSize = getElementSize(element);
            var arrowSize = getComputedStyle(element).getPropertyValue('--form-tooltip-arrow-size');
            var left = targetCoordinates[0] + (targetSize[0] - tooltipSize[0]) / 2;
            if (left < 5) {
                left = 5;
            }
            var top = targetCoordinates[1] - tooltipSize[1] - 2;
            if (hasFixedContainer) {
                var fixedContainerCoordinates = getElementCoordinates(fixedContainer);
                left -= fixedContainerCoordinates[0] - fixedContainer.scrollLeft;
                top -= fixedContainerCoordinates[1] - fixedContainer.scrollTop;
            } else if (targetHasFixedParent) {
                left -= window.pageXOffset;
                top -= window.pageYOffset;
            }
            element.style.left = left + 'px';
            element.style.top = 'calc(' + top + 'px - ' + arrowSize + ')';
        };
        updatePosition();
        if (isElementOutsideViewport(element)) {
            element.scrollIntoView();
        }
        var intervalID = window.setInterval(updatePosition, 100);
        var hide = function () {
            try {
                element.parentNode.removeChild(element);
            } catch (e) {

            }
            window.removeEventListener('click', hide);
            window.removeEventListener('keydown', hide);
            window.clearInterval(intervalID);
        };
        window.addEventListener('click', hide);
        window.addEventListener('keydown', hide);
        tooltipsToHide.push(hide);
    };

    return {
        'initialize': initialize,
        'submit': submit
    };

})();