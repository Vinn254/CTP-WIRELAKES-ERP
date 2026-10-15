<?php

namespace App\Support;

use App\Models\FundiRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\ProjectMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Module 6 — the matching desk (§9.4). The search reads the E-Fundi register the way §8 keeps it: grade against the
 * project category, the skills the typed trade actually names, the counties the fundi says they cover, whether they
 * say they are free, and what they have already done. Nothing here invents a rating — the programme has no review
 * scores, so the standing bar is built from the register's own facts and every factor is written out on the row.
 */
class Marketplace
{
    /** §9.4 step 2 — the factors the search scores on and the most each can add. */
    public const FACTORS = ['skills' => 40, 'county' => 25, 'availability' => 15, 'experience' => 10, 'jobs' => 10];

    /** The standing bar a fundi must clear to be shortlisted, as a share of the points available on the project. */
    public const BAR = 55;

    /** §9.4 step 3 — how many of the top of the shortlist are asked. */
    public const INVITE_TOP = 5;

    /** A lowered bar (§9.6) is not free — the desk only lets an executive take it that far. */
    public const MIN_BAR = 30;

    /** The skills the project asks for, read off the trade and the title against the programme's own skill list. */
    public static function needs(Project $project): array
    {
        $text = static::lower($project->trade.' '.$project->title);

        return collect(FundiRegistration::SPECIALISATIONS)
            ->filter(fn (string $skill) => str_contains($text, static::lower($skill))
                || str_contains($text, static::lower(str_replace('-', ' ', $skill))))
            ->values()->all();
    }

    /**
     * Every fundi the pool offers for this project, scored and sorted, with the reason each one was passed over kept
     * so the desk can say why a search came up empty rather than showing a blank table.
     *
     * @param  array{widen?: bool, bar?: int}  $opts
     * @return Collection<int, array{fundi: FundiRegistration, score: float, matched: array, passed: bool, reason: ?string}>
     */
    public static function search(Project $project, array $opts = []): Collection
    {
        $widen = (bool) ($opts['widen'] ?? false);
        $bar = max(self::MIN_BAR, (int) ($opts['bar'] ?? self::BAR));
        $needs = static::needs($project);
        $nearby = $widen ? KenyaMap::neighbours($project->county) : [];

        $rows = FundiRegistration::where('status', 'active')->orderBy('full_name')->get()
            ->map(function (FundiRegistration $fundi) use ($project, $needs, $nearby) {
                return static::score($project, $fundi, $needs, $nearby);
            })
            ->sortByDesc('score')->values();

        return $rows->map(fn (array $r) => $r + ['passed' => $r['reason'] === null && $r['score'] >= $bar]);
    }

    /** One fundi against one project: the points, the facts behind them, and the reason they cannot be matched. */
    public static function score(Project $project, FundiRegistration $fundi, array $needs, array $nearby = []): array
    {
        $matched = [];
        $points = 0.0;
        $possible = array_sum(self::FACTORS);

        // The three tests §9.4 runs before it scores anything at all.
        $reason = match (true) {
            ! Project::fits(['tier' => $fundi->tier, 'ctp' => false], $project->category) =>
                'Their grade does not cover a '.($project->categoryLabel()).'.',
            ! $fundi->isMatchable() =>
                'They say they are '.strtolower($fundi->availabilityLabel()).'.',
            default => null,
        };

        if ($needs) {
            $have = collect($needs)->intersect((array) $fundi->specialisations)->values()->all();
            $matched['skills'] = $have ? implode(', ', $have) : 'None of '.implode(' / ', $needs);
            $points += count($have) ? self::FACTORS['skills'] : 0;
        } else {
            $possible -= self::FACTORS['skills'];
            $matched['skills'] = 'The trade names no skill on the register — matched on place and standing only';
        }

        $serves = collect((array) $fundi->service_counties)->push($fundi->county)->filter()
            ->map(fn ($c) => trim((string) $c));
        $here = $project->county ? $serves->first(fn ($c) => strcasecmp($c, trim($project->county)) === 0) : null;

        if ($here) {
            $points += self::FACTORS['county'];
            $matched['county'] = 'Works in '.$here;
        } elseif ($nearby && $project->county) {
            $close = $serves->intersect($nearby)->values()->all();
            if ($close) {
                $points += round(self::FACTORS['county'] * 0.5, 1);
                $matched['county'] = 'Neighbour of '.$project->county.': '.implode(', ', $close);
            } else {
                $matched['county'] = 'Does not cover '.$project->county.' or its neighbours';
            }
        } else {
            $matched['county'] = $project->county ? 'Does not cover '.$project->county : 'The project has no county';
        }

        $points += self::FACTORS['availability'];
        $matched['availability'] = $fundi->availabilityLabel();

        $years = min(10, max(0, (int) $fundi->years_in_business));
        $points += $years / 10 * self::FACTORS['experience'];
        $matched['experience'] = $years.' '.str('year')->plural($years).' in trade';

        $jobs = min(10, ProjectApplication::where('applicant_type', 'fundi')
            ->where('applicant_id', $fundi->id)->where('status', 'approved')->count());
        $points += $jobs / 10 * self::FACTORS['jobs'];
        $matched['jobs'] = $jobs ? $jobs.' '.str('project')->plural($jobs).' done' : 'No project awarded yet';

        return [
            'fundi' => $fundi,
            'score' => round($points / max(1, $possible) * 100, 1),
            'matched' => $matched,
            'reason' => $reason,
        ];
    }

