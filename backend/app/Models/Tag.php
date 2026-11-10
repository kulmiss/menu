<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A system tag (key set, translated by the apps) or the restaurant's own
 * tag (key null, name per language).
 *
 * @property int $id
 * @property string|null $key
 * @property array<string, string> $name
 * @property int $sort
 */
#[Fillable(['key', 'name', 'sort'])]
class Tag extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'sort' => 'integer',
        ];
    }

    public function isSystem(): bool
    {
        return $this->key !== null;
    }
}
