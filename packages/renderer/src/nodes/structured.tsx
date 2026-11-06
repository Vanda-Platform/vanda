import { useId } from 'react';
import { t } from '../i18n';
import { nodeAttributes } from '../store';
import { buttonClass, cx, Icon } from '../ui';
import type { FieldNode, UiNode } from '../types';
import { FieldFrame, useField } from './fields';
import { NodeView } from './NodeView';

type Row = Record<string, unknown>;

const iconButton = 'grid size-7 shrink-0 place-items-center rounded-md text-fg-3 hover:bg-surface-2 hover:text-fg disabled:opacity-30';

/** Rows of the same fields; child paths are relative to the row ("form.specs.0.name"). */
export function RepeaterField({ node }: { node: FieldNode & { fields?: FieldNode[]; addLabel?: string; max?: number; table?: boolean } }) {
  const field = useField(node);
  const rows = (Array.isArray(field.value) ? field.value : []) as Row[];
  const templates = node.fields ?? [];
  const canAdd = node.max === undefined || rows.length < node.max;

  const move = (from: number, to: number) => {
    const next = [...rows];
    const [row] = next.splice(from, 1);
    next.splice(to, 0, row as Row);
    field.setValue(next);
  };

  const rowButtons = (index: number, vertical: boolean) => (
    <div className={cx('flex shrink-0 gap-0.5', vertical && 'flex-col')}>
      <button type="button" className={iconButton} aria-label={t('moveUp')} disabled={index === 0 || field.disabled} onClick={() => move(index, index - 1)}>
        <Icon name="chevron-up" />
      </button>
      <button type="button" className={iconButton} aria-label={t('moveDown')} disabled={index === rows.length - 1 || field.disabled} onClick={() => move(index, index + 1)}>
        <Icon name="chevron-down" />
      </button>
      <button type="button" className={iconButton} aria-label={t('remove')} disabled={field.disabled} onClick={() => field.setValue(rows.filter((__, i) => i !== index))}>
        <Icon name="x" />
      </button>
    </div>
  );

  const addButton = (
    <button type="button" className={cx(buttonClass('secondary', 'sm'), 'self-start')} disabled={!canAdd || field.disabled} onClick={() => field.setValue([...rows, {}])}>
      <Icon name="plus" />
      {node.addLabel ?? t('add')}
    </button>
  );

  // Repeater::table() / Matrix: one column per field, the labels become the header.
  if (node.table) {
    return (
      <div className="flex flex-col gap-2" data-vanda-path={node.path} {...nodeAttributes(node)}>
        {node.label && <span className="text-[13px] font-medium">{node.label}</span>}
        <div className="overflow-x-auto rounded-lg border border-line">
          <table className="w-full border-collapse text-[14px]">
            <thead>
              <tr className="bg-surface-2/60 text-left text-[12.5px] text-fg-2">
                {templates.map((template) => (
                  <th key={template.path} scope="col" className="px-2.5 py-2 font-medium">
                    {template.label ?? template.path}
                  </th>
                ))}
                <th scope="col" className="w-px">
                  <span className="sr-only">{t('actions')}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((_, index) => (
                <tr key={index} className="border-t border-line align-top">
                  {templates.map((template) => {
                    const path = `${node.path}.${index}.${template.path}`;
                    return (
                      <td key={path} className="p-1.5">
                        <NodeView node={{ ...template, id: path, path, labelHidden: true } as UiNode} />
                      </td>
                    );
                  })}
                  <td className="p-1.5 align-middle">{rowButtons(index, false)}</td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr className="border-t border-line">
                  <td colSpan={templates.length + 1} className="px-3 py-4 text-center text-[13.5px] text-fg-3">
                    {t('empty')}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        {addButton}
        {node.help && <span className="text-[12.5px] text-fg-3">{node.help}</span>}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-2" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {node.label && <span className="text-[13px] font-medium">{node.label}</span>}
      <ol className="flex flex-col gap-2">
        {rows.map((_, index) => (
          <li key={index} className="flex items-start gap-2 rounded-lg border border-line p-2.5">
            <div className={cx('grid min-w-0 flex-grow gap-2.5', templates.length > 1 ? 'md:grid-cols-2' : 'grid-cols-1')}>
              {templates.map((template) => {
                const path = `${node.path}.${index}.${template.path}`;
                return <NodeView key={path} node={{ ...template, id: path, path } as UiNode} />;
              })}
            </div>
            {rowButtons(index, true)}
          </li>
        ))}
      </ol>
      {addButton}
      {node.help && <span className="text-[12.5px] text-fg-3">{node.help}</span>}
    </div>
  );
}

export function KeyValueField({ node }: { node: FieldNode & { keyLabel?: string; valueLabel?: string; addLabel?: string } }) {
  const id = useId();
  const field = useField(node);
  const rows = (Array.isArray(field.value) ? field.value : []) as { key: string; value: string }[];
  const update = (index: number, key: 'key' | 'value', value: string) => field.setValue(rows.map((row, i) => (i === index ? { ...row, [key]: value } : row)));

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className="overflow-hidden rounded-lg border border-line-strong">
        <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_36px] bg-surface-2 text-[12.5px] font-medium text-fg-3">
          <span className="border-r border-line px-2.5 py-1.5">{node.keyLabel}</span>
          <span className="px-2.5 py-1.5">{node.valueLabel}</span>
        </div>
        {rows.map((row, index) => (
          <div key={index} className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_36px] border-t border-line">
            <input
              id={index === 0 ? id : undefined}
              value={row.key}
              onChange={(event) => update(index, 'key', event.target.value)}
              aria-label={`${node.keyLabel} ${index + 1}`}
              className="min-w-0 border-r border-line bg-transparent px-2.5 py-2 font-mono text-[13px] outline-none focus:bg-accent-soft"
            />
            <input
              value={row.value}
              onChange={(event) => update(index, 'value', event.target.value)}
              aria-label={`${node.valueLabel} ${index + 1}`}
              className="min-w-0 bg-transparent px-2.5 py-2 font-mono text-[13px] outline-none focus:bg-accent-soft"
            />
            <button type="button" className={cx(iconButton, 'm-auto')} aria-label={t('remove')} onClick={() => field.setValue(rows.filter((_, i) => i !== index))}>
              <Icon name="x" />
            </button>
          </div>
        ))}
      </div>
      <button type="button" className={cx(buttonClass('ghost', 'sm'), 'self-start')} onClick={() => field.setValue([...rows, { key: '', value: '' }])}>
        <Icon name="plus" />
        {node.addLabel ?? t('add')}
      </button>
    </FieldFrame>
  );
}

