import { useEffect, useRef, useState } from 'react';
import { lang } from '../i18n';
import { nodeAttributes } from '../store';
import { cx } from '../ui';
import type { UiNode } from '../types';

interface Series {
  name: string;
  values: number[];
}

type ChartNode = UiNode & {
  kind: 'line' | 'bar' | 'ranking';
  title: string;
  description?: string;
  labels?: string[];
  series?: Series[];
  unit?: string;
  height?: number;
  span?: number;
};

const COLORS = ['var(--v-accent)', 'var(--v-fg-3)', '#2563eb', '#c2410c', '#7c3aed'];
const SPANS: Record<number, string> = { 2: 'md:col-span-2', 3: 'md:col-span-3', 4: 'md:col-span-4' };

const locale = () => (lang() === 'ru' ? 'ru-RU' : 'en-US');
const formatValue = (value: number, unit?: string) => `${value.toLocaleString(locale(), { maximumFractionDigits: 2 })}${unit ? ` ${unit}` : ''}`;
const formatAxis = (value: number) => value.toLocaleString(locale(), { notation: 'compact', maximumFractionDigits: 1 });

/** 0 … a round maximum in 4 steps. */
function ticks(max: number): number[] {
  if (max <= 0) return [0, 1];
  const rough = max / 4;
  const power = 10 ** Math.floor(Math.log10(rough));
  const step = [1, 2, 2.5, 5, 10].map((f) => f * power).find((candidate) => candidate >= rough) ?? rough;
  return Array.from({ length: Math.ceil(max / step) + 1 }, (_, index) => index * step);
}

function useWidth() {
  const ref = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(600);
  useEffect(() => {
    const element = ref.current;
    if (!element) return;
    const observer = new ResizeObserver(([entry]) => entry && setWidth(entry.contentRect.width));
    observer.observe(element);
    return () => observer.disconnect();
  }, []);
  return [ref, width] as const;
}

function Legend({ series }: { series: Series[] }) {
  if (series.length < 2) return null;
  return (
    <div className="flex flex-wrap gap-4 text-[13px] text-fg-2">
      {series.map((item, index) => (
        <span key={item.name} className="flex items-center gap-2">
          <span aria-hidden="true" className="h-0.5 w-3.5 rounded-full" style={{ background: COLORS[index % COLORS.length] }} />
          {item.name}
        </span>
      ))}
    </div>
  );
}

