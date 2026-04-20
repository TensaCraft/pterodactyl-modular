import type { ModuleRouteComponentRegistries, ModuleRouteComponentRegistry } from './routeTypes';

type ModuleRoutesModule = {
    default?: ModuleRouteComponentRegistry;
    slug?: string;
};

const routesContext = require.context('../../../Modules', true, /Resources\/scripts\/routes\.tsx?$/);

const pascalCaseToKebabCase = (value: string) =>
    value
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
        .toLowerCase();

const deriveSlugFromContextKey = (key: string): string | null => {
    const match = key.match(/^\.\/([^/]+)\/Resources\/scripts\/routes\.tsx?$/);

    if (!match) {
        return null;
    }

    return pascalCaseToKebabCase(match[1]);
};

const discoverModuleRouteComponents = (): ModuleRouteComponentRegistries => {
    const registries: ModuleRouteComponentRegistries = {};

    routesContext.keys().forEach((key) => {
        const moduleDefinition = routesContext(key) as ModuleRoutesModule;
        const slug = moduleDefinition.slug ?? deriveSlugFromContextKey(key);

        if (!slug || !moduleDefinition.default) {
            return;
        }

        registries[slug] = moduleDefinition.default;
    });

    return registries;
};

export const discoveredModuleRouteComponents = discoverModuleRouteComponents();

export default discoveredModuleRouteComponents;
