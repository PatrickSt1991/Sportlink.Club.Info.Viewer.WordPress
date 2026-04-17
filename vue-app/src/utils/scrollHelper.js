import { ref } from 'vue';

export function useScrollHelper(router, config) {
    const scrollInterval           = ref(null);
    const scrollingContainerHeight = ref('300px');
    const scrollPosition           = ref(0);
    const scrollCycleCount         = ref(0);
    const containerReady           = ref(false);
    const noContentTimeout         = ref(null);

    const calculateScrollingContainerHeight = () => {
        const fixed = config.value?.displayHeight;
        scrollingContainerHeight.value = (fixed && fixed > 0)
            ? `${fixed}px`
            : `${window.innerHeight - 265}px`;
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
            noContentTimeout.value = setTimeout(() => {
                if (config.value.enableScreenSwitch) {
                    const currentPath = router.currentRoute.value.path;
                    router.push(currentPath === '/match-info' ? '/match-results' : '/match-info');
                }
            }, 60000 + Math.random() * 60000);
            return;
        }

        scrollInterval.value = setInterval(() => {
            const speed = config.value?.scrollSpeed || 2;
            scrollPosition.value += speed;
            container.scrollTop   = scrollPosition.value;

            if (scrollPosition.value >= scrollHeight) {
                scrollPosition.value   = 0;
                scrollCycleCount.value += 1;
                container.scrollTop    = 0;

                if (scrollCycleCount.value >= 2 && config.value.enableScreenSwitch) {
                    stopScrolling();
                    const currentPath = router.currentRoute.value.path;
                    router.push(currentPath === '/match-info' ? '/match-results' : '/match-info');
                }
            }
        }, 50);
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
