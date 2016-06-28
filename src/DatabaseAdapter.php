<?php

namespace MinhD\ANDSLogUtil;
use mysqli;
use Gregwar\Cache\Cache;

class DatabaseAdapter
{
    protected $db_registry = null;
    protected $db_roles = null;
    protected $cache;

    public function __construct($config)
    {
        $this->db_registry = new mysqli(
            $config['DB_HOST'],
            $config['DB_USER'],
            $config['DB_PASS'],
            'dbs_registry'
        );

        $this->db_roles = new mysqli(
            $config['DB_HOST'],
            $config['DB_USER'],
            $config['DB_PASS'],
            'dbs_roles'
        );

        $this->cache = new Cache;
        $this->cache->setCacheDirectory('cache');
    }

    public function getRecord($roId) {
        $cacheId = 'ro.'.$roId;
        if ($this->cache->check($cacheId)) {
            return unserialize($this->cache->get($cacheId));
        }

        //set
        $sanitized = $this->db_registry->real_escape_string($roId);
        $query = $this->db_registry->query("select * from dbs_registry.registry_objects where registry_object_id = '{$sanitized}' limit 1");
        if (!$query->num_rows) {
            return false;
        }
        $result = $query->fetch_assoc();
        $this->cache->set($cacheId, serialize($result));
        return $result;
    }

    public function getRecordOwners($dataSourceID) {
        $cacheId = 'record_owners.ds.'.$dataSourceID;

        if ($this->cache->check($cacheId)) {
            return unserialize($this->cache->get($cacheId));
        }

        $dataSource = $this->getDataSource($dataSourceID);
        if (!$dataSource) {
            return [];
        }
        $roleID = $dataSource['record_owner'];
        $result = array_merge([$dataSource['record_owner']], $this->getParentRoles($roleID));
        $this->cache->set($cacheId, serialize($result));
        return $result;
    }

    public function test()
    {
        $query = $this->db_registry->query("select * from dbs_registry.data_sources");
        while($row = $query->fetch_assoc()) {
            $recordOwners = $this->getRecordOwners($row['data_source_id']);
            if (count($recordOwners) > 3) {
                var_dump($row['data_source_id']);
                var_dump($recordOwners);
                var_dump('---');
            }
        }
    }

    private function getDataSource($id)
    {
        $sanitized = $this->db_registry->real_escape_string($id);
        $query = $this->db_registry->query("select * from dbs_registry.data_sources where data_source_id = '{$sanitized}' limit 1");

        if (!$query || !$query->num_rows) {
            return false;
        }

        return $query->fetch_assoc();
    }

    private function getParentRoles($roleID, $processed = array())
    {
        $sanitized = $this->db_roles->real_escape_string($roleID);
        $query = $this->db_roles->query("
SELECT *
FROM dbs_roles.role_relations as relation, dbs_roles.roles as roles
where parent_role_id = '{$sanitized}'
and roles.role_id = relation.child_role_id
and roles.role_type_id = 'ROLE_ORGANISATIONAL'");

        if (!$query->num_rows) {
            return [];
        }

        $result = [];
        while ($row = $query->fetch_assoc()) {
            if (!in_array($row['child_role_id'], $processed)) {
                $result[] = $row['child_role_id'];
                $parents = $this->getParentRoles($row['child_role_id'], $result);
                if (count($parents) > 0) {
                    $result = array_merge($result, $parents);
                }
            }
        }

        return $result;
    }
}