/** The numbers behind the picture, for screen readers. */
function DataTable({ node, labels, series }: { node: ChartNode; labels: string[]; series: Series[] }) {
  return (
    <table className="sr-only">
      <caption>{node.title}</caption>
      <thead>
        <tr>
          <th scope="col" />
          {series.map((item) => (
            <th key={item.name} scope="col">
              {item.name}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {labels.map((label, index) => (
          <tr key={`${label}-${index}`}>
            <th scope="row">{label}</th>
            {series.map((item) => (
              <td key={item.name}>{formatValue(item.values[index] ?? 0, node.unit)}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function AxisChart({ node, labels, series }: { node: ChartNode; labels: string[]; series: Series[] }) {
  const [ref, width] = useWidth();
  const [hover, setHover] = useState<number | null>(null);
  const height = node.height ?? 220;
  const axis = 44;
  const plot = { left: axis, right: width - 4, top: 8, bottom: height - 24 };
  const max = Math.max(0, ...series.flatMap((item) => item.values));
  const scale = ticks(max);
  const top = scale[scale.length - 1] || 1;
  const count = Math.max(1, labels.length);
  const bar = node.kind === 'bar';
  const slot = (plot.right - plot.left) / (bar ? count : Math.max(1, count - 1));
  const x = (index: number) => plot.left + (bar ? slot * (index + 0.5) : slot * index);
  const y = (value: number) => plot.bottom - (value / top) * (plot.bottom - plot.top);
  const every = Math.max(1, Math.ceil(count / Math.max(2, Math.floor((plot.right - plot.left) / 72))));
  const gradient = `vanda-${node.id.replace(/[^\w-]/g, '-')}-fill`;

  return (
    <div ref={ref} className="relative" onMouseLeave={() => setHover(null)}>
      <svg
        width={width}
        height={height}
        role="img"
        aria-label={node.title}
        className="block overflow-visible"
        onMouseMove={(event) => {
          const box = event.currentTarget.getBoundingClientRect();
          const offset = event.clientX - box.left - plot.left;
          const index = Math.round(bar ? offset / slot - 0.5 : offset / slot);
          setHover(index >= 0 && index < count ? index : null);
        }}
      >
        {scale.map((tick) => (
          <g key={tick}>
            <line x1={plot.left} x2={plot.right} y1={y(tick)} y2={y(tick)} stroke="var(--v-line)" strokeDasharray={tick === 0 ? undefined : '3 4'} />
            <text x={plot.left - 8} y={y(tick)} dy="0.32em" textAnchor="end" className="fill-fg-3 text-[11.5px] tabular-nums">
              {formatAxis(tick)}
            </text>
          </g>
        ))}

        {labels.map((label, index) =>
          index % every === 0 ? (
            <text key={`${label}-${index}`} x={x(index)} y={height - 6} textAnchor="middle" className="fill-fg-3 text-[11.5px]">
              {label}
            </text>
          ) : null,
        )}

        {hover !== null && !bar && <line x1={x(hover)} x2={x(hover)} y1={plot.top} y2={plot.bottom} stroke="var(--v-line-strong)" />}

        {bar
          ? series.map((item, seriesIndex) => {
              const group = Math.min(slot * 0.7, 18 * series.length + 6);
              const width = group / series.length - 2;
              return item.values.map((value, index) => (
                <rect
                  key={`${item.name}-${index}`}
                  x={x(index) - group / 2 + seriesIndex * (width + 2)}
                  y={y(value)}
                  width={Math.max(1, width)}
                  height={Math.max(0, plot.bottom - y(value))}
                  rx={3}
                  fill={COLORS[seriesIndex % COLORS.length]}
                  opacity={hover === null || hover === index ? 1 : 0.45}
                />
              ));
            })
          : [...series].reverse().map((item, reversed) => {
              const seriesIndex = series.length - 1 - reversed;
              const points = item.values.map((value, index) => `${x(index)},${y(value)}`).join(' ');
              const color = COLORS[seriesIndex % COLORS.length];
              return (
                <g key={item.name}>
                  {seriesIndex === 0 && (
                    <>
                      <defs>
                        <linearGradient id={gradient} x1="0" x2="0" y1="0" y2="1">
                          <stop offset="0%" stopColor="var(--v-accent)" stopOpacity="0.16" />
                          <stop offset="100%" stopColor="var(--v-accent)" stopOpacity="0" />
                        </linearGradient>
                      </defs>
                      <polygon points={`${x(0)},${plot.bottom} ${points} ${x(item.values.length - 1)},${plot.bottom}`} fill={`url(#${gradient})`} />
                    </>
                  )}
                  <polyline points={points} fill="none" stroke={color} strokeWidth={seriesIndex === 0 ? 2 : 1.5} strokeDasharray={seriesIndex === 0 ? undefined : '4 4'} strokeLinejoin="round" strokeLinecap="round" />
                  {hover !== null && item.values[hover] !== undefined && <circle cx={x(hover)} cy={y(item.values[hover]!)} r={3.5} fill="var(--v-surface)" stroke={color} strokeWidth={2} />}
                </g>
              );
            })}
      </svg>

      {hover !== null && (
        <div
          role="presentation"
          className="pointer-events-none absolute top-0 z-10 flex min-w-40 flex-col gap-1.5 rounded-lg border border-line bg-surface px-3 py-2 text-[13px] shadow-pop"
          style={{ left: Math.min(x(hover) + 12, width - 180) }}
        >
          <span className="font-medium">{labels[hover]}</span>
          {series.map((item, index) => (
            <span key={item.name} className="flex items-center justify-between gap-4">
              <span className="flex items-center gap-2 text-fg-2">
                <span className="size-2 rounded-full" style={{ background: COLORS[index % COLORS.length] }} />
                {item.name}
              </span>
              <span className="font-medium tabular-nums">{formatValue(item.values[hover] ?? 0, node.unit)}</span>
            </span>
          ))}
        </div>
      )}
    </div>
  );
}

function Ranking({ node, labels, series }: { node: ChartNode; labels: string[]; series: Series[] }) {
  const values = series[0]?.values ?? [];
  const rows = labels.map((label, index) => ({ label, value: values[index] ?? 0 })).sort((a, b) => b.value - a.value);
  const max = Math.max(1, ...rows.map((row) => row.value));

  return (
    <ol className="flex flex-col gap-4">
      {rows.map((row) => (
        <li key={row.label} className="flex flex-col gap-1.5">
          <span className="flex justify-between gap-3 text-[14px]">
            <span className="truncate">{row.label}</span>
            <span className="font-medium whitespace-nowrap tabular-nums">{formatValue(row.value, node.unit)}</span>
          </span>
          <span aria-hidden="true" className="h-1.5 overflow-hidden rounded-full bg-surface-2">
            <span className="block h-full rounded-full bg-accent" style={{ width: `${(row.value / max) * 100}%` }} />
          </span>
        </li>
      ))}
    </ol>
  );
}

export function ChartView({ node }: { node: ChartNode }) {
  const labels = node.labels ?? [];
  const series = node.series ?? [];

  return (
    <section className={cx('flex min-w-0 flex-col gap-4 rounded-xl border border-line bg-surface p-5 shadow-sm', SPANS[node.span ?? 1])} {...nodeAttributes(node)}>
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-col gap-0.5">
          <h2 className="text-[15px] font-semibold">{node.title}</h2>
          {node.description && <span className="text-[13.5px] text-fg-3 tabular-nums">{node.description}</span>}
        </div>
        {node.kind !== 'ranking' && <Legend series={series} />}
      </header>
      {node.kind === 'ranking' ? <Ranking node={node} labels={labels} series={series} /> : <AxisChart node={node} labels={labels} series={series} />}
      {node.kind !== 'ranking' && <DataTable node={node} labels={labels} series={series} />}
    </section>
  );
}
