<?php

namespace Custom\EmployeeLaborReport;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Bitrix\Socialnetwork\WorkgroupTable;
use Bitrix\Tasks\Internals\Task\ElapsedTimeTable;
use Bitrix\Tasks\Internals\TaskTable;
use Exception;
use Bitrix\Main\Diag\Debug;

class PreparationData
{
    private const DEPARTMENTS_IBLOCK_CODE = 'departments';
    private const DEPARTMENTS_IBLOCK_TYPE = 'structure';

    private static ?array $departmentNames = null;

    private static ?int $departmentsIblockId = null;

    private static ?array $userDepartmentNames = null;

    public static function getRows(array $filter = []): array
    {
        if (!self::loadModules()) {
            return [];
        }

        $runtime = self::getElapsedTimeRuntime();
        $queryFilter = self::buildElapsedTimeFilter($filter);

        try {
            $result = ElapsedTimeTable::getList([
                'select' => [
                    'ID',
                    'USER_ID',
                    'SECONDS',
                    'DATE_START',
                    'CREATED_DATE',
                    'WORK_DATE',
                    'TASK_TITLE' => 'TASK.TITLE',
                    'PROJECT_NAME' => 'GROUP.NAME',
                    'USER_NAME' => 'USER.NAME',
                    'USER_LAST_NAME' => 'USER.LAST_NAME',
                    'USER_SECOND_NAME' => 'USER.SECOND_NAME',
                    'USER_LOGIN' => 'USER.LOGIN',
                ],
                'filter' => $queryFilter,
                'order' => [
                    'WORK_DATE' => 'DESC',
                    'ID' => 'DESC',
                ],
                'runtime' => $runtime,
                'cache' => [
                    'ttl' => 3600,
                    'cache_joins' => true,
                ],
            ]);
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }


        $rows = [];

        while ($row = $result->fetch()) {
            $userId = (int)$row['USER_ID'];

            $rows[] = [
                'ID' => (int)$row['ID'],
                'FIO' => self::formatUserName([
                    'NAME' => $row['USER_NAME'] ?? '',
                    'LAST_NAME' => $row['USER_LAST_NAME'] ?? '',
                    'SECOND_NAME' => $row['USER_SECOND_NAME'] ?? '',
                    'LOGIN' => $row['USER_LOGIN'] ?? '',
                ]),
                'DEPARTMENT' => self::getUserDepartmentName($userId),
                'PROJECT' => (string)($row['PROJECT_NAME'] ?? '') ?: '—',
                'TASK' => (string)($row['TASK_TITLE'] ?? ''),
                'LABOR_HOURS' => round(((int)$row['SECONDS']) / 3600, 2),
                'WORK_DATE' => self::normalizeDate($row['DATE_START'] ?? null, $row['CREATED_DATE'] ?? null),
            ];
        }

        return $rows;
    }

    public static function getDepartments(): array
    {
        if (!self::loadModules()) {
            return [];
        }

        $iblockId = self::getDepartmentsIblockId();
        if ($iblockId <= 0) {
            return [];
        }

        $items = [];

        try {
            $result = SectionTable::getList([
                'select' => ['ID', 'NAME'],
                'filter' => [
                    '=IBLOCK_ID' => $iblockId,
                    '>ID' => 1,
                ],
                'order' => ['LEFT_MARGIN' => 'ASC'],
                'cache' => [
                    'ttl' => 3600,
                ],
            ]);
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }

        while ($row = $result->fetch()) {
            $items[(string)$row['ID']] = (string)$row['NAME'];
        }

        return $items;
    }

