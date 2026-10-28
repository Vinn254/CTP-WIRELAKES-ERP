<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Community;
use App\Models\FundiRegistration;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\ProjectMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** The project board a member sees — resolved from the signed-in account, never from a url. */
class MemberPortal
{
    public static function applicantType(Model $member): string
    {
        return $member instanceof FundiRegistration ? 'fundi' : 'ctp';
    }

    public static function projects(Model $member)
    {
        return Project::with('hirer')
            ->where('status', 'open')
            // A CTP trainee sees every posted project; only the ones they may apply for say so.
            ->when(! ($member instanceof CtpRegistration),
                fn ($q) => $q->whereIn('category', Project::eligibleCategories($member)))
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', today()))
            ->latest()
            ->get();
    }

    public static function applications(Model $member)
    {
        return ProjectApplication::with('project.hirer')
            ->where('applicant_type', static::applicantType($member))
            ->where('applicant_id', $member->id)
            ->latest()
            ->get();
    }

    /** Everything a member interface needs about one member. */
    public static function board(Model $member): array
    {
        // What the office has billed this member. The balance screen is only offered with a real invoice behind it.
        $invoices = Invoice::forMember($member)->with('payments')->latest('id')->get();

        return [
            'member' => $member,
            'projects' => static::projects($member),
            'applications' => static::applications($member),
            'communities' => $member instanceof CtpRegistration ? static::communities($member) : collect(),
            'invoices' => $invoices,
            'invoiceCount' => $invoices->where('status', 'open')->count(),
            // §9.4 — an E-Fundi's sidebar carries the asks waiting on their answer; a CTP never has one.
            'inviteCount' => $member instanceof FundiRegistration
                ? ProjectMatch::for($member)->awaiting()->count()
                : 0,
            // §10.4 step 1 — the orders waiting on the worker's own signature, whichever member they are.
            'workOrderCount' => Execution::awaitingWorker($member),
            // §11.4 step 5 — the inspection items the worker still owes a photograph of the fix for.
            'fixCount' => Construction::fixesFor($member)->count(),
            // §12.7 — the repair jobs on technology assets assigned to this fundi to fix.
            'techJobCount' => Technology::jobCount($member),
        ];
    }

    /** A CTP's own communities with the field work they and their colleagues have logged. */
    public static function communities(CtpRegistration $ctp)
    {
        return Community::forCtp($ctp)
            ->with([
                'needs' => fn ($q) => $q->with('project:id,title,category,status')->latest('id'),
                'ctps:id,ctp_code,full_name',
                'beneficiaries.project:id,title,category,status',
            ])
            ->orderBy('county')->orderBy('ward')->orderBy('name')
            ->get();
    }

    /** Trainings a CTP can put themselves forward for — posted, still in date and with room (§7.5 step 1). */
    public static function openCohorts()
    {
        return Cohort::openForEnrol()
            ->with(['training:id,name,code,summary', 'venue:id,name,type,county,sub_county,ward',
                'trainer.fundi:id,full_name', 'trainer.staff:id,name', 'trainer.applicant:id,name', 'community:id,name', 'enrollments'])
            ->orderBy('starts_on')->orderBy('id')->get();
    }

    /** An intake runs in the CTP's own area when the county matches (the training mobilises ward by ward). */
    public static function isInCtpArea(Cohort $cohort, CtpRegistration $ctp): bool
    {
        return $ctp->county && $cohort->county && strcasecmp(trim($cohort->county), trim($ctp->county)) === 0;
    }

    /** Everything the My training screen needs about one CTP — open intakes, seats held and the record. */
    public static function training(CtpRegistration $ctp): array
    {
        $open = static::openCohorts();
        $nearby = $open->filter(fn (Cohort $c) => static::isInCtpArea($c, $ctp))->values();

        return [
            'open' => $open,
            'nearby' => $nearby,
            'elsewhere' => $open->reject(fn (Cohort $c) => static::isInCtpArea($c, $ctp))->values(),
            'appliedTo' => $ctp->enrollments()->whereIn('state', ['pending', 'approved'])->pluck('cohort_id'),
            'mine' => $ctp->activeEnrollments()
                ->with(['entries', 'certification', 'moduleMarks', 'documents', 'cohort.training.modules', 'cohort.venue', 'cohort.trainer.fundi', 'cohort.trainer.staff', 'cohort.trainer.applicant', 'cohort.community'])
                ->get(),
            'history' => $ctp->enrollments()
                ->with(['cohort:id,reference,training_id,venue_id,trainer_id,academic_year,term,starts_on,ends_on',
                    'cohort.training:id,name,code', 'certification'])
                ->whereNotIn('state', ['pending', 'approved'])->latest('id')->get(),
        ];
    }

    public static function resolve(User $user): ?Model
    {
        return $user->role === 'fundi'
            ? FundiRegistration::where('user_id', $user->id)->first()
            : CtpRegistration::where('user_id', $user->id)->first();
    }
}
