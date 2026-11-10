<?php

declare(strict_types=1);

namespace Vanda\Support;

use Vanda\VandaException;

/**
 * A keyboard shortcut written as the renderer reads it: modifiers and one key joined by "+",
 * "mod" being ⌘ on macOS and Ctrl elsewhere:
 *
 *     Hotkey::normalize('Mod+Shift+S'); // 'mod+shift+s'
 *     Hotkey::normalize('cmd+s');       // VandaException: write "mod+s"
 *
 * The browser keeps some shortcuts for itself (a new window, closing the tab) and never lets a page
 * have them; they are refused here, with the reason.
 */
final class Hotkey
{
    private const MODIFIERS = ['mod', 'ctrl', 'alt', 'shift'];

    /** Written for another system or another library: what Vanda calls them. */
    private const RENAMED = ['cmd' => 'mod', 'command' => 'mod', 'meta' => 'mod', '⌘' => 'mod', 'super' => 'mod', 'win' => 'mod', 'control' => 'ctrl', 'option' => 'alt', 'opt' => 'alt', '⌥' => 'alt', '⇧' => 'shift'];

    private const KEYS = ['enter', 'escape', 'backspace', 'delete', 'tab', 'space', 'arrowup', 'arrowdown', 'arrowleft', 'arrowright', 'home', 'end', 'pageup', 'pagedown', 'insert'];

    private const ALIASES = ['esc' => 'escape', 'return' => 'enter', 'up' => 'arrowup', 'down' => 'arrowdown', 'left' => 'arrowleft', 'right' => 'arrowright', 'del' => 'delete'];

    private const SIGNS = ['/', '\\', ',', '.', ';', "'", '[', ']', '-', '=', '`', '?'];

    /** Shortcuts the browser or the system takes before the page sees them. */
    private const RESERVED = [
        'mod+n' => 'opens a new window',
        'mod+shift+n' => 'opens a private window',
        'mod+t' => 'opens a new tab',
        'mod+shift+t' => 'reopens a closed tab',
        'mod+w' => 'closes the tab',
        'mod+shift+w' => 'closes the window',
        'mod+q' => 'quits the browser on macOS',
        'mod+h' => 'hides the browser on macOS',
        'mod+m' => 'minimizes the window on macOS',
        'ctrl+tab' => 'switches tabs',
        'ctrl+shift+tab' => 'switches tabs',
        'mod+tab' => 'switches applications',
    ];

    /**
     * The shortcut in lower case with its modifiers in a fixed order ("mod+shift+s").
     *
     * @throws VandaException when it cannot work, saying what to write instead
     */
    public static function normalize(string $hotkey, string $where = 'hotkey()'): string
    {
        $written = trim($hotkey);
        $parts = array_map('trim', explode('+', strtolower(preg_replace('/\+\+$/', '+plus', $written) ?? $written)));
        $key = array_pop($parts) ?? '';
        $key = $key === 'plus' ? '+' : (self::ALIASES[$key] ?? $key);

        foreach ($parts as $modifier) {
            if (isset(self::RENAMED[$modifier])) {
                $fixed = implode('+', array_map(static fn (string $part) => self::RENAMED[$part] ?? $part, [...$parts, $key]));
                throw new VandaException("{$where}: \"{$written}\" names \"{$modifier}\", which Vanda calls \"".self::RENAMED[$modifier]."\". Write \"{$fixed}\" (\"mod\" is ⌘ on macOS and Ctrl elsewhere).");
            }
            if (! in_array($modifier, self::MODIFIERS, true)) {
                throw new VandaException("{$where}: \"{$written}\" has the modifier \"{$modifier}\". Use mod (⌘ on macOS, Ctrl elsewhere), ctrl, alt and shift, e.g. \"mod+shift+s\".");
            }
        }

        $valid = preg_match('/^[a-z0-9]$/', $key) === 1
            || preg_match('/^f([1-9]|1[0-2])$/', $key) === 1
            || in_array($key, self::KEYS, true)
            || in_array($key, self::SIGNS, true)
            || $key === '+';
        if (! $valid) {
            $hint = Suggest::closest($key, [...self::KEYS, ...array_keys(self::ALIASES)]);
            throw new VandaException("{$where}: \"{$written}\" ends with \"{$key}\", which is not a key. End it with one letter, a digit, f1–f12, a sign like \"/\" or one of: ".implode(', ', self::KEYS).'.'.($hint === null ? '' : " Did you mean \"{$hint}\"?"));
        }

        $modifiers = array_values(array_filter(self::MODIFIERS, static fn (string $modifier) => in_array($modifier, $parts, true)));
        $normal = implode('+', [...$modifiers, $key]);

        if (isset(self::RESERVED[$normal])) {
            $free = array_values(array_filter(["mod+shift+{$key}", "mod+alt+{$key}", "alt+{$key}"], static fn (string $other) => ! isset(self::RESERVED[$other])));
            throw new VandaException("{$where}: \"{$written}\" never reaches the page: the browser ".self::RESERVED[$normal].'. Choose another one, e.g. "'.implode('" or "', array_slice($free, 0, 2)).'".');
        }

        return $normal;
    }
}