    public static function getProjects(): array
    {
        if (!self::loadModules()) {
            return [];
        }

        $groupIds = [];
        $runtime = self::getElapsedTimeRuntime();

        try {
            $result = ElapsedTimeTable::getList([
                'select' => ['GROUP_ID' => 'TASK.GROUP_ID'],
                'filter' => [
                    '!TASK.GROUP_ID' => false,
                ],
                'group' => ['TASK.GROUP_ID'],
                'runtime' => $runtime,
                'cache' => [
                    'ttl' => 3600,
                    'cache_joins' => true,
                ],
            ]);
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }

        while ($row = $result->fetch()) {
            $groupId = (int)($row['GROUP_ID'] ?? 0);
            if ($groupId > 0) {
                $groupIds[] = $groupId;
            }
        }

        if ($groupIds === []) {
            return [];
        }

        $items = [];

        try {
            $groups = WorkgroupTable::getList([
                'select' => ['ID', 'NAME'],
                'filter' => [
                    '@ID' => array_values(array_unique($groupIds)),
                ],
                'order' => ['NAME' => 'ASC'],
                'cache' => [
                    'ttl' => 3600,
                ],
            ]);
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }

        while ($row = $groups->fetch()) {
            $items[(string)$row['ID']] = (string)$row['NAME'];
        }

        return $items;
    }

    private static function loadModules(): bool
    {
        return Loader::includeModule('tasks')
            && Loader::includeModule('intranet')
            && Loader::includeModule('socialnetwork')
            && Loader::includeModule('iblock');
    }

    private static function getElapsedTimeRuntime(): array
    {
        return [
            new ReferenceField(
                'TASK',
                TaskTable::class,
                Join::on('this.TASK_ID', 'ref.ID')
            ),
            new ReferenceField(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID')
            ),
            new ReferenceField(
                'GROUP',
                WorkgroupTable::class,
                Join::on('this.TASK.GROUP_ID', 'ref.ID'),
                ['join_type' => Join::TYPE_LEFT]
            ),
            new ExpressionField(
                'WORK_DATE',
                'COALESCE(%s, %s)',
                ['DATE_START', 'CREATED_DATE']
            ),
        ];
    }

    private static function buildElapsedTimeFilter(array $filter): array
    {
        $queryFilter = [
            '=USER.ACTIVE' => 'Y',
        ];

        $dateFrom = self::parseFilterDate($filter['DATE_FROM'] ?? null);
        $dateTo = self::parseFilterDateEnd($filter['DATE_TO'] ?? null);

        if ($dateFrom !== null) {
            $queryFilter[] = [
                'LOGIC' => 'OR',
                ['>=DATE_START' => $dateFrom],
                [
                    'LOGIC' => 'AND',
                    ['=DATE_START' => null],
                    ['>=CREATED_DATE' => $dateFrom],
                ],
            ];
        }

        if ($dateTo !== null) {
            $queryFilter[] = [
                'LOGIC' => 'OR',
                ['<=DATE_START' => $dateTo],
                [
                    'LOGIC' => 'AND',
                    ['=DATE_START' => null],
                    ['<=CREATED_DATE' => $dateTo],
                ],
            ];
        }

        if (!empty($filter['PROJECT_ID'])) {
            $queryFilter['=TASK.GROUP_ID'] = (int)$filter['PROJECT_ID'];
        }

        if (!empty($filter['TASK'])) {
            $queryFilter['%TASK.TITLE'] = $filter['TASK'];
        }

        if (!empty($filter['FIO'])) {
            $queryFilter[] = [
                'LOGIC' => 'OR',
                ['%USER.LAST_NAME' => $filter['FIO']],
                ['%USER.NAME' => $filter['FIO']],
                ['%USER.SECOND_NAME' => $filter['FIO']],
                ['%USER.LOGIN' => $filter['FIO']],
            ];
        }

        if (!empty($filter['DEPARTMENT_ID'])) {
            $userIds = self::getUserIdsByDepartment((int)$filter['DEPARTMENT_ID']);
            if ($userIds === []) {
                $queryFilter['=ID'] = 0;
            } else {
                $queryFilter['@USER_ID'] = $userIds;
            }
        }

        return $queryFilter;
    }

    private static function parseFilterDate(?string $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y'] as $format) {
            try {
                return new DateTime($value, $format);
            } catch (\Bitrix\Main\ObjectException $e) {
                continue;
            }
        }

        $timestamp = MakeTimeStamp($value);
        if ($timestamp > 0) {
            return DateTime::createFromTimestamp($timestamp);
        }

