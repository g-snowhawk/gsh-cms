/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2020 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', cmsSiteInitializeRemoving)
        break;
    case 'interactive':
    case 'complete':
        cmsSiteInitializeRemoving();
        break;
}

function cmsSiteInitializeRemoving(event) {
    const checkbox = document.querySelector('input[name=removing]');
    if (checkbox) {
        checkbox.addEventListener('click', cmsSiteEnableSubmitListener);
        cmsSiteEnableSubmit(checkbox);
    }
}
function cmsSiteEnableSubmitListener(event) {
    cmsSiteEnableSubmit(event.target);
}

function cmsSiteEnableSubmit(element) {
    const form = element.form;
    const button = form.querySelector('input[name=s1_submit]');
    if (button) {
        button.disabled = !element.checked;
        if (button.disabled) {
            form.removeEventListener('submit', cmsSiteConfirmRemoval);
        } else {
            form.addEventListener('submit', cmsSiteConfirmRemoval);
        }
    }
}

function cmsSiteConfirmRemoval(event) {
    event.preventDefault();
    const form = event.target;
    new Dialog(
        'Input your password.',
        'secret',
        function (returnValue) {
            if (returnValue !== null) {
                const passwd = form.appendChild(document.createElement('input'));
                passwd.type = 'hidden';
                passwd.name = 'passphrase';
                passwd.value = returnValue;
                form.submit();
            }
        }
    );
}
