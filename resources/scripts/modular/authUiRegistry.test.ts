import { buildAuthUiExtensionRegistry } from './authUiRegistry';
import type { ModuleRouteComponentRegistries, ModularFrontendRegistryPayload } from './routeTypes';

describe('authUiRegistry', () => {
    it('collects login action components from modules registered for the auth login zone', () => {
        const FirstAction = () => null;
        const SecondAction = () => null;

        const frontendRegistry: ModularFrontendRegistryPayload = {
            modules: [
                {
                    slug: 'panel-auth',
                    has_frontend: true,
                    entrypoint: 'Modules/PanelAuth/Resources/scripts/modular/routes.tsx',
                    zones: ['auth.login-actions'],
                    routes: {},
                },
                {
                    slug: 'dashboard-only',
                    has_frontend: true,
                    entrypoint: 'Modules/DashboardOnly/Resources/scripts/modular/routes.tsx',
                    zones: ['dashboard.server-list'],
                    routes: {},
                },
                {
                    slug: 'secondary-auth',
                    has_frontend: true,
                    entrypoint: 'Modules/SecondaryAuth/Resources/scripts/modular/routes.tsx',
                    zones: ['auth.login-actions'],
                    routes: {},
                },
            ],
        };

        const components: ModuleRouteComponentRegistries = {
            'panel-auth': {
                authUi: {
                    loginActions: FirstAction,
                },
            },
            'dashboard-only': {},
            'secondary-auth': {
                authUi: {
                    loginActions: SecondAction,
                },
            },
        };

        expect(buildAuthUiExtensionRegistry(frontendRegistry, components).loginActions).toEqual([
            FirstAction,
            SecondAction,
        ]);
    });
});
