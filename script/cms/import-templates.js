
switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', tmsImportTemplatesInit)
        break;
    case 'interactive':
    case 'complete':
        tmsImportTemplatesInit();
        break;
}

function tmsImportTemplatesInit(event) {
    const element = document.querySelector('input[name=template_xml]');
    if (element) {
        element.addEventListener('change', tmsImportTemplateRun);
    }
}

function tmsImportTemplateRun(event) {
    const trigger = event.target;
    const form = trigger.form;
    if (!confirm(trigger.dataset.confirm)) {
        event.preventDefault();
        return;
    }

    const formData = new FormData(form);
    formData.set('mode', trigger.dataset.mode);

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then((response) => {
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }
            throw new Error("Unexpected response");
        } else {
            throw new Error("Server Error");
        }
    })
    .then((json) => {
        if (json.status !== 0) {
            throw new Error(json.message);
        }
        // some functions
        alert(json.message);
        form.reset();
        location.reload();
    })
    .catch((error) => {
        alert(error.message);
        console.error(error);
    });
}
