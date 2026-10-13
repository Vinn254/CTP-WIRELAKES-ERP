<?php

namespace App\Support;

use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** The project board every member sees — shared by the member portal and the staff preview. */
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

    /** Everything the interface needs about one member, for both sides of the RBAC line. */
    public static function board(Model $member): array
    {
        return [
            'member' => $member,
            'projects' => static::projects($member),
            'applications' => static::applications($member),
        ];
    }

    public static function resolve(User $user): ?Model
    {
        return $user->role === 'fundi'
            ? FundiRegistration::where('user_id', $user->id)->first()
            : CtpRegistration::where('user_id', $user->id)->first();
    }
}
