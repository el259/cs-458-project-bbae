const tabList = document.querySelector('.auth-tabs');
const forms = document.querySelector('.auth-forms');

if (tabList && forms) {
    const tabs = Array.from(tabList.querySelectorAll('[role="tab"]'));

    function selectTab(tab) {
        tabs.forEach((item) => {
            const selected = item === tab;
            item.setAttribute('aria-selected', String(selected));
            item.tabIndex = selected ? 0 : -1;
            document.getElementById(item.getAttribute('aria-controls')).hidden = !selected;
        });
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('keydown', (event) => {
            let nextIndex;
            if (event.key === 'ArrowRight') {
                nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (index + tabs.length - 1) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            } else {
                return;
            }
            event.preventDefault();
            selectTab(tabs[nextIndex]);
            tabs[nextIndex].focus();
        });
    });

    selectTab(document.getElementById(`${forms.dataset.activeTab}-tab`));
    tabList.hidden = false;
}
