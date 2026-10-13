<?php

namespace App\Support;

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
            ->whereIn('category', Project::eligibleCategories($member))
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
                'projects',
            ])
            ->orderBy('county')->orderBy('ward')->orderBy('name')
            ->get();
    }

    public static function resolve(User $user): ?Model
    {
        return $user->role === 'fundi'
            ? FundiRegistration::where('user_id', $user->id)->first()
            : CtpRegistration::where('user_id', $user->id)->first();
    }
}
