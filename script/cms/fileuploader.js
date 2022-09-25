/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2022 PlusFive <https://www.plus-5.com/>
 *
 * This software is released under the MIT License.
 * http://your.domain/licenses/mit-license
 */

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', tmsFileUploaderInit)
        break;
    case 'interactive':
    case 'complete':
        tmsFileUploaderInit();
        break;
}

let tmsFileUploaderMode = undefined;
let tmsFileUploaderForm = undefined;
let tmsFileUploaderCanceller = undefined;
let tmsFileUploaderCountFiles = undefined;
let tmsFileUploaderCountDirectory = undefined;
let tmsFileUploaderCountError = undefined;
let tmsFileUploaderDirectoryMessage = undefined;
let tmsFileUploaderErrorMessage = undefined;

function tmsFileUploaderInit() {
    const opener = document.querySelectorAll('.file-uploader');
    opener.forEach(element => {
        element.addEventListener('click', tmsFileUploaderOpen);
        const match = element.search.match(/mode=([^=]+)/);
        if (match) {
            tmsFileUploaderMode = match[1];
            tmsFileUploaderForm = element.findParent('form');
        }
    });

    //
    const element = document.getElementById('file-selector');
    if (element) {
        tmsFileUploaderErrorMessage = element.dataset.errorMessage;
        tmsFileUploaderDirectoryMessage = element.dataset.directoryMessage;
        const droparea = document.querySelectorAll('label.droparea');
        droparea.forEach(area => {
            const inputs = area.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('change', tmsFileUploaderOnChange);
            });
            area.addEventListener('drop', tmsFileUploaderDragAndDrop);
            area.addEventListener('dragleave', tmsFileUploaderDragAndDrop);
            area.addEventListener('dragover', tmsFileUploaderDragAndDrop);
        });
    }

    const err = getcookie('tmsFileUploadMessage');
    if (err) {
        alert(err);
        setcookie('tmsFileUploadMessage', null);
    }
}

function tmsFileUploaderOpen(event) {
    event.preventDefault();
    const element = document.getElementById('file-selector');
    if (element) {
        element.classList.toggle('open');
        if (!element.classList.contains('open')) {
            const inputs = element.querySelectorAll('input');
            inputs.forEach(input => {
                input.value = '';
            });
        }
    }
}

function tmsFileUploaderOnChange(event) {
    const element = event.target;

    if (tmsFileUploaderCountFiles === undefined) {
        tmsFileUploaderCountFiles = 0;
    }
    if (tmsFileUploaderCountError === undefined) {
        tmsFileUploaderCountError = 0;
    }

    for (let i = 0; i < element.files.length; i++) {
        const file = element.files[i];
        tmsFileUploaderUpload(file);
        tmsFileUploaderCountFiles++;
    }

    element.value = '';
}

function tmsFileUploaderDragAndDrop(event) {
    event.preventDefault();
    const element = event.target;
    switch (event.type) {
        case 'drop':
            if (tmsFileUploaderCountFiles === undefined) {
                tmsFileUploaderCountFiles = 0;
            }
            if (tmsFileUploaderCountDirectory === undefined) {
                tmsFileUploaderCountDirectory = 0;
            }
            if (tmsFileUploaderCountError === undefined) {
                tmsFileUploaderCountError = 0;
            }
            const items = event.dataTransfer.items;
            let files = 0;
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                if (item.getAsEntry) {
                    file = item.getAsEntry();
                } else if (item.webkitGetAsEntry) {
                    file = item.webkitGetAsEntry();
                } else {
                    console.warn("This browser is unsupported to HTML-Translator");
                    return;
                }
                if (!file.file) {
                    tmsFileUploaderCountDirectory++;
                    continue;
                }
                file.file(file => {
                    tmsFileUploaderUpload(file)
                    tmsFileUploaderCountFiles++;
                });
                files++;
            }

            if (files === 0) {
                if (tmsFileUploaderCountDirectory > 0) {
                    alert(tmsFileUploaderDirectoryMessage.replace(/%d/, tmsFileUploaderCountDirectory));
                }
                tmsFileUploaderCountFiles = undefined;
                tmsFileUploaderCountError = undefined;
                tmsFileUploaderCountDirectory = undefined;
            }
        case 'dragleave':
            element.classList.remove('dragover');
            break;
        case 'dragover':
            element.classList.add('dragover');
            break;
        default:
            // NooP
            break;
    }
}

function tmsFileUploaderUpload(file) {
    const formData = new FormData();
    formData.append('stub', tmsFileUploaderForm.stub.value);
    formData.append('mode', tmsFileUploaderMode);
    formData.append('file', file);
    formData.append('returntype', 'json');

    tmsFileUploaderCanceller = new AbortController();

    const request = {
        headers:  new Headers({
            'X-Requested-With': 'XMLHttpRequest'
        }),
        signal: tmsFileUploaderCanceller.signal,
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    };

    fetch(tmsFileUploaderForm.action, request)
    .then(response => {
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }

            response.text().then(text => {
                console.log(text);
            });
            throw new Error("Unexpected response");
        } else {
            throw new Error("Server Error");
        }
    })
    .then(response => {
        if (response.status !== 0) {
            tmsFileUploaderCountError++;
            console.warn(response.message);
        } else {
            // Something to do if success uploading
        }
    })
    .catch(error => {
        if (error.name === 'AbortError') {
            console.warn('Aborted!');
        } else {
            console.error(error)
            alert(error.message);
        }
    })
    .then(() => {
        if (tmsFileUploaderCountFiles !== undefined) {
            if (--tmsFileUploaderCountFiles <= 0) {
                tmsFileUploaderForm.dataset.freeUnload = '1';
                let errorMessage = [];
                if (tmsFileUploaderCountDirectory > 0) {
                    errorMessage.push(tmsFileUploaderDirectoryMessage.replace(/%d/, tmsFileUploaderCountDirectory));
                }
                if (tmsFileUploaderCountError > 0) {
                    errorMessage.push(tmsFileUploaderErrorMessage.replace(/%d/, tmsFileUploaderCountError));
                }
                if (errorMessage.length > 0) {
                    setcookie('tmsFileUploadMessage', errorMessage.join('\n'));
                }
                location.reload();
            }
        }
    });
}
