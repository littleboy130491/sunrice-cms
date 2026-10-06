import * as React from 'react';

interface TranslationMode {
    /** Editing a secondary language: layout and shared fields are read-only. */
    secondary: boolean;
    mainLocale?: string;
}

const TranslationModeContext = React.createContext<TranslationMode>({ secondary: false });

export function TranslationModeProvider({ secondary, mainLocale, children }: TranslationMode & { children: React.ReactNode }) {
    const value = React.useMemo(() => ({ secondary, mainLocale }), [secondary, mainLocale]);

    return <TranslationModeContext.Provider value={value}>{children}</TranslationModeContext.Provider>;
}

export function useTranslationMode(): TranslationMode {
    return React.useContext(TranslationModeContext);
}

/** Stable client-side id for new repeater rows and flexible blocks. */
export function newItemId(): string {
    return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
}
