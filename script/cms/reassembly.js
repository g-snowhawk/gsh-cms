/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2020 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */
let cmsReassemblyNumber = 0;
let cmsFormAction;
let cmdReassemblingProgress;

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', cmsReassemblyInit)
        break;
    case 'interactive':
    case 'complete':
        cmsReassemblyInit();
        break;
}

function cmsReassemblyInit(event) {
    const trigger = document.querySelector('[name=s1_submit]');
    if (trigger) {
        trigger.addEventListener('click', cmsReassemblyGetEntries);
        trigger.form.addEventListener('submit', cmsReassemblyUnsubmitForm);
    }

    cmdReassemblingProgress = document.getElementById('progress-rebuilding');
}

function cmsReassemblyUnsubmitForm(event) {
    event.preventDefault();
}

function cmsReassemblyGetEntries(event) {
    const trigger = event.target;
    const form = trigger.form;
    const formData = new FormData(form);
    cmsFormAction = form.action;

    fetch(cmsFormAction, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }
            throw new Error("Unexpected response");
        } else {
            throw new Error("Server Error");
        }
    }).then(response => {
        if (response.status !== 0) {
            const message = (response.description)
                ? response.description : response.message;
            throw new CmsReassemblyError(message);
        }
        if (confirm(trigger.dataset.confirm)) {
            trigger.classList.add('hidden');
            cmdReassemblingProgress.classList.remove('hidden');
            cmdReassemblingProgress.min = 0;
            cmdReassemblingProgress.max = response.entries.length;
            cmdReassemblingProgress.value = 0;
            formData.set('mode', 'cms.entry.receive:reassemble');
            formData.set('in_reassemble', '1');
            cmsReassemblyStart(response.entries, formData, 0, trigger);
        }
    }).catch((error) => {
        if (error.name === 'AbortError') {
            console.warn('Aborted!');
        } else {
            console.error(error)
            const message = (error.name === "CmsReassemblyError") ? error : 'System Error!';
            alert(message);
        }
    })
    .then(() => {
        // Finaly
    });
}

function cmsReassemblyPostProcess(formData, trigger) {
    formData.set('mode', 'cms.entry.receive:cleanup-unused-data');
    formData.delete('id');
    formData.delete('type');

    fetch(cmsFormAction, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }
            throw new Error("Unexpected response");
        } else {
            throw new Error("Server Error");
        }
    }).then(response => {
        if (response.status !== 0) {
            const message = (response.description)
                ? response.description : response.message;
            throw new CmsReassemblyError(message);
        }
        // Post-prosessing
        trigger.classList.remove('hidden');
        cmdReassemblingProgress.classList.add('hidden');
        alert(trigger.dataset.completion);
    }).catch((error) => {
        trigger.classList.remove('hidden');
        cmdReassemblingProgress.classList.add('hidden');
        if (error.name === 'AbortError') {
            console.warn('Aborted!');
        } else {
            console.error(error)
            const message = (error.name === "CmsReassemblyError") ? error : 'System Error!';
            alert(message);
        }
    })
    .then(() => {
        // Finaly
    });
}

function cmsReassemblyStart(entries, formData, n, trigger) {
    if (entries[n] === undefined) {
        cmsReassemblyPostProcess(formData, trigger);
        return;
    }
    //formData.set('version', entries[n].version);
    formData.set('id', entries[n].id);
    formData.set('type', entries[n].type);

    fetch(cmsFormAction, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }
            throw new Error("Unexpected response");
        } else {
            throw new Error("Server Error");
        }
    }).then(response => {
        if (response.status !== 0) {
            const message = (response.description)
                ? response.description : response.message;
            throw new CmsReassemblyError(message);
        }
        cmdReassemblingProgress.value = ++n;
        setTimeout(cmsReassemblyStart, 100, entries, formData, n, trigger);
    }).catch((error) => {
        trigger.classList.remove('hidden');
        cmdReassemblingProgress.classList.add('hidden');
        if (error.name === 'AbortError') {
            console.warn('Aborted!');
        } else {
            console.error(error)
            const message = (error.name === "CmsReassemblyError") ? error : 'System Error!';
            alert(message);
        }
    })
    .then(() => {
        // Finaly
    });
}

class CmsReassemblyError extends Error {
  constructor(message) {
    super(message);
    this.name = "CmsReassemblyError";
  }
}
