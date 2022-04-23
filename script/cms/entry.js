/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2017 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */
var TM_Entry = function() {
    this.onLoad(this, 'init');
};
TM_Entry.prototype.onClick = function(ev) {
    ev.preventDefault();
    var el = ev.target;
    switch(el.name){
        case 'preview':
            TM.entry.preview();
            break;
        case 'remove':
            TM.entry.sendDelete(el);
            break;
        case 'removeRelay':
            TM.entry.sendDeleteRelay(el);
            break;
        case 'trash':
            TM.entry.intoTrash(el);
            break;
    }
};
TM_Entry.prototype.preview = function() {
    var div, ifr, nav, ipt;
    div = document.body.appendChild(document.createElement('div'));
    div.id = 'previewScreen';
    ifr = div.appendChild(document.createElement('iframe'));
    ifr.id = 'previewFrame';
    ifr.src = 'about:blank';
    ifr.contentWindow.name = ifr.id;

    nav = document.createElement('nav');

    ipt = nav.appendChild(document.createElement('input'));
    ipt.type = 'button';
    ipt.value = 'キャンセル';
    ipt.addEventListener('click', this.cancelPreview, false);

    ipt = nav.appendChild(document.createElement('input'));
    ipt.type = 'button';
    ipt.value = '保存';
    ipt.addEventListener('click', this.saveEntry, false);

    nav.id = 'previewControler';
    document.body.appendChild(nav);

    var form = document.getElementById('TMS-mainform');
    if(form){
        var escTarget = form.target;
        var escMode = form.mode.value;
        form.target = 'previewFrame';
        form.mode.value = 'cms.entry.response:preview';
        form.submit();
        form.target = escTarget;
        form.mode.value = escMode;
    }
};
TM_Entry.prototype.removePreviewImages = function(ev) {
    var form = document.getElementById('TMS-mainform');
    if(form){
        var formData = new FormData();
        formData.append('stub', form.stub.value);
        formData.append('mode', 'cms.entry.receive:removePreviewImages');
        var request = new XMLHttpRequest();
        request.open('POST', form.action, true);
        request.send(formData);
    }
};
TM_Entry.prototype.cancelPreview = function(ev) {
    TM.entry.removePreviewImages();
    var div;
    div = document.getElementById('previewScreen');
    if(div) div.parentNode.removeChild(div);
    div = document.getElementById('previewControler');
    if(div) div.parentNode.removeChild(div);
};
TM_Entry.prototype.saveEntry = function(ev) {
    TM.entry.removePreviewImages();
    var form = document.getElementById('TMS-mainform');
    if(form){
        var evn = document.createEvent('Event');
        evn.initEvent('externalsubmit', true, true);
        form.dispatchEvent(evn);
    }
};
TM_Entry.prototype.sendDelete = function(el) {
    var form = el.form;
    if(form){
        form.dataset.confirm = encodeURIComponent('削除します。よろしいですか？');
        form.mode.value = 'cms.section.receive:remove';

        var evn = document.createEvent('Event');
        evn.initEvent('externalsubmit', true, true);
        form.dispatchEvent(evn);
    }
};
TM_Entry.prototype.sendDeleteRelay = function(el) {
    var form = el.form;
    if(form){
        form.dataset.confirm = encodeURIComponent('削除します。よろしいですか？');
        form.mode.value = 'cms.entry.receive:removeRelay';

        var evn = document.createEvent('Event');
        evn.initEvent('externalsubmit', true, true);
        form.dispatchEvent(evn);
    }
};
TM_Entry.prototype.trash = function(el) {
    var form = el.form;
    if(form){
        form.mode.value = 'cms.entry.receive:trash';

        var evn = document.createEvent('Event');
        evn.initEvent('externalsubmit', true, true);
        form.dispatchEvent(evn);
    }
};
TM_Entry.prototype.init = function(ev) {
    var e,i,n;
    var names = ['preview','remove'];
    for (n = 0; n < names.length; n++) {
        e = document.getElementsByName(names[n]);
        if (e) {
            for(i = 0; i < e.length; i++){
                e[i].addEventListener('click', this.onClick, false);
            }
        }
    }
};
TM_Entry.prototype.onLoad = function(scope, func) {
    addEventListener('load', function(ev){ scope[func](ev); }, false);
};
// Create instance
if(!window.TM) window.TM = new TM_Common();
TM.entry = new TM_Entry();
