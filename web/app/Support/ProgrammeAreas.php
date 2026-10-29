<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The six areas of work, each with its own public page: what the programme does there, shown in the office's own
 * pictures, and the one form a person fills in to ask for that work. The words here are the programme's own — a
 * category is a service the programme actually delivers, never something invented for the page. Someone who wants
 * to join the programme registers, and someone who wants a job applies on Careers; neither is asked to fill this
 * form in.
 */
class ProgrammeAreas
{
    public const AREAS = [
        'training' => [
            'name' => 'Training & Certification',
            'nav' => 'Training',
            'icon' => 'award',
            'department' => 'training',
            'lede' => 'Courses and cohorts at community sites and partner schools.',
            'services' => [
                'Training for my school or community',
                'Certification for our staff',
                'A trainer for our group',
            ],
            'quick' => [
                ['label' => 'Apply to become a student', 'url' => '/register/ctp', 'class' => 'btn-primary'],
                ['label' => 'Apply to become a Fundi', 'url' => '/register/fundi', 'class' => 'btn-lime'],
            ],
        ],
        'marketplace' => [
            'name' => 'Marketplace',
            'nav' => 'Marketplace',
            'icon' => 'briefcase',
            'department' => 'marketplace',
            'lede' => 'The services the programme delivers, and the verified fundis behind them.',
            'services' => [
                'Electrical installation',
                'Solar installation',
                'Training',
                'A fundi for a job',
                'A trainer',
                'Web design',
                'Software design',
                'Cyber services — printing, CVs, online applications',
            ],
            'quick' => [
                ['label' => 'Register as a Fundi', 'url' => '/register/fundi', 'class' => 'btn-primary'],
            ],
        ],
        'projects' => [
            'name' => 'Projects & Construction',
            'nav' => 'Projects',
            'icon' => 'building',
            'department' => 'execution',
            'lede' => 'Projects posted for application, and the work already signed off.',
            'services' => [
                'Post a project for the programme to run',
                'Construction — roofs, fences, classrooms',
                'ICT lab or equipment',
                'Maintenance for our building',
            ],
            'quick' => [
                ['label' => 'See the open projects', 'url' => '/projects', 'class' => 'btn-primary'],
            ],
        ],
        'e-waste' => [
            'name' => 'E-Waste Management',
            'nav' => 'E-Waste',
            'icon' => 'recycle',
            'department' => 'ewaste',
            'lede' => 'Collection, repair, refurbishment and safe disposal.',
            'services' => [
                'Collect e-waste from my premises',
                'Repair or refurbish our equipment',
                'Safe disposal of our e-waste',
                'Refurbished gear for a school',
            ],
            'quick' => [
                ['label' => 'Register as a Fundi to collect and repair', 'url' => '/register/fundi', 'class' => 'btn-primary'],
            ],
        ],
        'entrepreneurship' => [
            'name' => 'Entrepreneurship',
            'nav' => 'Entrepreneurship',
            'icon' => 'cap',
            'department' => 'communities',
            'lede' => 'What the programme offers a fundi or CTP turning a skill into a business.',
            'services' => [
                'Business support for my group',
                'A toolkit for a new enterprise',
                'First contracts for our members',
            ],
            'quick' => [
                ['label' => 'Register as a student (CTP)', 'url' => '/register/ctp', 'class' => 'btn-primary'],
                ['label' => 'Fund a toolkit', 'url' => '/donate', 'class' => 'btn-lime'],
            ],
        ],
        'green-economy' => [
            'name' => 'Green Economy & Technology',
            'nav' => 'Green Economy',
            'icon' => 'leaf',
            'department' => 'technology',
            'lede' => 'Solar, green tech and community technology, installed by our own workforce.',
            'services' => [
                'Solar installation for my home',
                'Solar for a school or clinic',
                'ICT, networking or robotics for our institution',
            ],
            'quick' => [
                ['label' => 'Register as a Fundi for green work', 'url' => '/register/fundi', 'class' => 'btn-primary'],
                ['label' => 'Partner with us', 'url' => '/partners', 'class' => 'btn-outline'],
            ],
        ],
    ];

    public static function all(): array
    {
        return self::AREAS;
    }

    public static function find(string $slug): ?array
    {
        return self::AREAS[$slug] ?? null;
    }

    /** The route name a visitor is sent to — the same slug as the CMS page key. */
    public static function route(string $slug): string
    {
        return $slug;
    }

    public static function name(string $slug): string
    {
        return self::AREAS[$slug]['name'] ?? Str::headline($slug);
    }

    public static function nav(string $slug): string
    {
        return self::AREAS[$slug]['nav'] ?? Str::headline($slug);
    }

    public static function icon(string $slug): string
    {
        return self::AREAS[$slug]['icon'] ?? 'grid';
    }

    /** The office desk that has to answer this area's requests. */
    public static function department(string $slug): ?string
    {
        return self::AREAS[$slug]['department'] ?? null;
    }

