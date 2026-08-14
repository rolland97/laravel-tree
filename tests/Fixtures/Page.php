<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Concerns\IsTreeNode;
use Rolland\Tree\Contracts\TreeNode;

/**
 * ⚠️ Deliberately unrelated to Category, and deliberately awkward.
 *
 * Research R9: one consumer proves nothing. Every configurable seam — column
 * names, tie-breaker, validity predicate — can be accidentally hard-coded to the
 * shape of a single fixture and still pass. This model is what makes "generic" a
 * measured claim rather than an assertion.
 *
 * Three things differ from Category ON PURPOSE:
 *
 *   1. the parent column is `section_id`, not `parent_id`;
 *   2. the position column is `sort_order`, not `position`;
 *   3. there is NO `name` column at all — the tie-breaker is `title`, so any code
 *      that hard-codes the default tie-breaker fails loudly here instead of
 *      quietly ordering by something else.
 *
 * Configuration is global to the package (data-model.md § Configuration), so tests
 * exercising this model set `config('tree.*')` to these names for the duration.
 * That is not a per-model override — it is the global seam being moved, which is
 * exactly what needs proving.
 *
 * @property int $id
 * @property int|null $section_id
 * @property int $sort_order
 * @property string $title
 * @property bool $published
 */
final class Page extends Model implements TreeNode
{
    use IsTreeNode;

    public const PARENT_COLUMN = 'section_id';

    public const POSITION_COLUMN = 'sort_order';

    public const TIEBREAKER = 'title';

    protected $table = 'pages';

    public $timestamps = false;

    protected $guarded = [];

    /** Mirrors the column defaults in the fixture migration — see Category. */
    protected $attributes = [
        'sort_order' => 0,
        'published' => true,
    ];

    protected $casts = [
        'published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function isValidTreeTarget(): bool
    {
        return $this->published;
    }
}
