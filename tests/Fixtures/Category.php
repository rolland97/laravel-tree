<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Concerns\IsTreeNode;
use Rolland\Tree\Contracts\TreeNode;

/**
 * Mirrors the shape the source application uses: the package defaults, unchanged.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int $position
 * @property string $name
 * @property bool $is_active
 */
final class Category extends Model implements TreeNode
{
    use IsTreeNode;

    protected $table = 'categories';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Mirrors the column defaults in the fixture migration. Without these, a model
     * created without naming them holds `null` in memory until it is re-read, and
     * `isValidTreeTarget()` would return null rather than a bool.
     */
    protected $attributes = [
        'position' => 0,
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * The host's own rule. The package has no opinion about what makes a target
     * valid — the source application answers "is it active?", another host might
     * answer "is it not archived?" or always true.
     */
    public function isValidTreeTarget(): bool
    {
        return $this->is_active;
    }
}
