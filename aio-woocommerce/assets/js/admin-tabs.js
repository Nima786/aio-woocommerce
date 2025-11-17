document.addEventListener('DOMContentLoaded', function () {
    const sidebarLinks = document.querySelectorAll('.aio-wc-sidebar__link');
    const tabContents = document.querySelectorAll('.aio-wc-tab-content');

    // Function to show a tab based on ID
    function showTab(tabId) {
        // Handle main tabs
        tabContents.forEach(content => {
            content.style.display = content.id === tabId ? 'block' : 'none';
        });

        sidebarLinks.forEach(link => {
            if (link.dataset.tab === tabId) {
                link.classList.add('aio-wc-sidebar__link--active');
            } else {
                link.classList.remove('aio-wc-sidebar__link--active');
            }
        });
    }

    // Function to show a sub-tab within a main tab
    function showSubTab(mainTabId, subTabId) {
        const mainTabContent = document.getElementById(mainTabId);
        if (!mainTabContent) return;

        const subTabLinks = mainTabContent.querySelectorAll('.aio-wc-sub-nav__link');
        const subTabContents = mainTabContent.querySelectorAll('.aio-wc-sub-tab-content');

        subTabContents.forEach(content => {
            content.style.display = 'none';
            content.classList.remove('aio-wc-sub-tab-content--active');
        });

        subTabLinks.forEach(link => {
            link.classList.remove('aio-wc-sub-nav__link--active');
        });

        const activeSubTabContent = mainTabContent.querySelector(`#sub-tab-${subTabId}`);
        const activeSubTabLink = mainTabContent.querySelector(`[data-sub-tab="${subTabId}"]`);

        if (activeSubTabContent) {
            activeSubTabContent.style.display = 'block';
            activeSubTabContent.classList.add('aio-wc-sub-tab-content--active');
        }

        if (activeSubTabLink) {
            activeSubTabLink.classList.add('aio-wc-sub-nav__link--active');
        }
    }

    // Function to handle URL hash
    function showTabFromHash() {
        let hash = window.location.hash.substring(1);
        let mainTabId, subTabId;

        if (hash.includes('/')) {
            [mainTabId, subTabId] = hash.split('/');
        } else {
            mainTabId = hash;
        }

        if (mainTabId) {
            showTab(mainTabId);
            if (subTabId) {
                showSubTab(mainTabId, subTabId);
            }
        } else {
            // Show the first tab by default if no hash
            if (sidebarLinks.length > 0) {
                showTab(sidebarLinks[0].dataset.tab);
            }
        }
    }

    // Handle main sidebar link clicks
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const tabId = this.dataset.tab;
            history.pushState(null, null, `#${tabId}`);
            showTab(tabId);
        });
    });

    // Handle sub-tab link clicks using event delegation
    document.body.addEventListener('click', function(e) {
        const subTabLink = e.target.closest('.aio-wc-sub-nav__link');
        if (subTabLink) {
            e.preventDefault();
            const subTabId = subTabLink.dataset.subTab;
            const mainTabContent = subTabLink.closest('.aio-wc-tab-content');
            
            if (mainTabContent && subTabId) {
                const mainTabId = mainTabContent.id;
                history.pushState(null, null, `#${mainTabId}/${subTabId}`);
                showSubTab(mainTabId, subTabId);
            }
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', showTabFromHash);

    // Initial load
    showTabFromHash();
});