    /**
     * Run the search and keep the result as a new round (§9.4 step 2, and §9.6 — a second run is round 2, never an
     * overwrite of round 1). Returns the round number and how many fundis were kept.
     *
     * @param  array{widen?: bool, bar?: int}  $opts
     */
    public static function run(Project $project, array $opts = []): array
    {
        $round = ((int) ProjectMatch::where('project_id', $project->id)->max('round')) + 1;
        $bar = max(self::MIN_BAR, (int) ($opts['bar'] ?? self::BAR));
        $widen = (bool) ($opts['widen'] ?? false);

        $kept = 0;

        foreach (static::search($project, $opts) as $row) {
            if ($row['reason'] !== null || $row['score'] < $bar) {
                continue;
            }

            ProjectMatch::updateOrCreate(
                ['project_id' => $project->id, 'fundi_id' => $row['fundi']->id, 'round' => $round],
                [
                    'score' => $row['score'],
                    'matched' => $row['matched'],
                    'status' => 'shortlisted',
                    'widened' => $widen,
                    'below_bar' => $bar < self::BAR,
                ]
            );
            $kept++;
        }

        return ['round' => $round, 'kept' => $kept];
    }

    /** §9.4 step 3 — ask the top of the round. The 48 hours start when the invite is put out, not when it is read. */
    public static function invite(Project $project, int $round, int $top = self::INVITE_TOP): int
    {
        $ids = ProjectMatch::where('project_id', $project->id)->where('round', $round)
            ->where('status', 'shortlisted')->orderByDesc('score')->limit($top)->pluck('id');

        $n = $ids->count();

        ProjectMatch::whereIn('id', $ids)->update(['status' => 'invited', 'invited_on' => now()]);

        return $n;
    }

    /** A fundi answering inside the window (§9.4 step 4). Anything else is refused with the reason a person can read. */
    public static function respond(ProjectMatch $match, string $answer, ?string $note): ProjectMatch
    {
        abort_unless($match->status === 'invited', 422, match ($match->status) {
            'shortlisted' => 'The office has this project against your name, but has not put it to you yet — answer it when the invite arrives.',
            'no_reply' => 'The 48 hours on this invite ran out, so it is closed. Ask the office to bring you forward again.',
            'awarded' => 'This project is yours — the office awarded it, so the answer is already recorded.',
            default => 'You have already answered this one, and the office can see your answer.',
        });
        static::closeExpired($match->project_id);
        abort_if($match->fresh()->isOverdue(), 422, 'The 48 hours ran out on this invite, so it is closed. Ask the office to put you forward again.');

        $match->update([
            'status' => $answer === 'accept' ? 'accepted' : 'declined',
            'responded_on' => now(),
            'note' => $note ? str($note)->limit(190)->toString() : ($answer === 'accept' ? 'Took the project.' : 'Declined.'),
        ]);

        return $match;
    }

