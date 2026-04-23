import type { ModuleFrontendRegistryModule, ModularFrontendRegistryPayload } from './routeTypes';

type ModularWindow = Window & {
    ModularFrontendRegistry?: ModularFrontendRegistryPayload;
};

export const getFrontendModules = (frontendRegistry?: ModularFrontendRegistryPayload): ModuleFrontendRegistryModule[] => {
    if (frontendRegistry?.modules) {
        return frontendRegistry.modules.filter((module) => module.has_frontend);
    }

    if (typeof window === 'undefined') {
        return [];
    }

    const registry = (window as ModularWindow).ModularFrontendRegistry;

    return registry?.modules?.filter((module) => module.has_frontend) ?? [];
};
