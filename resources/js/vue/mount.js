import { createApp } from 'vue';
import WorkdayProgressWidget from './components/WorkdayProgressWidget.vue';
import WorkdayCommandCenter from './components/WorkdayCommandCenter.vue';

const registry = {
    'workday-progress-widget': WorkdayProgressWidget,
    'workday-command-center': WorkdayCommandCenter,
};

function parseProps(rawProps) {
    if (!rawProps || rawProps.trim() === '') {
        return {};
    }

    try {
        const parsed = JSON.parse(rawProps);

        if (parsed && typeof parsed === 'object') {
            return parsed;
        }
    } catch (error) {
        console.warn('Vue props konnten nicht geparsed werden.', error);
    }

    return {};
}

export function mountVueIslands() {
    document.querySelectorAll('[data-vue-component]').forEach((element) => {
        if (element.dataset.vueMounted === '1') {
            return;
        }

        const componentName = element.dataset.vueComponent;

        if (!componentName || !Object.prototype.hasOwnProperty.call(registry, componentName)) {
            return;
        }

        const component = registry[componentName];
        const props = parseProps(element.dataset.vueProps ?? '');

        createApp(component, props).mount(element);
        element.dataset.vueMounted = '1';
    });
}
