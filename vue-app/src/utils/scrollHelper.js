import { ref } from 'vue';

export function useScrollHelper(router, config) {
    const scrollInterval          = ref(null);
    const scrollingContainerHeight = ref('300px');
    const scrollPosition          = ref(0);
    const scrollCycleCount        = ref(0);
    const containerReady          = ref(false);
    const noContentTimeout        = ref(null);

    const calculateScrollingContainerHeight = () => {
        scrollingContainerHeight.value = `${window.innerHeight - 265}px`;
    };

    const startScrolling = () => {
        const container = document.getElementById('scrollingContainer');

        if (!container) {
            console.error('Scrolling container not found! Trying again...');
            setTimeout(tryStartScrolling, 100);
            return;
        }

        if (noContentTimeout.value) {
            clearTimeout(noContentTimeout.value);
            noContentTimeout.value = null;
        }

        containerReady.value   = true;
        scrollPosition.value   = 0;
        scrollCycleCount.value = 0;
        clearInterval(scrollInterval.value);
        container.scrollTop    = 0;

        const scrollHeight = container.scrollHeight - container.clientHeight;
        if (scrollHeight <= 0) {
            console.log('Not enough content to scroll');
            noContentTimeout.value = setTimeout(() => {
                if (config.value.enableScreenSwitch) {
                    const currentPath = router.currentRoute.value.path;
                    router.push(currentPath === '/match-info' ? '/match-results' : '/match-info');
                }
            }, 60000 + Math.random() * 60000);
            return;
        }

        scrollInterval.value = setInterval(() => {
            scrollPosition.value += 1;
            container.scrollTop  = scrollPosition.value;

            if (scrollPosition.value >= scrollHeight) {
                scrollPosition.value   = 0;
                scrollCycleCount.value += 1;
                container.scrollTop    = 0;

                if (scrollCycleCount.value >= 2 && config.value.enableScreenSwitch) {
                    stopScrolling();
                    // Toggle between match-info and match-results
                    const currentPath = router.currentRoute.value.path;
                    router.push(currentPath === '/match-info' ? '/match-results' : '/match-info');
                }
            }
        }, 100);
    };

    const tryStartScrolling = (attempt = 0) => {
        const maxAttempts = 5;
        const container   = document.getElementById('scrollingContainer');

        if (container) {
            containerReady.value = true;
            startScrolling();
        } else if (attempt < maxAttempts) {
            setTimeout(() => tryStartScrolling(attempt + 1), 200 * (attempt + 1));
        } else {
            console.error('Failed to find scrolling container after', maxAttempts, 'attempts');
        }
    };

    const stopScrolling = () => {
        clearInterval(scrollInterval.value);
        scrollInterval.value = null;
        if (noContentTimeout.value) {
            clearTimeout(noContentTimeout.value);
            noContentTimeout.value = null;
        }
    };

    return {
        scrollInterval,
        scrollingContainerHeight,
        scrollPosition,
        scrollCycleCount,
        containerReady,
        calculateScrollingContainerHeight,
        startScrolling,
        tryStartScrolling,
        stopScrolling
    };
}