interface PermissionGroup {
  title: string;
  items: { key: string; label: string; sources?: string[] }[];
}

/**
 * Role: each permission on/off. User (tristate): inherit from roles, allow or deny,
 * with the roles that grant it shown next to it.
 */
export function PermissionsField({ node }: { node: FieldNode & { groups?: PermissionGroup[]; tristate?: boolean } }) {
  const field = useField(node);
  const value = (field.value && typeof field.value === 'object' && !Array.isArray(field.value) ? field.value : {}) as Record<string, boolean>;
  const set = (key: string, next: boolean | null) => {
    const copy = { ...value };
    if (next === null) delete copy[key];
    else copy[key] = next;
    field.setValue(copy);
  };

  return (
    <div className="flex flex-col gap-1" data-vanda-path={node.path} {...nodeAttributes(node)}>
      {node.label && <span className="mb-1 text-[13px] font-medium">{node.label}</span>}
      <div className="overflow-hidden rounded-xl border border-line">
        {(node.groups ?? []).map((group) => (
          <div key={group.title}>
            <div className="bg-surface-2 px-4 pt-3 pb-1.5 text-[11.5px] font-semibold tracking-[0.06em] text-fg-3 uppercase">{group.title}</div>
            <ul>
              {group.items.map((item) => {
                const own = item.key in value ? value[item.key] : null;
                const inherited = (item.sources ?? []).length > 0;
                const effective = own ?? inherited;

                return (
                  <li key={item.key} className="flex flex-wrap items-center gap-3 border-t border-line px-4 py-2.5">
                    <div className="flex min-w-0 flex-grow flex-col">
                      <span className="text-[14px] font-medium">{item.label}</span>
                      <span className="font-mono text-[12px] text-fg-3">{item.key}</span>
                    </div>

                    {node.tristate ? (
                      <>
                        <span
                          className={cx(
                            'inline-flex h-[22px] items-center rounded-full border px-2 text-[12px] font-medium whitespace-nowrap',
                            own === null ? 'border-line bg-surface-2 text-fg-2' : own ? 'border-transparent bg-accent-soft text-accent-fg' : 'border-transparent bg-danger-soft text-danger-fg',
                          )}
                        >
                          {own === null ? (inherited ? t('fromRoles', { roles: (item.sources ?? []).join(', ') }) : t('notGranted')) : own ? t('allow') : t('deny')}
                        </span>
                        <div role="radiogroup" aria-label={item.label} className="inline-flex gap-0.5 rounded-lg border border-line bg-surface-2 p-0.5">
                          {([null, true, false] as const).map((option) => (
                            <button
                              key={String(option)}
                              type="button"
                              role="radio"
                              aria-checked={own === option}
                              onClick={() => set(item.key, option)}
                              className={cx(
                                'h-6 rounded-md px-2 text-[12.5px] font-medium text-fg-2 hover:text-fg',
                                own === option && 'bg-surface text-fg shadow-sm ring-1 ring-line',
                              )}
                            >
                              {option === null ? t('inherit') : option ? t('allow') : t('deny')}
                            </button>
                          ))}
                        </div>
                        <span aria-hidden="true" className={cx('size-2 rounded-full', effective ? 'bg-success' : 'bg-line-strong')} />
                      </>
                    ) : (
                      <button
                        type="button"
                        role="switch"
                        aria-checked={Boolean(own)}
                        aria-label={item.label}
                        onClick={() => set(item.key, own ? null : true)}
                        className={cx('flex h-[18px] w-8 shrink-0 items-center rounded-full px-0.5 transition-colors', own ? 'bg-accent' : 'bg-line-strong')}
                      >
                        <span className={cx('size-3.5 rounded-full bg-white shadow transition-transform', own && 'translate-x-3.5')} />
                      </button>
                    )}
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </div>
    </div>
  );
}

