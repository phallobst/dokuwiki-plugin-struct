<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AccessTableLookup
 *
 * Load and (more importantly) save data for Lookup Schemas
 *
 * @package dokuwiki\plugin\struct\meta
 */
class AccessTableLookup extends AccessTable {

    /**
     * Remove the current data
     */
    public function clearData() {
        if(!$this->rid) return; // no data

        /** @noinspection SqlResolve */
        $sql = 'DELETE FROM ? WHERE rid = ?';
        $this->sqlite->query($sql, 'data_'.$this->schema->getTable(), $this->rid);
        $this->sqlite->query($sql, 'multi_'.$this->schema->getTable(), $this->rid);
    }

    protected function getLastRevisionTimestamp() {
        return 0;
    }

    /**
     * @inheritDoc
     */
    protected function buildGetDataSQL($idColumn = 'rid')
    {
        return parent::buildGetDataSQL($idColumn);
    }

    /**
     * @inheritDoc
     */
    protected function getSingleSql()
    {
        $cols = array_merge($this->getSingleNoninputCols(), $this->singleCols);
        $cols = join(',', $cols);
        $vals = array_merge($this->getSingleNoninputValues(), $this->singleValues);
        $rid = $this->getRid() ?: "(SELECT (COALESCE(MAX(rid), 0 ) + 1) FROM $this->stable)";

        return "REPLACE INTO $this->stable (rid, $cols) VALUES ($rid," . trim(str_repeat('?,', count($vals)),',') . ');';
    }

    /**
     * @inheritDoc
     */
    protected function getMultiSql()
    {
        return "REPLACE INTO $this->mtable (pid, rid, rev, latest, colref, row, value) VALUES (?,?,?,?,?,?,?)";
    }

    /**
     * @inheritDoc
     */
    protected function validateTypeData($data)
    {
        // we do not store completely empty rows
        $isempty = array_reduce($data, function ($isempty, $cell) {
            return $isempty && ($cell === '' || $cell === [] || $cell === null);
        }, true);

        return !$isempty;
    }

    /**
     * @inheritDoc
     */
    protected function getSingleNoninputCols()
    {
        return ['pid', 'rev', 'latest'];
    }

    /**
     * @inheritDoc
     */
    protected function getSingleNoninputValues()
    {
        return [$this->pid, AccessTable::DEFAULT_REV, AccessTable::DEFAULT_LATEST];
    }

    /**
     * @inheritDoc
     */
    protected function getMultiNoninputValues()
    {
        return [$this->pid, $this->rid, AccessTable::DEFAULT_REV, AccessTable::DEFAULT_LATEST];
    }

    /**
     * Set new rid if this is a new insert

     * @return bool
     */
    protected function afterSingleSave()
    {
        $ok = true;
        if(!$this->rid) {
            $res = $this->sqlite->query("SELECT rid FROM $this->stable WHERE ROWID = last_insert_rowid()");
            $this->rid = $this->sqlite->res2single($res);
            $this->sqlite->res_close($res);
            if(!$this->rid) {
                $ok = false;
            }
        }
        return $ok;
    }
}
