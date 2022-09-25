switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', openSiteInit)
        break;
    case 'interactive':
    case 'complete':
        openSiteInit();
        break;
}

function openSiteInit(event) {
    const elements = document.querySelectorAll('a.open-the-site');
    elements.forEach(function(element) {
        element.addEventListener('click', openSiteExec);
    });
}

function openSiteExec(event) {
    event.preventDefault();

    const element = event.target;
    const path = element.pathname.replace(/\/$/g, '');

    document.cookie = 'wherefrom=console; path=' + path;
    window.open(element.href, 'TMS-CMS-SITE');
}
