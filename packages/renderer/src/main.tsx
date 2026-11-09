import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { VandaPage } from './VandaPage';
import './styles.css';

function applyStoredTheme() {
  let theme = 'system';
  try {
    theme = localStorage.getItem('vanda:theme') ?? 'system';
  } catch {
    // Storage blocked: follow the system theme.
  }
  const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
}

/** The density the user picked in this browser, over the panel's (data-density from the server). */
function applyStoredDensity() {
  try {
    const density = localStorage.getItem('vanda:density');
    if (density === 'compact' || density === 'comfortable') document.documentElement.dataset.density = density;
  } catch {
    // Storage blocked: the panel's density.
  }
}

applyStoredTheme();
applyStoredDensity();

// Every screen is the same Inertia component: the page IR decides what is drawn.
void createInertiaApp({
  resolve: () => VandaPage,
  setup({ el, App, props }) {
    if (el) createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#12805C', delay: 120 },
});
