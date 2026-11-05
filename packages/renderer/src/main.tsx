import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import { createStore, StoreContext } from './store';
import type { Page } from './types';
import './styles.css';

const data = document.getElementById('vanda-page');
const root = document.getElementById('vanda-app');

if (data && root) {
  let theme = 'system';
  try {
    theme = localStorage.getItem('vanda:theme') ?? 'system';
  } catch {
    // Storage blocked: follow the system theme.
  }
  const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';

  const store = createStore(JSON.parse(data.textContent ?? '{}') as Page);

  createRoot(root).render(
    <StrictMode>
      <StoreContext.Provider value={store}>
        <App />
      </StoreContext.Provider>
    </StrictMode>,
  );
}
