import discoveredModuleRouteComponents from './discoverModuleRouteComponents';
import { getFrontendModules } from './frontendRegistry';
import type {
    AuthUiExtensionRegistry,
    ModuleRouteComponentRegistries,
    ModularFrontendRegistryPayload,
} from './routeTypes';

const generatedRoutes = discoveredModuleRouteComponents as ModuleRouteComponentRegistries;

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
