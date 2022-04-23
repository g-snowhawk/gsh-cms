/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2017 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */
switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', cmsPreviewInit)
        break;
    case 'interactive':
    case 'complete':
        cmsPreviewInit();
        break;
}

function cmsPreviewInit(event) {
    (document.querySelectorAll('a')).forEach((element) => {;
        element.addEventListener('click', cmsPreviewPrevent);
    });
    (document.querySelectorAll('form')).forEach((element) => {;
        element.addEventListener('submit', cmsPreviewPrevent);
    });
    window.addEventListener('beforeunload', cmsPreviewPrevent);
}

function cmsPreviewPrevent(event) {
    event.preventDefault();
    switch (event.type) {
        case 'click':
            alert('This link cannot be used.');
            break;
        case 'submit':
            alert('This form cannot be used.');
            break;
        case 'beforeunload':
            const img = new Image();
            img.src = '?mode=cms.entry.response:before-unload-preview';
            break;
    }
}