    /**
     * An invite whose 48 hours have passed is closed off the moment the desk or the fundi next opens it. It stays on
     * the round as a no-reply — the record that the search went out and nobody answered is what §9.6 acts on.
     */
    public static function closeExpired(int $projectId): int
    {
        return static::expire(ProjectMatch::where('project_id', $projectId));
    }

    /** The same clock, read from the fundi's own list. */
    public static function closeExpiredFor(FundiRegistration $fundi): int
    {
        return static::expire(ProjectMatch::where('fundi_id', $fundi->id));
    }

    private static function expire(Builder $q): int
    {
        $ids = $q->where('status', 'invited')->whereNull('responded_on')
            ->where('invited_on', '<', now()->subHours(ProjectMatch::WINDOW_HOURS))->pluck('id');

        ProjectMatch::whereIn('id', $ids)->update([
            'status' => 'no_reply',
            'note' => 'No answer within the 48 hours.',
        ]);

        return $ids->count();
    }

    /**
     * §9.4 step 6 — the project goes to a fundi who took it, or to a hand-pick. The project's own award rule is the
     * one place an award happens (Project::awardTo), so a marketplace award and an award from the project page cannot
     * disagree. Being handed a job also takes the fundi off the available list (§8.3).
     */
    public static function award(ProjectMatch $match): ProjectApplication
    {
        $project = $match->project;
        $fundi = $match->fundi;

        abort_unless(in_array($match->status, ['accepted', 'invited'], true), 422,
            'Only a fundi who took the project, or one the office is still asking, can be awarded it.');
        abort_if($fundi->status !== 'active', 422, $fundi->full_name.' is '.$fundi->statusLabel().' on the E-Fundi register, so the job cannot go to them.');

        $application = ProjectApplication::firstOrNew([
            'project_id' => $project->id,
            'applicant_type' => 'fundi',
            'applicant_id' => $fundi->id,
        ], [
            'full_name' => $fundi->full_name,
            'phone' => $fundi->phone,
            'email' => $fundi->email,
            'note' => 'Matched on the marketplace, round '.$match->round.' — '.$match->score.'%.',
            'status' => 'pending_review',
        ]);
        $application->save();

        $project->awardTo($application);

        $match->update(['status' => 'awarded', 'awarded_by_user' => auth()->id(), 'awarded_on' => now()]);
        ProjectMatch::where('project_id', $project->id)->where('id', '!=', $match->id)
            ->whereIn('status', ['accepted', 'invited', 'shortlisted'])
            ->update(['status' => 'declined', 'note' => 'The project went to another fundi.']);

        $fundi->update([
            'availability' => 'on_job',
            'availability_note' => 'Awarded '.$project->reference.' on '.now()->format('d M Y').'.',
        ]);

        return $application;
    }

    /** The desk's own waiting counts — what a coordinator opens the page to find. */
    public static function queues(): array
    {
        $open = Project::where('status', 'open')->pluck('id');

        return [
            'unmatched' => Project::where('status', 'open')->whereDoesntHave('matches')->count(),
            'waiting' => ProjectMatch::whereIn('project_id', $open)->where('status', 'invited')->count(),
            'accepted' => ProjectMatch::whereIn('project_id', $open)->where('status', 'accepted')->count(),
            'quiet' => ProjectMatch::whereIn('project_id', $open)->where('status', 'no_reply')->count(),
        ];
    }

    /** How the search is described back to the office, so a relaxed rule is never invisible. */
    public static function ruleLabel(array $opts): string
    {
        $bits = [];

        if (! empty($opts['widen'])) {
            $bits[] = 'widened to neighbouring counties';
        }

        $bar = (int) ($opts['bar'] ?? self::BAR);

        if ($bar < self::BAR) {
            $bits[] = 'standing bar lowered to '.$bar.'%';
        }

        return $bits ? implode(' and ', $bits) : 'the standard search';
    }

    private static function lower(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
