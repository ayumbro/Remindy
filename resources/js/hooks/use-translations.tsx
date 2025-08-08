import { usePage } from '@inertiajs/react';

interface Translations {
    app: Record<string, any>;
    auth: Record<string, any>;
    subscriptions: Record<string, any>;
    [key: string]: Record<string, any>;
}

interface LocaleData {
    current: string;
    supported: Record<string, string>;
}

/**
 * Hook to access translations and locale information
 */
export function useTranslations() {
    const { translations, locale } = usePage().props as {
        translations: Translations;
        locale: LocaleData;
    };

    /**
     * Get a translation by key using dot notation
     * @param key - The translation key (e.g., 'app.nav.dashboard')
     * @param replacements - Object with placeholder replacements
     * @param fallback - Fallback text if translation not found
     */
    const t = (key: string, replacements: Record<string, any> = {}, fallback?: string): string => {
        const keys = key.split('.');
        let value: any = translations;

        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = value[k];
            } else {
                return fallback || key;
            }
        }

        if (typeof value !== 'string') {
            return fallback || key;
        }

        // Replace placeholders like :name with actual values
        let result = value;
        Object.entries(replacements).forEach(([placeholder, replacement]) => {
            result = result.replace(new RegExp(`:${placeholder}`, 'g'), String(replacement));
        });

        return result;
    };

    /**
     * Handle pluralization
     * @param key - The translation key
     * @param count - The count for pluralization
     * @param replacements - Object with placeholder replacements
     */
    const tChoice = (key: string, count: number, replacements: Record<string, any> = {}): string => {
        const translation = t(key, replacements);
        
        // Simple pluralization logic (can be enhanced)
        const parts = translation.split('|');
        
        if (parts.length === 1) {
            return translation;
        }
        
        // Basic plural rules (can be expanded for more languages)
        let index = count === 1 ? 0 : 1;
        
        // For languages with more complex plural rules
        if (locale.current === 'zh-CN' || locale.current === 'ja') {
            // Chinese and Japanese don't have plural forms
            index = 0;
        }
        
        return parts[index] || parts[0];
    };

    /**
     * Get all translations for a specific section
     */
    const tSection = (section: keyof Translations): Record<string, any> => {
        return translations[section] || {};
    };

    /**
     * Change the current locale
     */
    const changeLocale = (newLocale: string) => {
        window.location.href = `${window.location.pathname}?locale=${newLocale}`;
    };

    return {
        t,
        tChoice,
        tSection,
        translations,
        locale,
        changeLocale,
        currentLocale: locale.current,
        supportedLocales: locale.supported,
    };
}

// Export a shorthand function for quick access
export const useT = () => {
    const { t } = useTranslations();
    return t;
};