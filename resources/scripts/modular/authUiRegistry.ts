import discoveredModuleRouteComponents from './discoverModuleRouteComponents';
import type {
    AuthUiExtensionRegistry,
    ModuleFrontendRegistryModule,
    ModuleRouteComponentRegistries,
    ModularFrontendRegistryPayload,
} from './routeTypes';

const generatedRoutes = discoveredModuleRouteComponents as ModuleRouteComponentRegistries;
const isBrowser = typeof window !== 'undefined';

const getFrontendModules = (frontendRegistry?: ModularFrontendRegistryPayload): ModuleFrontendRegistryModule[] => {
    if (frontendRegistry?.modules) {
        return frontendRegistry.modules.filter((module) => module.has_frontend);
    }

    if (!isBrowser || !window.ModularFrontendRegistry?.modules) {
        return [];
    }

    return window.ModularFrontendRegistry.modules.filter((module) => module.has_frontend);
};

export const buildAuthUiExtensionRegistry = (
    frontendRegistry?: ModularFrontendRegistryPayload,
    moduleRouteComponents: ModuleRouteComponentRegistries = generatedRoutes
): AuthUiExtensionRegistry => {
    const modules = getFrontendModules(frontendRegistry);

    return {
        loginActions: modules
            .filter((module) => module.zones.includes('auth.login-actions'))
            .map((module) => moduleRouteComponents[module.slug]?.authUi?.loginActions)
            .filter((component): component is NonNullable<typeof component> => !!component),
    };
};

export const authUiRegistry = buildAuthUiExtensionRegistry();

export default authUiRegistry;
