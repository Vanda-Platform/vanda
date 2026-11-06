import { useId, useState } from 'react';
import { Button as AriaButton, Dialog, DialogTrigger, Popover } from 'react-aria-components';
import { t } from '../i18n';
import { buttonClass, controlClass, cx, Icon, invalidClass } from '../ui';
import type { FieldNode } from '../types';
import { FieldFrame, useField } from './fields';

const TAGS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as const;

/** Splits "https://site.ru/page?x=1&utm_source=mail#top" so the tags can be edited apart from the rest. */
function parse(link: string): { url: URL | null; tags: Record<string, string> } {
  try {
    const url = new URL(link);
    return { url, tags: Object.fromEntries(TAGS.map((tag) => [tag, url.searchParams.get(tag) ?? ''])) };
  } catch {
    return { url: null, tags: Object.fromEntries(TAGS.map((tag) => [tag, ''])) };
  }
}

/** Utm::make(): a link with a panel for its utm_* tags. */
export function UtmField({ node }: { node: FieldNode }) {
  const id = useId();
  const field = useField(node);
  const link = typeof field.value === 'string' ? field.value : '';
  const { url, tags } = parse(link);
  const [draft, setDraft] = useState<Record<string, string>>(tags);

  const write = (next: Record<string, string>) => {
    if (!url) return;
    for (const tag of TAGS) {
      const value = next[tag]?.trim() ?? '';
      if (value) url.searchParams.set(tag, value);
      else url.searchParams.delete(tag);
    }
    field.setValue(url.toString());
  };

  const filled = TAGS.filter((tag) => tags[tag]).length;

  return (
    <FieldFrame node={node} inputId={id} required={field.required} error={field.error}>
      <div className="flex gap-2">
        <input
          id={id}
          type="url"
          inputMode="url"
          value={link}
          onChange={(event) => field.setValue(event.target.value)}
          placeholder={node.placeholder ?? 'https://'}
          disabled={field.disabled}
          readOnly={node.readonly}
          aria-invalid={field.error ? true : undefined}
          className={cx(controlClass, 'min-w-0 flex-1', field.error && invalidClass)}
        />
        <DialogTrigger onOpenChange={(open) => open && setDraft(tags)}>
          <AriaButton isDisabled={!url || field.disabled} className={cx(buttonClass('secondary'), 'shrink-0')}>
            <Icon name="link" />
            UTM{filled > 0 && <span className="text-fg-3 tabular-nums">{filled}</span>}
          </AriaButton>
          <Popover placement="bottom end" className="w-[340px] rounded-xl border border-line bg-surface p-4 shadow-pop outline-none">
            <Dialog aria-label={t('utmTitle')} className="flex flex-col gap-3 outline-none">
              {({ close }) => (
                <form
                  className="flex flex-col gap-3"
                  onSubmit={(event) => {
                    event.preventDefault();
                    write(draft);
                    close();
                  }}
                >
                  {TAGS.map((tag, index) => (
                    <label key={tag} className="flex flex-col gap-1 text-[13px] font-medium">
                      <span className="flex items-baseline justify-between gap-2">
                        {t(`utm_${tag.slice(4)}` as 'utm_source')}
                        <span className="font-mono text-[11.5px] font-normal text-fg-3">{tag}</span>
                      </span>
                      <input
                        autoFocus={index === 0}
                        value={draft[tag] ?? ''}
                        onChange={(event) => setDraft({ ...draft, [tag]: event.target.value })}
                        className={controlClass}
                      />
                    </label>
                  ))}
                  <div className="flex justify-end gap-2 pt-1">
                    <button type="button" className={buttonClass('secondary', 'sm')} onClick={close}>
                      {t('cancel')}
                    </button>
                    <button type="submit" className={buttonClass('primary', 'sm')}>
                      {t('apply')}
                    </button>
                  </div>
                </form>
              )}
            </Dialog>
          </Popover>
        </DialogTrigger>
      </div>
    </FieldFrame>
  );
}
