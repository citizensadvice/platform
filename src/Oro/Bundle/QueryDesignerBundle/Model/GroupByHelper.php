<?php

namespace Oro\Bundle\QueryDesignerBundle\Model;

class GroupByHelper
{
    /**
     * Get fields that must appear in GROUP BY.
     *
     * @param string|array $groupBy
     * @param array        $selects
     * @return array
     */
    public function getGroupByFields($groupBy, $selects)
    {

        /**
         * Previous commit Reference: 3c09c18
         * 
         * Previously there was a block of code here which formed the group by clause for the datagrid select statement.
         * This added any missing columns to the group by clause that would have been missing to ensure the query is valid.
         * This condition is not required on MySQL and actually changes the results that we were expecting.
         * 
         * We stripped adding a group by clause on this query due to the issue above - this means
         * the group by clause is instead defined in the relating datagrid.yml file and not altered retrospectively.
         * 
         * The result is that the amount of rows returned to the datagrid is the same as Oro 1.9
         */

        return array_unique($this->getPreparedGroupBy($groupBy));
    }

    /**
     * Get GROUP BY statements as array of trimmed parts.
     *
     * @param string|array $groupBy
     * @return array
     */
    protected function getPreparedGroupBy($groupBy)
    {
        if (!is_array($groupBy)) {
            $groupBy = explode(',', $groupBy);
        }

        $result = [];
        foreach ($groupBy as $groupByPart) {
            $groupByPart = trim((string)$groupByPart);
            if ($groupByPart) {
                $result[] = $groupByPart;
            }
        }

        return $result;
    }

    /**
     * @param string $select
     * @return bool
     */
    protected function hasAggregate($select)
    {
        // subselect
        if (stripos($select, '(SELECT') === 0) {
            return false;
        }

        preg_match('/(MIN|MAX|AVG|COUNT|SUM|GROUP_CONCAT)\(/i', $select, $matches);

        return (bool)$matches;
    }

    /**
     * Search for field alias if applicable or field name to use in group by
     *
     * @param string $select
     * @return string|null
     */
    protected function getFieldForGroupBy($select)
    {
        preg_match('/([^\s]+)\s+as\s+(\w+)$/i', $select, $parts);
        if (!empty($parts[2])) {
            // Add alias
            return $parts[2];
        } elseif (!$parts && strpos($select, ' ') === false) {
            // Add field itself when there is no alias
            return $select;
        }

        return null;
    }
}
