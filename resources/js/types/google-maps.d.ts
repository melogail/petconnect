/// <reference types="google.maps" />

// `tsconfig.json` pins `types` to `vite/client`, so the ambient `google.maps`
// namespace that ships with `@googlemaps/js-api-loader` is not picked up on its
// own. This reference pulls it in from inside `resources/js`, which is where
// the map components live, rather than widening the compiler options.

declare module 'vite/client' {
    interface ImportMetaEnv {
        /**
         * Optional. When it is missing, `LocationMap` renders a coordinate
         * readout and a link out to Google Maps instead of an embedded map —
         * see the component for why that fallback exists.
         */
        readonly VITE_GOOGLE_MAPS_API_KEY?: string;
    }
}

export {};
