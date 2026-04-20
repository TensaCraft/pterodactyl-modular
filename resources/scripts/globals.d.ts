declare module '*.jpg';
declare module '*.png';
declare module '*.svg';
declare module '*.css';

interface Window {
    SiteConfiguration?: import('@/state/settings').SiteSettings;
    PterodactylUser?: {
        uuid: string;
        username: string;
        email: string;
        /* eslint-disable camelcase */
        root_admin: boolean;
        use_totp: boolean;
        language: string;
        updated_at: string;
        created_at: string;
        /* eslint-enable camelcase */
    };
    ModularFrontendRegistry?: import('@/modular/routeTypes').ModularFrontendRegistryPayload;
}
