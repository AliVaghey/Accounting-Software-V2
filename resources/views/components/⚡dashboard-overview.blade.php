<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** @return array{today: string, date: string, month: string, start: Carbon, end: Carbon} */
    #[Computed]
    public function period(): array
    {
        $today = now('Asia/Tehran');
        $calendar = IntlCalendar::createInstance('Asia/Tehran', 'en_US@calendar=persian');
        $calendar->setTime($today->getTimestamp() * 1000);
        $start = $today->copy()->subDays($calendar->get(IntlCalendar::FIELD_DAY_OF_MONTH) - 1)->startOfDay();
        $end = $start->copy()->addDays($calendar->getActualMaximum(IntlCalendar::FIELD_DAY_OF_MONTH));
        $formatter = new IntlDateFormatter('en_US@calendar=persian', IntlDateFormatter::NONE,
            IntlDateFormatter::NONE, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, 'EEEE, d MMMM yyyy');
        $date = $formatter->format($today);
        $formatter->setPattern('MMMM yyyy');

        return [
            'today' => $today->toDateString(),
            'date' => $date,
            'month' => $formatter->format($today),
            'start' => $start->utc(),
            'end' => $end->utc(),
        ];
    }

    /** @return array{count: int, uses: int, tags: list<array{id: int, name: string, color: string, amount: int, count: int, percentage: float, share: float, offset: float}>} */
    #[Computed]
    public function statistics(): array
    {
        $user = $this->currentUser();
        $start = $this->period['start'];
        $end = $this->period['end'];
        $inMonth = fn (Builder $query): Builder => $query->where('transactions.user_id', $user->id)
            ->where('transactions.created_at', '>=', $start)->where('transactions.created_at', '<', $end);
        $count = $user->transactions()->where('created_at', '>=', $start)->where('created_at', '<', $end)->count();
        $tags = $user->tags()->whereHas('transactions', $inMonth)
            ->withCount(['transactions' => $inMonth])
            ->withSum(['transactions' => $inMonth], 'amount')
            ->orderByDesc('transactions_count')->orderBy('name')->orderBy('id')->get();
        $uses = (int) $tags->sum('transactions_count');
        $offset = 0.0;
        $breakdown = $tags->map(function (Tag $tag) use ($count, $uses, &$offset): array {
            $tagCount = (int) $tag->getAttribute('transactions_count');
            $share = $tagCount / $uses * 100;
            $entry = [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'amount' => (int) $tag->getAttribute('transactions_sum_amount'),
                'count' => $tagCount,
                'percentage' => $tagCount / $count * 100,
                'share' => $share,
                'offset' => -$offset,
            ];
            $offset += $share;

            return $entry;
        })->all();

        return ['count' => $count, 'uses' => $uses, 'tags' => $breakdown];
    }

    #[On('transaction-saved')]
    #[On('transaction-deleted')]
    public function refreshStatistics(): void
    {
        unset($this->period, $this->statistics);
    }

    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div class="flex flex-col gap-6">
    <header class="flex flex-col gap-3">
        <p class="text-sm text-galaxy-muted">Accounting Software</p>
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <time datetime="{{ $this->period['today'] }}" class="text-sm text-galaxy-muted tabular-nums">{{ $this->period['date'] }}</time>
    </header>

    <section class="dashboard-statistics flex min-w-0 flex-col gap-4 rounded-3xl p-5" aria-labelledby="monthly-statistics-heading">
        <header class="flex flex-col gap-1">
            <h2 id="monthly-statistics-heading" class="text-lg font-semibold">This month by tag</h2>
            <p class="text-sm text-galaxy-muted">{{ $this->period['month'] }} · {{ number_format($this->statistics['count']) }} {{ Str::plural('transaction', $this->statistics['count']) }}</p>
        </header>

        <figure class="flex flex-col items-center gap-3">
            <svg viewBox="0 0 200 200" class="size-32 max-w-full" role="img" aria-labelledby="monthly-chart-title" aria-describedby="monthly-chart-description">
                <title id="monthly-chart-title">{{ $this->statistics['uses'] > 0 ? 'Share of tag uses this Persian month' : 'No tag uses this Persian month' }}</title>
                <circle cx="100" cy="100" r="96" fill="var(--color-galaxy-deep)" />
                @foreach ($this->statistics['tags'] as $tag)
                    <circle wire:key="monthly-slice-{{ $tag['id'] }}" cx="100" cy="100" r="48" fill="none" stroke="{{ $tag['color'] }}" stroke-width="96"
                        pathLength="100" stroke-dasharray="{{ $tag['share'] }} 100" stroke-dashoffset="{{ $tag['offset'] }}" transform="rotate(-90 100 100)" />
                @endforeach
            </svg>
            <figcaption id="monthly-chart-description" class="text-center text-xs leading-5 text-galaxy-muted">Each slice shows a tag’s share of tag uses.</figcaption>
        </figure>

        @if ($this->statistics['tags'] !== [])
            <dl class="flex flex-col gap-4" aria-label="Monthly tag breakdown">
                @foreach ($this->statistics['tags'] as $tag)
                    <div wire:key="monthly-tag-{{ $tag['id'] }}" class="grid min-w-0 grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-start gap-3">
                        <dt class="flex min-w-0 items-start gap-2 text-sm">
                            <span class="mt-1 size-3 shrink-0 rounded-full border border-white/30" style="background-color: {{ $tag['color'] }}" aria-hidden="true"></span>
                            <span class="wrap-anywhere">{{ $tag['name'] }}</span>
                        </dt>
                        <dd class="flex min-w-0 flex-col gap-1 text-right tabular-nums">
                            <span class="text-sm font-semibold wrap-anywhere">{{ number_format($tag['amount']) }}</span>
                            <span class="text-xs text-galaxy-muted">{{ number_format($tag['percentage'], 1) }}% of transactions</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
            <details class="text-xs leading-5 text-galaxy-muted">
                <summary class="min-h-11 cursor-pointer py-3">How these totals are counted</summary>
                <p>A transaction can have several tags, so percentages may total more than 100%. Each tag includes the full amount of its transactions, both income and expenses. Untagged transactions are included in the monthly count.</p>
            </details>
        @else
            <p class="text-sm leading-6 text-galaxy-muted">{{ $this->statistics['count'] === 0 ? 'No transactions this Persian month. Add one to start your monthly overview.' : 'No tagged transactions this Persian month. Add tags to your transactions to see their breakdown.' }}</p>
            <button type="button" class="auth-link self-start" x-data x-on:click="$dispatch('open-transaction-modal')" aria-haspopup="dialog" aria-controls="add-transaction-modal">Add transaction</button>
        @endif
    </section>
</div>
