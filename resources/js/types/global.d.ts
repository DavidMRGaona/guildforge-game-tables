export interface GuildForgeCookieConsentApi {
    /**
     * Whether the visitor consented to the cookie category.
     * Required categories always return true.
     */
    hasConsent(categorySlug: string): boolean;
}

declare global {
    // Published by the cookie banner of the cookie-consent module; absent when it is not on the page
    var GuildForgeCookieConsent: GuildForgeCookieConsentApi | undefined;
}
