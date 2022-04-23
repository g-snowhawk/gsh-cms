/**
 * Javascript Library for G.Snowhawk Application
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2017 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */
var TM_Site = function() {
    TM.initModule(this.init, this, 'interactive');
};

TM_Site.prototype.siteSelection = function(event) {
    var element = event.currentTarget;
    if (element.checked) {
        TM.form.submit(element.form);
    }
}

TM_Site.prototype.init = function() {
    var elements = document.querySelectorAll('[name=choice]');
    for (var i = 0; i < elements.length; i++) {
        elements[i].addEventListener('click', this.siteSelection, false);
    }
};

TM.site = new TM_Site();
