<?php

namespace App\Support;

use App\Models\AttachmentLog;
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
use Illuminate\Support\Collection;

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
            //  — an E-Fundi's sidebar carries the asks waiting on their answer; a CTP never has one.
            'inviteCount' => $member instanceof FundiRegistration
                ? ProjectMatch::for($member)->awaiting()->count()
                : 0,
            //  — the orders waiting on the worker's own signature. A CTP holds no paid order, so none is counted.
            'workOrderCount' => $member instanceof FundiRegistration ? Execution::awaitingWorker($member) : 0,
            //  — the inspection items the worker still owes a photograph of the fix for; only an E-Fundi works a site.
            'fixCount' => $member instanceof FundiRegistration ? Construction::fixesFor($member)->count() : 0,
            //  — the attachment weeks the trainee still owes the Registrar: kept as a draft or sent back for a fix.
            'attachmentCount' => $member instanceof CtpRegistration
                ? AttachmentLog::where('ctp_registration_id', $member->id)->whereIn('status', ['draft', 'returned'])->count()
                : 0,
            //  — the repair jobs on technology assets assigned to this fundi to fix.
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

    /**
     * The programmes whose library reading a trainee opens: the classes they hold an approved seat in, and the
     * programme the office has them on. An application to a course they were never placed on is not a class.
     *
     * @return Collection<int, \App\Models\Training>
     */
    public static function readings(CtpRegistration $ctp): Collection
    {
        return $ctp->enrollments()->where('state', 'approved')
            ->with('cohort.training:id,name')->get()
            ->pluck('cohort.training')->filter()
            ->concat($ctp->programme ? collect([$ctp->programme]) : collect())
            ->unique('id')->values();
    }

    /** Trainings a CTP can put themselves forward for — posted, still in date and with room. */
    public static function openCohorts()
    {
        return Cohort::openForEnrol()
            ->with(['training:id,name,code,summary', 'venue:id,name,type,county,sub_county,ward',
                'trainer.fundi:id,full_name', 'trainer.staff:id,name', 'trainer.applicant:id,name', 'community:id,name',
                'counties:id,name', 'enrollments'])
            ->orderBy('starts_on')->orderBy('id')->get();
    }

    /** An intake reaches a CTP when it is open in all counties, or lists the county the CTP registered in. */
    public static function isInCtpArea(Cohort $cohort, CtpRegistration $ctp): bool
    {
        return $cohort->coversCounty($ctp->county);
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
                ->with(['entries', 'certification', 'moduleMarks', 'documents', 'cohort.units', 'cohort.venue', 'cohort.trainer.fundi', 'cohort.trainer.staff', 'cohort.trainer.applicant', 'cohort.community'])
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
