<?php

namespace App\Support;

/**
 * The app's information architecture, in one place.
 *
 * Twelve flat top-level links did not fit at 1280px and gave no hint about how
 * the app is organised — the header scrolled sideways and the user had to
 * remember which of twelve words held what they wanted.
 *
 * Four sections, each answering a question a lifter actually asks. The desktop
 * header shows the sections; the pages inside appear as a sub-navigation once
 * you are in one, so depth is revealed rather than dumped.
 */
class Navigation
{
    /**
     * @return array<int, array{
     *     key: string, label: string, question: string, route: string,
     *     children: array<int, array{key: string, label: string, route: string, pattern: string}>
     * }>
     */
    public static function sections(): array
    {
        return [
            [
                'key' => 'today',
                'label' => __('app.sections.today'),
                'question' => __('app.sections.today_question'),
                'route' => 'dashboard',
                'children' => [
                    self::item('dashboard', __('app.nav.dashboard'), 'dashboard'),
                    self::item('goals', __('app.nav.goals'), 'goals'),
                    self::item('ai', __('app.nav.ai'), 'ai'),
                ],
            ],
            [
                'key' => 'training',
                'label' => __('app.sections.training'),
                'question' => __('app.sections.training_question'),
                'route' => 'performance',
                'children' => [
                    self::item('performance', __('app.nav.performance'), 'performance'),
                    self::item('muscle', __('app.nav.muscle'), 'muscle'),
                    self::item('strength-levels', __('app.nav.levels'), 'strength-levels'),
                    self::item('routines', __('app.nav.routines'), 'routines', 'routines*'),
                ],
            ],
            [
                'key' => 'body',
                'label' => __('app.sections.body'),
                'question' => __('app.sections.body_question'),
                'route' => 'body',
                'children' => [
                    self::item('body', __('app.nav.body'), 'body'),
                    self::item('nutrition', __('app.nav.nutrition'), 'nutrition'),
                    self::item('photos', __('app.nav.photos'), 'photos'),
                    self::item('compare', __('app.nav.compare'), 'compare'),
                ],
            ],
            [
                'key' => 'outlook',
                'label' => __('app.sections.outlook'),
                'question' => __('app.sections.outlook_question'),
                'route' => 'projections',
                'children' => [
                    self::item('projections', __('app.nav.projections'), 'projections'),
                ],
            ],
        ];
    }

    /** The section the current request belongs to, or null. */
    public static function activeSection(): ?array
    {
        foreach (self::sections() as $section) {
            foreach ($section['children'] as $child) {
                if (request()->routeIs($child['pattern'])) {
                    return $section;
                }
            }
        }

        return null;
    }

    private static function item(string $key, string $label, string $route, ?string $pattern = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'route' => $route,
            'pattern' => $pattern ?? $route,
        ];
    }
}
