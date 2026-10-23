<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabelPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The labeling queue as a table: every page, its split and status, what
 * was changed on it, and its tags, which can be fixed here without opening
 * the page.
 */
class DatasetController extends Controller
{
    private const SORTABLE = ['labeled_at', 'priority', 'id', 'seconds'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->value(), self::SORTABLE, true) ? $request->string('sort')->value() : 'labeled_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 30, 50], true) ? (int) $request->input('per_page') : 50;

        $splits = array_values(array_intersect((array) $request->input('split', []), ['train', 'test']));
        $statuses = array_values(array_intersect((array) $request->input('status', []), LabelPage::STATUSES));
        $tags = array_values(array_intersect((array) $request->input('tag', []), [...LabelPage::TAGS, 'none']));
        $search = $request->integer('search') ?: null;

        $pages = LabelPage::query()
            ->with(['translation:id,result_marked', 'admin:id,name'])
            ->select(['id', 'translation_id', 'split', 'status', 'skip_reason', 'priority', 'issues', 'cells', 'edits', 'tags', 'admin_id', 'seconds', 'labeled_at'])
            ->when($search, fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('id', $search)->orWhere('translation_id', $search)))
            ->when($splits !== [], fn (Builder $q) => $q->whereIn('split', $splits))
            ->when($statuses !== [], fn (Builder $q) => $q->whereIn('status', $statuses))
            ->when($tags !== [], fn (Builder $q) => $q->where(function (Builder $w) use ($tags) {
                foreach ($tags as $tag) {
                    if ($tag === 'none') {
                        $w->orWhere(fn (Builder $n) => $n->whereNull('tags')->orWhereJsonLength('tags', 0));
                    } else {
                        $w->orWhereJsonContains('tags', $tag);
                    }
                }
            }))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $pages->through(fn (LabelPage $page) => [
            'id' => $page->id,
            'translation_id' => $page->translation_id,
            'image_url' => $page->translation?->markedImageUrl(),
            'split' => $page->split,
            'status' => $page->status,
            'skip_reason' => $page->skip_reason,
            'tags' => $page->tags ?? [],
            'cells' => $page->cells === null ? null : count($page->cellList()),
            'model_cells' => $page->issues['cells'] ?? null,
            'issues' => $page->issues,
            'edits' => $page->edits,
            'priority' => $page->priority,
            'labeled_by' => $page->admin?->name,
            'labeled_at' => $page->labeled_at,
            'seconds' => $page->seconds,
        ]);

        $count = fn (string $column) => LabelPage::query()->selectRaw("{$column} as v, COUNT(*) as c")->groupBy($column)->pluck('c', 'v')->map(fn ($c) => (int) $c);

        return Inertia::render('dataset', [
            'pages' => $pages,
            'splitCounts' => $count('split'),
            'statusCounts' => $count('status'),
            'tagCounts' => collect(LabelPage::TAGS)->mapWithKeys(fn ($tag) => [$tag => LabelPage::query()->whereJsonContains('tags', $tag)->count()]),
            'tagNames' => LabelPage::TAGS,
            'filters' => [
                'search' => $search,
                'split' => $splits,
                'status' => $statuses,
                'tag' => $tags,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** Sets or clears one tag on one or more pages. */
    public function tag(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'tag' => ['required', Rule::in(LabelPage::TAGS)],
            'on' => ['required', 'boolean'],
        ]);

        $pages = LabelPage::query()->whereIn('id', $data['ids'])->get(['id', 'tags']);
        foreach ($pages as $page) {
            $tags = array_values(array_diff($page->tags ?? [], [$data['tag']]));
            if ($data['on']) {
                $tags[] = $data['tag'];
            }
            // Keep the declared order, so the same tags always read the same.
            $page->update(['tags' => array_values(array_intersect(LabelPage::TAGS, $tags))]);
        }

        return response()->json(['data' => $pages->mapWithKeys(fn (LabelPage $p) => [$p->id => $p->tags])]);
    }
}
