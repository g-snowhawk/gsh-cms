/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2017 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */
TM_Category = function (){
    this.form = undefined;
    this.formHeight = 0;
    this.onLoad(this, 'init');
};
TM_Category.prototype.init = function(ev) {
    var bt = document.getElementById('cat_submit');
    if (bt) {
        bt.addEventListener('click', this.onClick, false);
    }
    bt = document.getElementById('cat_cancel');
    if (bt) {
        bt.addEventListener('click', this.onClick, false);
    }

    this.form = document.getElementById('category-form');
    if (this.form) {
        this.formHeight = this.form.clientHeight;
        var err = this.form.getElementsByClassName('invalid');
        if(!err || err.length == 0) this.form.style.height = '0';
        bt = document.getElementById('new-category');
        if(bt) {
            bt.addEventListener('click', this.openForm, false);
        }
    }

    let trashButtons = document.getElementsByName('trash');
    Array.prototype.forEach.call(trashButtons, element => {
        element.addEventListener('click', this.intoTrash);
    })
};
TM_Category.prototype.intoTrash = function(event) {
    event.preventDefault();

    const element = event.target;
    const form = element.form;

    const pair = element.value.split(':');
    const kind = pair[0];
    const identifier = pair[1];

    let data = new FormData();
    data.append('stub', form.stub.value);
    data.append('mode', 'cms.' + kind + '.receive:trash');
    data.append('identifier', identifier);

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    }).then(response => response.json())
    .then((json) => {
        console.log(json);
        if (json.status === 0) {
            location.reload();
        } else {
            alert(json.message);
        }
    })
    .catch(error => console.error(error));
}
TM_Category.prototype.onClick = function(ev) {
    ev.preventDefault();
    var el = ev.target;
    switch(el.name){
        case 'cat_close':
            TM.category.closeForm(el);
            break;
        case 'cat_submit':
            var message = '';
            if(el.form.title.value == ''){
                el.form.title.parentNode.className.add('invalid');
                message += '\n和名を入力してください';
            }
            if(el.form.path.value == ''){
                el.form.path.parentNode.classList.add('invalid');
                message += '\n英名を入力してください';
            }
            if(message != ''){
                alert('未入力の項目があります' + message);
                return;
            }
            TM.category.submit(el);
            break;
    }
};
TM_Category.prototype.openForm = function(ev) {
    var cls = TM.category;
    cls.form.style.height = cls.formHeight + 'px';
};
TM_Category.prototype.closeForm = function(el) {
    var cls = TM.category;
    el.form.title.value = '';
    el.form.path.value = '';
    cls.form.style.height = '0px';
};
TM_Category.prototype.submit = function(el) {
    el.form.dataset.confirm = 'カテゴリを登録します。よろしいですか？';
    el.form.mode.value = 'cms.entry.receive:saveCategory';
    if(confirm(el.form.dataset.confirm)){
        el.form.submit();
    }
};
TM_Category.prototype.changeConfirm = function(el) {
    if (el.value.indexOf('entry:') != -1) {
        el.form.dataset.confirm = encodeURIComponent('エントリを削除します。\n内包する要素（画像、その他）があれば、全て削除され取り消しはできません。よろしいですか？');
    }
};
TM_Category.prototype.onLoad = function(scope, func) {
    addEventListener('load', function(ev){ scope[func](ev); }, false);
};

// Create instance
if(!window.TM) window.TM = new TM_Common();
TM.category = new TM_Category();