    /**
     * Which area page a CMS card title belongs to, so the work-area cards on the home page and on “What we do” can
     * open their area page without the office having to keep a link in step with a title. The distinctive words are
     * checked first: “Green Economy & Technology” is the green page, not the technology one.
     */
    public static function slugForTitle(?string $title): ?string
    {
        $t = Str::lower(trim((string) $title));
        if ($t === '') {
            return null;
        }

        $keys = [
            'e-waste' => ['waste', 'recycl'],
            'green-economy' => ['green', 'solar'],
            'entrepreneurship' => ['entrepreneur', 'business', 'enterprise'],
            'marketplace' => ['marketplace', 'fundi'],
            'projects' => ['project', 'construction'],
            'training' => ['training', 'certif', 'train'],
        ];

        foreach ($keys as $slug => $words) {
            foreach ($words as $word) {
                if (Str::contains($t, $word)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /** The services this area can be asked for, as value => label pairs for the form's select. */
    public static function services(string $slug): array
    {
        return array_combine(
            self::AREAS[$slug]['services'] ?? [],
            self::AREAS[$slug]['services'] ?? []
        ) ?: [];
    }

    /**
     * Every service the programme offers, under the name of the area that delivers it. A person who wants solar
     * wiring should find it whether they arrived on the Marketplace page or the Green Economy one, so the request
     * form lists the whole catalogue — with the page they are on shown first.
     */
    public static function serviceGroups(?string $first = null): array
    {
        $order = array_keys(self::AREAS);
        if ($first !== null && ($at = array_search($first, $order, true)) !== false) {
            array_splice($order, $at, 1);
            array_unshift($order, $first);
        }

        $groups = [];
        foreach ($order as $slug) {
            $services = self::AREAS[$slug]['services'] ?? [];
            if ($services) {
                $groups[self::name($slug)] = $services;
            }
        }

        return $groups;
    }

    /** Flat list of every service, which is what a request form is allowed to record. */
    public static function allServices(): array
    {
        return array_merge(...array_values(self::serviceGroups()));
    }

    /**
     * Where a person goes instead of asking for a service: join the programme, or work with it. The area's own
     * shortcuts come first, then the four routes every area offers, so nobody has to hunt for a register page.
     */
    public static function joinLinks(string $slug): array
    {
        $links = array_merge(self::AREAS[$slug]['quick'] ?? [], [
            ['label' => 'Become a student — register as a CTP', 'url' => '/register/ctp', 'class' => 'btn-primary'],
            ['label' => 'Register as a Fundi (E-Fundi)', 'url' => '/register/fundi', 'class' => 'btn-lime'],
            ['label' => 'Apply for a job — Careers', 'url' => '/careers', 'class' => 'btn-outline'],
            ['label' => 'Already registered? Log in', 'url' => '/login', 'class' => 'btn-ghost'],
        ]);

        $seen = [];

        return collect($links)
            ->filter(function (array $link) use ($slug, &$seen) {
                $key = parse_url($link['url'], PHP_URL_PATH);
                if (isset($seen[$key]) || $key === '/'.self::route($slug)) {
                    return false;
                }

                return $seen[$key] = true;
            })
            ->values()
            ->all();
    }

    /**
     * Everything an area page renders: the office's own heading, the work it explains with a picture beside each
     * explanation, the extra gallery and testimonials, and the choices the request form offers.
     */
    public static function page(string $slug): array
    {
        $area = self::AREAS[$slug] ?? null;
        if (! $area) {
            return [];
        }

        return [
            'area' => $slug,
            'meta' => $area,
            'tag' => content($slug, 'intro', 'tag', $area['nav']),
            'heading' => content($slug, 'intro', 'title', $area['name']),
            'lede' => content($slug, 'intro', 'sub', $area['lede']),
            'work_title' => content($slug, 'intro', 'work_title', 'What we do here'),
            'work_note' => content($slug, 'intro', 'work_note',
                'Each line below is work the area actually carries out, in the office’s own words with a picture of it.'),
            'offers' => content_rows($slug, 'offers'),
            'gallery' => content_rows($slug, 'gallery'),
            'voices' => content_rows($slug, 'testimonials'),
            'services' => self::services($slug),
            'service_groups' => self::serviceGroups($slug),
            'join' => self::joinLinks($slug),
            'apply_title' => content($slug, 'intro', 'apply_title', 'Ask us to do this for you'),
            'apply_note' => content($slug, 'intro', 'apply_note',
                'Tell us what you need done and where you are. The office reads it and contacts you on the number or email you give.'),
            'join_title' => content($slug, 'intro', 'join_title', 'Want to join us or work with us instead?'),
            'join_note' => content($slug, 'intro', 'join_note',
                'Those do not go through the form above — you register so we can verify you, and a job application goes to the Careers desk.'),
        ];
    }
}
