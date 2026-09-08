<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class CoverageAccessService
{
    public const LEVEL_ORDER = ['GM', 'RM', 'SAM', 'AM', 'MR'];

    private const GLOBAL_ROLE_NAMES = ['Super Admin', 'Admin IT', 'Marketing Insight', 'Direksi'];
    private const DRILLDOWN_RESTRICTED_ROLE_NAMES = [
        'Marketing Representative',
        'Medical Representative',
        'Sales Representative',
    ];

    public function rootCoverages(?User $user = null): array
    {
        if (!$user) {
            return [];
        }

        $user->loadMissing('coverages');

        return $user->coverages
            ->pluck('id_coverage')
            ->filter()
            ->values()
            ->all();
    }

    public function accessibleDrillLevels(?User $user = null): array
    {
        if (!$user) {
            return [];
        }

        if ($this->isGlobalAccessUser($user)) {
            return self::LEVEL_ORDER;
        }

        $user->loadMissing('coverages');

        $levels = $user->coverages
            ->pluck('cov_level')
            ->filter(fn ($level) => $level !== null && $level !== '')
            ->map(fn ($level) => (int) $level)
            ->values()
            ->all();

        if (empty($levels)) {
            return [];
        }

        $minLevel = min($levels);
        $startLevel = $this->levelNumToName((string) $minLevel);

        if (!$startLevel) {
            return [];
        }

        $startIndex = array_search($startLevel, self::LEVEL_ORDER, true);

        return $startIndex === false
            ? []
            : array_slice(self::LEVEL_ORDER, $startIndex);
    }

    public function defaultDrillLevel(?User $user = null): string
    {
        $levels = $this->accessibleDrillLevels($user);

        return $levels[0] ?? 'GM';
    }

    public function coverageAccessConfig(?User $user = null): array
    {
        return [
            'is_global_access' => $this->isGlobalAccessUser($user),
            'coverage_roots' => $this->rootCoverages($user),
        ];
    }

    public function filterCoverageCollection(Collection $coverageList, ?User $user = null): Collection
    {
        if ($this->isGlobalAccessUser($user)) {
            return $coverageList;
        }

        $roots = $this->rootCoverages($user);

        if (empty($roots)) {
            return collect();
        }

        return $coverageList->filter(function ($cov) use ($roots) {
            foreach ($roots as $root) {
                if (strpos((string) $cov->id_coverage, (string) $root) === 0) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    public function dashboardAccessConfig(?User $user = null): array
    {
        $drilldownLevels = $this->accessibleDrillLevels($user);
        $defaultDrilldownLevel = $this->defaultDrillLevel($user);
        $isGlobalAccess = $this->isGlobalAccessUser($user);
        $canViewDrilldown = $this->canViewDashboardDrilldown($user);

        return [
            'is_global_access' => $isGlobalAccess,
            'filter_levels' => $isGlobalAccess ? self::LEVEL_ORDER : ($drilldownLevels ?: self::LEVEL_ORDER),
            'drilldown_levels' => $drilldownLevels ?: self::LEVEL_ORDER,
            'default_drilldown_level' => $defaultDrilldownLevel,
            'can_view_drilldown' => $canViewDrilldown,
        ];
    }

    public function applyScope($query, string $column, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query;
        }

        if ($this->isGlobalAccessUser($user)) {
            return $query;
        }

        $roots = $this->rootCoverages($user);

        if (empty($roots)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($column, $roots) {
            foreach ($roots as $root) {
                $q->orWhere($column, 'like', $root . '%');
            }
        });
    }

    public function canAccessCoverage(?User $user, ?string $coverageId): bool
    {
        if (!$user || !$coverageId) {
            return false;
        }

        if ($this->isGlobalAccessUser($user)) {
            return true;
        }

        foreach ($this->rootCoverages($user) as $root) {
            if (strpos((string) $coverageId, (string) $root) === 0) {
                return true;
            }
        }

        return false;
    }

    public function isGlobalAccessUser(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(self::GLOBAL_ROLE_NAMES);
    }

    public function canViewDashboardDrilldown(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return ! $user->hasAnyRole(self::DRILLDOWN_RESTRICTED_ROLE_NAMES);
    }

    public function levelNumToName(?string $num): ?string
    {
        if ($num === null || $num === '') {
            return null;
        }

        $map = [
            'GM'  => '1',
            'RM'  => '2',
            'SAM' => '3',
            'AM'  => '4',
            'MR'  => '5',
        ];

        return array_search((string) $num, $map, true) ?: null;
    }
}
