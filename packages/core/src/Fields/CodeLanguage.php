<?php

declare(strict_types=1);

namespace Vanda\Fields;

enum CodeLanguage: string
{
    case Plain = 'plain';
    case Json = 'json';
    case Html = 'html';
    case Css = 'css';
    case JavaScript = 'javascript';
    case Php = 'php';
    case Sql = 'sql';
    case Markdown = 'markdown';
    case Yaml = 'yaml';
}
