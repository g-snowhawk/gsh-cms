/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2017-2021 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */
switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', tmsAttachmentsInit);
        break;
    case 'interactive':
    case 'complete':
        tmsAttachmentsInit();
        break;
}

const tmsAttachmentsOriginId = 'attachment-origin';
const tmsAttachmentsUploaderId = 'file-uploader';
const tmsAttachmentsFilesetClassName = 'file-set';
const tmsAttachmentsPopupClassName = 'popup';
const tmsAttachmentsPopupShowClassName = 'show';
const tmsAttachmentsDragLockClassName = 'lockon';
const tmsAttachmentsWithThumbnailClassName = 'with-thumbnail';

let tmsAttachmentsPopupWinow = undefined;
let tmsAttachmentsDragTarget = undefined;

function tmsAttachmentsInit(event) {
    const uploader = document.getElementById(tmsAttachmentsUploaderId);
    if (!uploader) {
        return;
    }

    const fileset = uploader.querySelectorAll('.' + tmsAttachmentsFilesetClassName);
    fileset.forEach((element) => {
        tmsAttachmentsSetEventListener(element);
    });

    tmsAttachmentsCountThumbnails();
}

function tmsAttachmentsReinit(element) {
    tmsAttachmentsSetEventListener(element);
    const origin = document.getElementById(tmsAttachmentsOriginId);
    if (origin !== element) {
        tmsAttachmentsSetEventListener(origin);
    }
}

function tmsAttachmentsSetEventListener(element) {
    element.draggable = element.id !== tmsAttachmentsOriginId;
    element.addEventListener('dragleave', tmsAttachmentsOnDragLeave);
    element.addEventListener('dragstart', tmsAttachmentsOnDragStart);
    element.addEventListener('dragover',  tmsAttachmentsOnDragEnter);
    element.addEventListener('drop',      tmsAttachmentsOnDrop);

    var popup = element.querySelector('.popup');
    if (!popup) {
        return;
    }
    element.addEventListener('contextmenu', tmsAttachmentsOnContextMenu);
}

function tmsAttachmentsOnContextMenu(event) {
    event.preventDefault();
    tmsAttachmentsPopup(event.currentTarget);
}

function tmsAttachmentsPopup(element) {
    const popup = element.querySelector('.' + tmsAttachmentsPopupClassName);
    if (popup === tmsAttachmentsPopupWinow && popup.classList.contains(tmsAttachmentsPopupShowClassName)) {
        return;
    }
    if (tmsAttachmentsPopupWinow) {
        tmsAttachmentsPopupWinow.classList.remove(tmsAttachmentsPopupShowClassName);
    }
    tmsAttachmentsPopupWinow = element.querySelector('.' + tmsAttachmentsPopupClassName);
    if (tmsAttachmentsPopupWinow.classList.contains(tmsAttachmentsPopupShowClassName)) {
        return;
    }
    tmsAttachmentsPopupWinow.classList.add(tmsAttachmentsPopupShowClassName);
    window.addEventListener('click', tmsAttachmentsPopdown);

    element.draggable = false;
}

function tmsAttachmentsPopdown(event) {
    let element = event.target;
    if (element === tmsAttachmentsPopupWinow || tmsAttachmentsPopupWinow.contains(element)) {
        return;
    }
    event.preventDefault();
    event.currentTarget.removeEventListener('click', tmsAttachmentsPopdown);
    tmsAttachmentsPopupWinow.classList.remove(tmsAttachmentsPopupShowClassName);
    element = tmsAttachmentsPopupWinow.findParent('.' + tmsAttachmentsFilesetClassName);
    element.draggable = true;
    tmsAttachmentsPopupWinow = undefined;
}

function tmsAttachmentsOnDragStart(event) {
    tmsAttachmentsDragTarget = tmsAttachmentsGetElement(event);
}

function tmsAttachmentsOnDragLeave(event) {
    tmsAttachmentsGetElement(event).classList.remove(tmsAttachmentsDragLockClassName);
}

function tmsAttachmentsOnDragEnter(event) {
    event.preventDefault();
    tmsAttachmentsGetElement(event).classList.add(tmsAttachmentsDragLockClassName);
}

function tmsAttachmentsOnDrop(event) {
    event.preventDefault();
    const element = tmsAttachmentsGetElement(event);

    const input = element.querySelector('input[type=file]');
    if (input && event.dataTransfer.files.length > 0) {
        input.files = event.dataTransfer.files;
        if (typeof TM.uploader.thumbnail === 'function') {
            TM.uploader.thumbnail(input);
        }
    }

    if (tmsAttachmentsDragTarget) {
        element.parentNode.insertBefore(tmsAttachmentsDragTarget, element);
    }
    element.classList.remove(tmsAttachmentsDragLockClassName);
}

function tmsAttachmentsGetElement(event) {
    const element = event.target;
    return (element.classList.contains(tmsAttachmentsFilesetClassName))
         ? element : element.findParent('.' + tmsAttachmentsFilesetClassName);
}

function tmsAttachmentsCountThumbnails(fileSet) {
    const uploader = document.getElementById(tmsAttachmentsUploaderId);
    const fileSets = uploader.querySelectorAll('.' + tmsAttachmentsFilesetClassName);

    let count = 0;
    fileSets.forEach(function(element) {
        if (element.id !== tmsAttachmentsOriginId) count++;
    });

    const withThumbnails = uploader.querySelectorAll('.' + tmsAttachmentsWithThumbnailClassName);
    const func = (count > 0) ? 'add' : 'remove';
    withThumbnails.forEach(function(element) {
        element.classList[func]('active');
    });
}
