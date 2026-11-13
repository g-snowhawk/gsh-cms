/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2018 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */

var Explorer = function() {
    this.dragElement = undefined;
    this.cnDropTarget = 'lockon';
    this.cnRenameElement = 'rename-element';
    this.doubleClicked = false;
    this.onLoad(this, 'init');
};

Explorer.prototype.onClick = function(element) {
    if (this.doubleClicked) {
        return;
    }
    if (element.nodeName.toLowerCase() === 'a') {
        location.href = element.href;
    }
};

Explorer.prototype.onDoubleClick = function(element) {
    var value = element.firstChild.nodeValue;
    while (element.firstChild) {
        element.removeChild(element.firstChild);
    }

    var input = element.appendChild(document.createElement('input'));
    input.type = 'hidden';
    input.name = 'oldname';
    input.value = value;
    input.classList.add(this.cnRenameElement);

    var input = element.appendChild(document.createElement('input'));
    input.type = 'text';
    input.name = 'newname';
    input.value = value;
    input.classList.add(this.cnRenameElement);
    input.focus();
    input.addEventListener('keypress', this.listener, false);
    input.addEventListener('blur', this.listener, false);
};

Explorer.prototype.onDragStart = function(element) {
    this.dragElement = element;
};

Explorer.prototype.onDragEnd = function(element) {
    var i, max;
    var dropTarget = document.querySelectorAll('.' + this.cnDropTarget);
    for (i = 0, max = dropTarget.length; i < max; i++) {
        dropTarget[i].classList.remove(this.cnDropTarget);
    }
    this.dragElement = undefined;
};

Explorer.prototype.onDragLeave = function(element) {
    if (element.classList.contains(this.cnDropTarget)) {
        element.classList.remove(this.cnDropTarget);
    }
};

Explorer.prototype.onDragOver = function(element) {
    if (!element.classList.contains(this.cnDropTarget)) {
        element.classList.add(this.cnDropTarget);
    }
};

Explorer.prototype.onDrop = function(element) {
    if (element.classList.contains('current')) {
        return;
    }

    var dropMode = document.querySelector('[name=ondrop_mode]');
    var form = dropMode.form;
    form.mode.value = dropMode.value;

    var input = form.appendChild(document.createElement('input'));
    input.type = 'hidden';
    input.name = 'source';
    input.value = this.dragElement.firstChild.nodeValue;

    var input = form.appendChild(document.createElement('input'));
    input.type = 'hidden';
    input.name = 'dest';
    input.value = decodeURIComponent(element.dataset.dropPath);

    form.submit();
};

Explorer.prototype.onKeyPress = function(event) {
    var code = event.keyCode;
    if (code === 13) {
        event.preventDefault();
        this.rename();
    }
    if (code === 27) {
        var element = document.querySelector('[name=oldname].'+this.cnRenameElement);
        this.rewind(element.value);
    }
};

Explorer.prototype.onBlur = function(element) {
    var old = element.parentNode.querySelector('[name=oldname]');
    if (element.value === old.value || element.value == '') {
        return this.rewind(old.value);
    }

    this.rename();
}

Explorer.prototype.rewind = function(name) {
    var url = undefined;
    if (name.filename) {
        if (name.url) {
            url = name.url;
        }
        name = name.filename;
    }
    var element = document.querySelector('[name=newname].'+this.cnRenameElement);
    if (!element) {
        return;
    }
    element = element.parentNode;
    var old = element.querySelector('[name=oldname].'+this.cnRenameElement);
    if (name == '') {
        name = old.value;
    }

    while (element.firstChild) {
        element.removeChild(element.firstChild);
    }
    element.innerHTML = name;

    var nodes = document.querySelectorAll('[value*="'+old.value+'"]');
    for (i = 0, max = nodes.length; i < max; i++) {
        nodes[i].value = nodes[i].value.replace(old.value, name);
    }

    if (url) {
        var tr = element.findParent('tr');
        if (tr) {
            var td = tr.querySelector('td.url');
            if (td) {
                var a = td.querySelector('a');
                if (a) {
                    a.href = url;
                    a.innerHTML = url;
                }
            }
        }
    }
};

Explorer.prototype.rename = function() {
    var element = document.querySelector('[name=newname].'+this.cnRenameElement);
    var form = element.form;
    TM.xhr.init('POST', form.action, true, function(event){
        var instance = TM.explorer;
        if (this.status == 200) {
            try {
                var json = JSON.parse(this.responseText);
            } catch (exceptionObject) {
                console.error(exceptionObject.message);
                console.log(this.responseText);
                return;
            }
            if (json.response.type === 'callback') {
                TM.apply(json.response.source, json.arguments);
            }
            else {
                instance.rewind(json);
            }
        } else {
            // TODO: add error handling
            console.log(this.responseText);
        }
    });

    var data = new FormData(form);
    data.set('mode', data.get('rename_mode'));
    data.append('callback', 'TM.explorer.rewind');
    data.append('returntype', 'json');
    TM.xhr.send(data);
};

Explorer.prototype.listener = function(event) {
    var instance = TM.explorer;
    var element = event.currentTarget;
    switch (event.type) {
        case 'blur':
            instance.onBlur(element);
            break;
        case 'click':
            event.preventDefault();
            if (!instance.doubleClicked) {
                setTimeout(function() {
                    instance.onClick(element);
                }, 300);
            }
            break;
        case 'dblclick':
            event.preventDefault();
            instance.doubleClicked = true;
            instance.onDoubleClick(element);
            break;
        case 'dragend':
            instance.onDragEnd(element);
            break;
        case 'dragleave':
            instance.onDragLeave(element);
            break;
        case 'dragstart':
            instance.onDragStart(element);
            break;
        case 'dragover':
            event.preventDefault();
            instance.onDragOver(element);
            break;
        case 'drop':
            event.preventDefault();
            instance.onDrop(element);
            break;
        case 'keypress':
            instance.onKeyPress(event);
            break;
    }
};

Explorer.prototype.init = function(event) {
    var i, max, element;

    var cells = document.querySelectorAll('.with-icon');
    for (i = 0, max = cells.length; i < max; i++) {
        element = cells[i].querySelector('a, span');
        if (element.classList.contains('unrenamable')) {
            element.draggable = false;
            continue;
        }
        element.draggable = true;
        element.addEventListener('dragstart', this.listener, false);
        element.addEventListener('dragend', this.listener, false);
    }

    var drops = document.querySelectorAll('.drop-target');
    for (i = 0, max = drops.length; i < max; i++) {
        element = drops[i];
        element.addEventListener('dragleave', this.listener, false);
        element.addEventListener('dragover',  this.listener, false);
        element.addEventListener('drop',      this.listener, false);
    }

    var renames = document.querySelectorAll('.renamable');
    for (i = 0, max = renames.length; i < max; i++) {
        element = renames[i];
        element.addEventListener('dblclick', this.listener, false);
        element.addEventListener('click', this.listener, false);
    }
};

Explorer.prototype.onLoad = function(scope, func) {
    addEventListener(
        'DOMContentLoaded',
        function(event) {
            scope[func](event);
        },
        false
    );
};

// Create instance
window.TM = window.TM || new TM_Common();
TM.explorer = new Explorer();
