const messages = {
  en: {
    cancel: 'Cancel',
    confirm: 'Confirm',
    checkFields: 'Check the highlighted fields',
    requestFailed: 'Something went wrong. Try again.',
    empty: 'Nothing here yet',
    shown: 'Showing {from}–{to} of {total}',
    previous: 'Previous page',
    next: 'Next page',
    search: 'Search and commands',
    choose: 'Choose…',
    theme: 'Theme',
    light: 'Light theme',
    dark: 'Dark theme',
    system: 'System theme',
    inspector: 'Vanda inspector',
    copyForAi: 'Copy for AI',
    copied: 'Copied',
    close: 'Close',
    menu: 'Main menu',
    breadcrumbs: 'Breadcrumbs',
  },
  ru: {
    cancel: 'Отмена',
    confirm: 'Подтвердить',
    checkFields: 'Проверьте поля, отмеченные красным',
    requestFailed: 'Что-то пошло не так. Попробуйте ещё раз.',
    empty: 'Здесь пока пусто',
    shown: 'Показано {from}–{to} из {total}',
    previous: 'Предыдущая страница',
    next: 'Следующая страница',
    search: 'Поиск и команды',
    choose: 'Выберите…',
    theme: 'Тема',
    light: 'Светлая тема',
    dark: 'Тёмная тема',
    system: 'Как в системе',
    inspector: 'Инспектор Vanda',
    copyForAi: 'Скопировать для AI',
    copied: 'Скопировано',
    close: 'Закрыть',
    menu: 'Основное меню',
    breadcrumbs: 'Хлебные крошки',
  },
} as const;

type Key = keyof (typeof messages)['en'];

export function t(key: Key, params: Record<string, string | number> = {}): string {
  const lang = document.documentElement.lang.startsWith('ru') ? 'ru' : 'en';
  return Object.entries(params).reduce<string>((text, [name, value]) => text.replace(`{${name}}`, String(value)), messages[lang][key]);
}