        return null;
    }

    private static function parseFilterDateEnd(?string $value): ?DateTime
    {
        $date = self::parseFilterDate($value);
        if ($date === null) {
            return null;
        }

        try {
            return new DateTime($date->format('Y-m-d') . ' 23:59:59', 'Y-m-d H:i:s');
        } catch (\Bitrix\Main\ObjectException $e) {
            return null;
        }
    }

    private static function getDepartmentsIblockId(): int
    {
        if (self::$departmentsIblockId !== null) {
            return self::$departmentsIblockId;
        }

        self::$departmentsIblockId = 0;

        try {
            $iblock = IblockTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=IBLOCK_TYPE_ID' => self::DEPARTMENTS_IBLOCK_TYPE,
                    '=CODE' => self::DEPARTMENTS_IBLOCK_CODE,
                ],
                'limit' => 1,
                'cache' => [
                    'ttl' => 3600,
                ],
            ])->fetch();
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }

        if ($iblock) {
            self::$departmentsIblockId = (int)$iblock['ID'];
        }

        return self::$departmentsIblockId;
    }

    private static function getDepartmentNames(): array
    {
        if (self::$departmentNames !== null) {
            return self::$departmentNames;
        }

        self::$departmentNames = [];
        $iblockId = self::getDepartmentsIblockId();

        if ($iblockId <= 0) {
            return self::$departmentNames;
        }

        try {
            $result = SectionTable::getList([
                'select' => ['ID', 'NAME'],
                'filter' => ['=IBLOCK_ID' => $iblockId],
                'cache' => [
                    'ttl' => 3600,
                ],
            ]);
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }

        while ($row = $result->fetch()) {
            self::$departmentNames[(int)$row['ID']] = (string)$row['NAME'];
        }

        return self::$departmentNames;
    }

    private static function getUserDepartmentName(int $userId): string
    {
        if (self::$userDepartmentNames === null) {
            self::$userDepartmentNames = [];
        }

        if (!isset(self::$userDepartmentNames[$userId])) {
            self::$userDepartmentNames[$userId] = self::resolveUserDepartmentName($userId);
        }

        return self::$userDepartmentNames[$userId];
    }

    private static function resolveUserDepartmentName(int $userId): string
    {
        $departmentIds = self::getUserDepartmentIds($userId);
        $departmentNames = self::getDepartmentNames();

        if ($departmentIds) {
            foreach ($departmentIds as $departmentId) {
                if ($departmentId > 1 && !empty($departmentNames[$departmentId])) {
                    return $departmentNames[$departmentId];
                }
            }
        }

        return '—';
    }

    private static function getUserDepartmentIds(int $userId): ?array
    {

        try {
            $user = UserTable::getList([
                'select' => ['UF_DEPARTMENT'],
                'filter' => ['=ID' => $userId],
                'limit' => 1,
                'cache' => [
                    'ttl' => 3600,
                ],
            ])->fetch();
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }

        if (!$user || empty($user['UF_DEPARTMENT'])) {
            return [];
        }

        $values = is_array($user['UF_DEPARTMENT'])
            ? $user['UF_DEPARTMENT']
            : [$user['UF_DEPARTMENT']];

        $departmentIds = array_values(array_filter(array_map('intval', $values)));

        if ($departmentIds !== []) {
            return $departmentIds;
        }

        return null;
    }

    private static function getUserIdsByDepartment(int $departmentId): array
    {
        $userIds = [];

        try {
            $result = UserTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=ACTIVE' => 'Y',
                    '=UF_DEPARTMENT' => $departmentId,
                ],
                'cache' => [
                    'ttl' => 3600,
                ],
            ]);
        } catch (Exception $exception) {
            AddMessage2Log($exception->getMessage(), 'main');
        }

        while ($row = $result->fetch()) {
            $userIds[] = (int)$row['ID'];
        }

        return array_values(array_unique($userIds));
    }

    private static function formatUserName(array $user): string
    {
        $name = trim(implode(' ', array_filter([
            (string)($user['LAST_NAME'] ?? ''),
            (string)($user['NAME'] ?? ''),
            (string)($user['SECOND_NAME'] ?? ''),
        ])));

        if ($name !== '') {
            return $name;
        }

        return (string)($user['LOGIN'] ?? '');
    }

    private static function normalizeDate($dateStart, $createdDate): string
    {
        $value = $dateStart ?: $createdDate;

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        return date('Y-m-d');
    }
}
