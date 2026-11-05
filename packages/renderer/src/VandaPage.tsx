import { useEffect, useState } from 'react';
import { I18nProvider } from 'react-aria-components';
import { App } from './App';
import { lang } from './i18n';
import { createStore, StoreContext } from './store';
import type { Page } from './types';

/**
 * The one Inertia page component. A store lives as long as the screen is on
 * screen; Inertia visits that keep state (sorting, search) feed it new pages.
 */
export function VandaPage({ page }: { page: Page }) {
  const [store] = useState(() => createStore(page));

  useEffect(() => store.receive(page), [store, page]);

  return (
    <I18nProvider locale={lang() === 'ru' ? 'ru-RU' : 'en-US'}>
      <StoreContext.Provider value={store}>
        <App />
      </StoreContext.Provider>
    </I18nProvider>
  );
}
