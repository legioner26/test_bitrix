<?php

namespace Custom\Component;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use CBitrixComponent;
use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Custom\EmployeeLaborReport\PreparationData;

require_once __DIR__ . '/lib/PreparationData.php';

class EmployeeLaborReport extends CBitrixComponent
{
    private const GRID_ID = 'CUSTOM_EMPLOYEE_LABOR_REPORT_GRID';
    private const FILTER_ID = 'CUSTOM_EMPLOYEE_LABOR_REPORT_FILTER';

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['PAGE_SIZE'] = (int) ($arParams['PAGE_SIZE'] ?? 20);
        if ($arParams['PAGE_SIZE'] <= 0)
        {
            $arParams['PAGE_SIZE'] = 20;
        }

        return $arParams;
    }

    public function executeComponent(): void
    {
        if (!Loader::includeModule('tasks'))
        {
            ShowError(Loc::getMessage('ERROR_TASK'));
            return;
        }

        $this->persistGridSortingFromRequest();
        $this->normalizePersistedGridSortCase();

        $filterFields = $this->getFilterFields();
        $filterValues = $this->getFilterValues($filterFields);
        $queryFilter = $this->buildQueryFilter($filterValues);

        $rows = PreparationData::getRows($queryFilter);
        $sorting = $this->getGridSorting();
        $rows = $this->sortRows($rows, $sorting);

        $nav = new PageNavigation(self::GRID_ID);
        $nav->allowAllRecords(false)
            ->setPageSize($this->arParams['PAGE_SIZE'])
            ->initFromUri();
        $nav->setRecordCount(count($rows));

        $pageRows = array_slice(
            $rows,
            $nav->getOffset(),
            $nav->getLimit()
        );

        $this->arResult = [
            'GRID_ID'     => self::GRID_ID,
            'FILTER_ID'   => self::FILTER_ID,
            'COLUMNS'     => $this->getGridColumns($sorting),
            'FILTER'      => $filterFields,
            'ROWS'        => $this->formatGridRows($pageRows),
            'NAV_OBJECT'  => $nav,
            'TOTAL_COUNT' => count($rows),
            'SORT'        => $sorting['field'],
            'SORT_ORDER'  => $sorting['order'],
        ];

        $this->includeComponentTemplate();
    }

    protected function getGridColumns(array $sorting = []): array
    {
        $columns = [
            [
                'id'      => 'FIO',
                'name'    => 'ФИО',
                'sort'    => 'FIO',
                'default' => true,
            ],
            [
                'id'      => 'DEPARTMENT',
                'name'    => 'Отдел',
                'sort'    => 'DEPARTMENT',
                'default' => true,
            ],
            [
                'id'      => 'PROJECT',
                'name'    => 'Проект',
                'sort'    => 'PROJECT',
                'default' => true,
            ],
            [
                'id'      => 'TASK',
                'name'    => 'Задача',
                'sort'    => 'TASK',
                'default' => true,
            ],
            [
                'id'          => 'WORK_DATE',
                'name'        => 'Дата',
                'sort'        => 'WORK_DATE',
                'first_order' => 'desc',
                'default'     => true,
            ],
            [
                'id'          => 'LABOR_HOURS',
                'name'        => 'Трудозатраты',
                'sort'        => 'LABOR_HOURS',
                'first_order' => 'desc',
                'default'     => true,
                'align'       => 'right',
                'type'        => 'number',
            ],
        ];

        if ($sorting === [])
        {
            return $columns;
        }

        $sortField = (string) ($sorting['field'] ?? '');
        $sortState = $this->toGridSortOrder((string) ($sorting['order'] ?? 'ASC'));

        foreach ($columns as &$column)
        {
            if (($column['sort'] ?? '') === $sortField)
            {
                $column['sort_state'] = $sortState;
            }
        }
        unset($column);

        return $columns;
    }

    protected function getFilterFields(): array
    {
        return [
            [
                'id'      => 'FIO',
                'name'    => 'ФИО',
                'type'    => 'string',
                'default' => true,
            ],
            [
                'id'      => 'DEPARTMENT',
                'name'    => 'Отдел',
                'type'    => 'list',
                'items'   => PreparationData::getDepartments(),
                'params'  => ['multiple' => 'N'],
                'default' => true,
            ],
            [
                'id'      => 'PROJECT',
                'name'    => 'Проект',
                'type'    => 'list',
                'items'   => PreparationData::getProjects(),
                'params'  => ['multiple' => 'N'],
                'default' => true,
            ],
            [
                'id'      => 'TASK',
                'name'    => 'Задача',
                'type'    => 'string',
                'default' => true,
            ],
            [
                'id'      => 'WORK_DATE',
                'name'    => 'Дата / период',
                'type'    => 'date',
                'default' => true,
            ],
        ];
    }

    protected function getFilterValues(array $filterFields): array
    {
        $filterOptions = new FilterOptions(self::FILTER_ID);
        $filterData = $filterOptions->getFilter($filterFields);

        return is_array($filterData) ? $filterData : [];
    }

    protected function buildQueryFilter(array $filterValues): array
    {
        $filter = [];

        if (!empty($filterValues['FIO']))
        {
            $filter['FIO'] = trim((string) $filterValues['FIO']);
        }

        if (!empty($filterValues['DEPARTMENT']))
        {
            $departmentId = is_array($filterValues['DEPARTMENT'])
                ? (int) ($filterValues['DEPARTMENT']['VALUE'] ?? 0)
                : (int) $filterValues['DEPARTMENT'];

            if ($departmentId > 0)
            {
                $filter['DEPARTMENT_ID'] = $departmentId;
            }
        }

        if (!empty($filterValues['PROJECT']))
        {
            $projectId = is_array($filterValues['PROJECT'])
                ? (int) ($filterValues['PROJECT']['VALUE'] ?? 0)
                : (int) $filterValues['PROJECT'];

            if ($projectId > 0)
            {
                $filter['PROJECT_ID'] = $projectId;
            }
        }

        if (!empty($filterValues['TASK']))
        {
            $filter['TASK'] = trim((string) $filterValues['TASK']);
        }

        if (!empty($filterValues['WORK_DATE_from']))
        {
            $filter['DATE_FROM'] = trim((string) $filterValues['WORK_DATE_from']);
        }

        if (!empty($filterValues['WORK_DATE_to']))
        {
            $filter['DATE_TO'] = trim((string) $filterValues['WORK_DATE_to']);
        }

        return $filter;
    }

    protected function persistGridSortingFromRequest(): void
    {
        $request = Context::getCurrent()->getRequest();
        $requestGridId = (string) $request->get('grid_id');

        if ($requestGridId !== '' && $requestGridId !== self::GRID_ID)
        {
            return;
        }

        $sortField = trim((string) $request->get('by'));
        if ($sortField === '')
        {
            return;
        }

        $sortOrder = $this->toGridSortOrder((string) $request->get('order'));
        $gridOptions = new \CGridOptions(self::GRID_ID);
        $gridOptions->SetSorting($sortField, $sortOrder);
        $gridOptions->Save();
    }

    protected function normalizePersistedGridSortCase(): void
    {
        $gridOptions = new \CGridOptions(self::GRID_ID);
        $sorting = $gridOptions->GetSorting([
            'sort' => ['WORK_DATE' => 'desc'],
            'vars' => ['by' => 'by', 'order' => 'order'],
        ]);

        if ($sorting['sort'] === [])
        {
            return;
        }

        $sortField = (string) key($sorting['sort']);
        $sortOrder = (string) current($sorting['sort']);

        if ($sortField === '' || $sortOrder === $this->toGridSortOrder($sortOrder))
        {
            return;
        }

        $gridOptions->SetSorting($sortField, $this->toGridSortOrder($sortOrder));
        $gridOptions->Save();
    }

    protected function getGridSorting(): array
    {
        $defaultSort = ['WORK_DATE' => 'DESC'];
        $request = Context::getCurrent()->getRequest();
        $requestGridId = (string) $request->get('grid_id');

        if ($sortField = trim((string) $request->get('by')))
        {
            if ($requestGridId === '' || $requestGridId === self::GRID_ID)
            {
                $orderRaw = (string) $request->get('order');

                return [
                    'field' => $sortField,
                    'order' => $orderRaw !== '' ? $this->normalizeSortOrder($orderRaw) : 'ASC',
                ];
            }
        }

        $gridOptions = new \CGridOptions(self::GRID_ID);
        $sorting = $gridOptions->GetSorting([
            'sort' => $defaultSort,
            'vars' => ['by' => 'by', 'order' => 'order'],
        ]);

        $sortField = (string) key($sorting['sort'] ?: $defaultSort);
        $sortOrder = $this->normalizeSortOrder((string) current($sorting['sort'] ?: $defaultSort));

        return [
            'field' => $sortField,
            'order' => $sortOrder,
        ];
    }

    protected function normalizeSortOrder(string $order): string
    {
        return strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    }

    protected function toGridSortOrder(string $order): string
    {
        return strtoupper($order) === 'DESC' ? 'desc' : 'asc';
    }

    protected function sortRows(array $rows, array $sorting): array
    {
        $sortField = $sorting['field'];
        $isDesc = $sorting['order'] === 'DESC';

        usort($rows, static function (array $a, array $b) use ($sortField, $isDesc): int {
            $valueA = $a[$sortField] ?? '';
            $valueB = $b[$sortField] ?? '';

            if ($sortField === 'LABOR_HOURS')
            {
                $result = (float) $valueA <=> (float) $valueB;
            }
            elseif ($sortField === 'WORK_DATE')
            {
                $result = strcmp((string) $valueA, (string) $valueB);
            }
            else
            {
                $result = strcasecmp((string) $valueA, (string) $valueB);
            }

            if ($result === 0)
            {
                $result = ((int) ($a['ID'] ?? 0)) <=> ((int) ($b['ID'] ?? 0));
            }

            return $isDesc ? -$result : $result;
        });

        return $rows;
    }

    protected function formatGridRows(array $rows): array
    {
        $gridRows = [];

        foreach ($rows as $row)
        {
            $gridRows[] = [
                'id'   => $row['ID'],
                'data' => [
                    'FIO'         => htmlspecialcharsbx($row['FIO']),
                    'DEPARTMENT'  => htmlspecialcharsbx($row['DEPARTMENT']),
                    'PROJECT'     => htmlspecialcharsbx($row['PROJECT']),
                    'TASK'        => htmlspecialcharsbx($row['TASK']),
                    'WORK_DATE'   => FormatDate('d.m.Y', MakeTimeStamp($row['WORK_DATE'], 'YYYY-MM-DD')),
                    'LABOR_HOURS' => number_format((float) $row['LABOR_HOURS'], 2, '.', ' ') . ' ч.',
                ],
            ];
        }

        return $gridRows;
    }
}
