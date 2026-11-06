import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useId, useRef, useState } from 'react';
import { t } from '../i18n';
import { buttonClass, controlClass, cx, Icon, Spinner } from '../ui';
import type { FieldNode } from '../types';
import { FieldFrame, useField } from './fields';

// Leaflet loads in its own chunk, only on screens with a Map field.

type Point = { lat: number; lng: number };

type MapNode = FieldNode & {
  center: Point;
  zoom: number;
  height: number;
  tiles: string;
  attribution?: string;
  /** A Nominatim-compatible search URL, or nothing when search is off. */
  geocoder?: string | null;
};

interface Place {
  display_name: string;
  lat: string;
  lon: string;
}

const toPoint = (value: unknown): Point | null =>
  value && typeof value === 'object' && typeof (value as Point).lat === 'number' && typeof (value as Point).lng === 'number' ? (value as Point) : null;

const round = (point: L.LatLng): Point => ({ lat: Number(point.lat.toFixed(7)), lng: Number(point.lng.toFixed(7)) });

// A pin drawn in CSS: no marker images to resolve, and it takes the panel's accent colour.
const pin = L.divIcon({
  className: '',
  html: '<span style="display:block;width:22px;height:22px;border-radius:50% 50% 50% 0;background:var(--v-accent);border:2px solid #fff;transform:rotate(-45deg);box-shadow:0 2px 6px rgb(0 0 0 / .35)"></span>',
  iconSize: [22, 22],
  iconAnchor: [11, 22],
});

/** Map::make(): click or drag the pin, type the coordinates or find an address. */
export function MapField({ node }: { node: MapNode }) {
  const id = useId();
  const field = useField(node);
  const point = toPoint(field.value);
  const container = useRef<HTMLDivElement>(null);
  const map = useRef<L.Map | null>(null);
  const marker = useRef<L.Marker | null>(null);
  // Leaflet's handlers are bound once; they read the latest setter and state through refs.
  const setValue = useRef(field.setValue);
  setValue.current = field.setValue;
  const disabled = useRef(field.disabled);
  disabled.current = field.disabled;
  const [query, setQuery] = useState('');
  const [places, setPlaces] = useState<Place[] | null>(null);
  const [searching, setSearching] = useState(false);

  useEffect(() => {
    if (!container.current) return;
    const instance = L.map(container.current, { center: point ?? node.center, zoom: node.zoom, scrollWheelZoom: false });
    L.tileLayer(node.tiles, { attribution: node.attribution, maxZoom: 19 }).addTo(instance);
    // The wheel zooms only after a click on the map, so the page still scrolls past it.
    instance.on('focus', () => instance.scrollWheelZoom.enable());
    instance.on('blur', () => instance.scrollWheelZoom.disable());
    instance.on('click', (event: L.LeafletMouseEvent) => {
      if (!disabled.current) setValue.current(round(event.latlng));
    });
    map.current = instance;

    // Tabs and modals show the map after it was created: let Leaflet measure again.
    const observer = new ResizeObserver(() => instance.invalidateSize());
    observer.observe(container.current);

    return () => {
      observer.disconnect();
      instance.remove();
      map.current = null;
      marker.current = null;
    };
    // The map is created once; the value and the disabled state are followed below.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [node.tiles]);

  useEffect(() => {
    const instance = map.current;
    if (!instance) return;
    if (!point) {
      marker.current?.remove();
      marker.current = null;
      return;
    }
    if (!marker.current) {
      marker.current = L.marker(point, { icon: pin, draggable: !field.disabled, keyboard: true, title: node.label ?? '' }).addTo(instance);
      marker.current.on('dragend', () => marker.current && setValue.current(round(marker.current.getLatLng())));
    } else {
      marker.current.setLatLng(point);
    }
    if (!instance.getBounds().contains(point)) instance.panTo(point);
  }, [point?.lat, point?.lng, field.disabled, node.label]); // eslint-disable-line react-hooks/exhaustive-deps

  const search = async () => {
    if (!node.geocoder || query.trim() === '') return;
    setSearching(true);
    try {
      const url = new URL(node.geocoder);
      url.searchParams.set('format', 'json');
      url.searchParams.set('limit', '5');
      url.searchParams.set('q', query.trim());
      const response = await fetch(url, { headers: { Accept: 'application/json', 'Accept-Language': document.documentElement.lang || 'ru' } });
      setPlaces(response.ok ? ((await response.json()) as Place[]) : []);
    } catch {
      setPlaces([]);
    } finally {
      setSearching(false);
    }
  };

  const go = (place: Place) => {
    const next = { lat: Number(Number(place.lat).toFixed(7)), lng: Number(Number(place.lon).toFixed(7)) };
    field.setValue(next);
    map.current?.setView(next, Math.max(map.current.getZoom(), 16));
    setPlaces(null);
  };

  const coordinate = (key: keyof Point, raw: string) => {
    const number = Number(raw.replace(',', '.'));
    if (raw.trim() === '' || Number.isNaN(number)) return;
    field.setValue({ ...(point ?? node.center), [key]: number });
  };

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className={cx('overflow-hidden rounded-lg border border-line', field.error && 'border-danger')}>
        {node.geocoder && !field.disabled && (
          <form
            className="relative flex gap-2 border-b border-line p-2"
            onSubmit={(event) => {
              event.preventDefault();
              void search();
            }}
          >
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('mapSearch')} aria-label={t('mapSearch')} className={cx(controlClass, 'min-w-0 flex-1')} />
            <button type="submit" className={buttonClass('secondary')} disabled={searching}>
              {searching ? <Spinner /> : <Icon name="search" />}
              {t('find')}
            </button>
            {places && (
              <ul className="absolute top-full right-2 left-2 z-[1000] mt-1 max-h-64 overflow-auto rounded-xl border border-line bg-surface p-1 shadow-pop">
                {places.length === 0 && <li className="px-2 py-1.5 text-[13.5px] text-fg-3">{t('noResults')}</li>}
                {places.map((place) => (
                  <li key={`${place.lat},${place.lon}`}>
                    <button type="button" onClick={() => go(place)} className="w-full rounded-md px-2 py-1.5 text-left text-[13.5px] hover:bg-surface-2">
                      {place.display_name}
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </form>
        )}
        <div ref={container} id={id} style={{ height: node.height }} className="z-0 bg-surface-2" />
      </div>
      <div className="flex flex-wrap items-center gap-2">
        {(['lat', 'lng'] as const).map((key) => (
          <label key={key} className="flex items-center gap-1.5 text-[12.5px] text-fg-2">
            {t(key === 'lat' ? 'latitude' : 'longitude')}
            <input
              key={point ? `${key}-${point[key]}` : `${key}-empty`}
              defaultValue={point ? String(point[key]) : ''}
              onBlur={(event) => coordinate(key, event.target.value)}
              onKeyDown={(event) => event.key === 'Enter' && (event.preventDefault(), coordinate(key, event.currentTarget.value))}
              inputMode="decimal"
              disabled={field.disabled}
              className={cx(controlClass, 'h-8 w-32 font-mono text-[12.5px] tabular-nums')}
            />
          </label>
        ))}
        {point && !field.disabled && !field.required && (
          <button type="button" className={buttonClass('ghost', 'sm')} onClick={() => field.setValue(null)}>
            {t('clear')}
          </button>
        )}
      </div>
    </FieldFrame>
  );
}
