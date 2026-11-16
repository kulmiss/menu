<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** The Super Admin's version of a help article (QUESTIONS #194); the defaults live in resources/help. */
#[Fillable(['audience', 'slug', 'topic', 'title', 'summary', 'body', 'features', 'roles', 'pages', 'app_pages', 'keywords', 'sort', 'is_hidden', 'updated_by'])]
class HelpArticle extends Model
{
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'roles' => 'array',
            'pages' => 'array',
            'app_pages' => 'array',
            'keywords' => 'array',
            'sort' => 'integer',
            'is_hidden' => 'boolean',
        ];
    }
}
