<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Community;
use App\Models\FundiRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
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
        return [
            'member' => $member,
            'projects' => static::projects($member),
            'applications' => static::applications($member),
            'communities' => $member instanceof CtpRegistration ? static::communities($member) : collect(),
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
            ->with(['venue:id,name,type', 'trainer.fundi:id,full_name', 'community:id,name', 'enrollments'])
            ->orderBy('starts_on')->orderBy('id')->get();
    }

    /** Everything the My training screen needs about one CTP — open intakes, seats held and the record. */
    public static function training(CtpRegistration $ctp): array
    {
        return [
            'open' => static::openCohorts(),
            'appliedTo' => $ctp->enrollments()->whereIn('state', ['pending', 'approved'])->pluck('cohort_id'),
            'mine' => $ctp->activeEnrollments()
                ->with(['entries', 'certification', 'cohort.venue', 'cohort.trainer.fundi', 'cohort.community'])
                ->get(),
            'history' => $ctp->enrollments()
                ->with(['cohort:id,reference,course,family', 'certification'])
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
