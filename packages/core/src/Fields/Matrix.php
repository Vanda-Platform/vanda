<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * A table you fill in row by row (Orchid's Matrix): a Repeater drawn as a table,
 * one column per field.
 *
 *     Matrix::make('form.prices', ['Volume' => 'volume', 'Price' => 'price'])
 *         ->addLabel('Add a price'),
 *
 *     // other fields than text inputs
 *     Matrix::make('form.prices', ['Volume' => 'volume', 'Price' => 'price'])
 *         ->fields([Input::make('volume'), Input::make('price')->inputType(InputType::Number)->suffix('₽')]),
 *
 * The value is a list of rows: [['volume' => '250 g', 'price' => '890'], ...].
 */
final class Matrix
{
    /**
     * @param array<string, string> $columns header => key in the row
     */
    public static function make(string $path, array $columns): Repeater
    {
        $fields = [];
        foreach ($columns as $header => $key) {
            $fields[] = Input::make($key)->label(is_string($header) ? $header : $key);
        }

        return Repeater::make($path)->fields($fields)->table();
    }
}
