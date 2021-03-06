<?php

namespace Silber\Bouncer\Database\Queries;

use Silber\Bouncer\Database\Models;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;

class Abilities
{
    /**
     * Get a list of the authority's abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForAuthority(Model $authority, $allowed = true)
    {
        return Models::ability()
                     ->whereExists($this->getRoleConstraint($authority, $allowed))
                     ->orWhereExists($this->getAuthorityConstraint($authority, $allowed))
                     ->get();
    }

    /**
     * Get a constraint for abilities that have been granted to the given authority through a role.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Closure
     */
    protected function getRoleConstraint(Model $authority, $allowed)
    {
        return function ($query) use ($authority, $allowed) {
            $permissions = Models::table('permissions');
            $abilities   = Models::table('abilities');
            $roles       = Models::table('roles');
            $prefix      = Models::prefix();

            $query->from($roles)
                  ->join($permissions, $roles.'.uuid', '=', $permissions.'.entity_uuid')
                  ->whereRaw("{$prefix}{$permissions}.ability_uuid = {$prefix}{$abilities}.uuid")
                  ->where($permissions.".forbidden", ! $allowed)
                  ->where($permissions.".entity_type", Models::role()->getMorphClass());

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $permissions);

            $query->where(function ($query) use ($roles, $authority, $allowed) {
                $query->whereExists($this->getAuthorityRoleConstraint($authority));

                if ($allowed) {
                    $this->addRoleInheritCondition($query, $authority, $roles);
                }
            });
        };
    }

    /**
     * Add the role inheritence "where" clause to the given query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $roles
     * @return \Closure
     */
    protected function addRoleInheritCondition(Builder $query, Model $authority, $roles) {
        $query->orWhere('level', '<', function ($query) use ($authority, $roles) {
            $query->selectRaw('max(level)')
                  ->from($roles)
                  ->whereExists($this->getAuthorityRoleConstraint($authority));

            Models::scope()->applyToModelQuery($query, $roles);
        });
    }

    /**
     * Get a constraint for roles that are assigned to the given authority.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return \Closure
     */
    protected function getAuthorityRoleConstraint(Model $authority)
    {
        return function ($query) use ($authority) {
            $pivot  = Models::table('assigned_roles');
            $roles  = Models::table('roles');
            $table  = $authority->getTable();
            $prefix = Models::prefix();

            $query->from($table)
                  ->join($pivot, "{$table}.{$authority->getKeyName()}", '=', $pivot.'.entity_uuid')
                  ->whereRaw("{$prefix}{$pivot}.role_uuid = {$prefix}{$roles}.uuid")
                  ->where($pivot.'.entity_type', $authority->getMorphClass())
                  ->where("{$table}.{$authority->getKeyName()}", $authority->getKey());

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $pivot);
        };
    }

    /**
     * Get a constraint for abilities that have been granted to the given authority.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Closure
     */
    protected function getAuthorityConstraint(Model $authority, $allowed)
    {
        return function ($query) use ($authority, $allowed) {
            $permissions = Models::table('permissions');
            $abilities   = Models::table('abilities');
            $table       = $authority->getTable();
            $prefix      = Models::prefix();

            $query->from($table)
                  ->join($permissions, "{$table}.{$authority->getKeyName()}", '=', $permissions.'.entity_uuid')
                  ->whereRaw("{$prefix}{$permissions}.ability_uuid = {$prefix}{$abilities}.uuid")
                  ->where("{$permissions}.entity_type", $authority->getMorphClass())
                  ->where("{$permissions}.forbidden", ! $allowed)
                  ->where("{$table}.{$authority->getKeyName()}", $authority->getKey());

            Models::scope()->applyToModelQuery($query, $abilities);
            Models::scope()->applyToRelationQuery($query, $permissions);
        };
    }
}
