<?php

namespace Silber\Bouncer;

use Silber\Bouncer\Database\Models;
use Silber\Bouncer\Database\Queries\Abilities;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BaseClipboard implements Contracts\Clipboard
{
    use HandlesAuthorization;

    /**
     * Holds the arguments of the current checks.
     *
     * We use a multi-dimensional array, so that users
     * are given the opportunity to do other checks
     * at the gate from their policy methods.
     *
     * @var array[]
     */
    protected $currentChecks = [];

    /**
     * Register the clipboard at the given gate.
     *
     * @param  \Illuminate\Contracts\Auth\Access\Gate  $gate
     * @return void
     */
    public function registerAt(Gate $gate)
    {
        $gate->before(function (
            $authority, $ability, $arguments = [], $additional = null
        ) use ($gate) {
            list($model, $additional) = $this->parseGateArguments($arguments, $additional);

            if (! is_null($additional)) {
                return;
            }

            // Before we run our own checks, we let the gate run its own checks
            // (including policies). Since we run as a "before" callback, we
            // need to make sure we don't run again in this second phase.
            if ($this->isCurrentCheck($authority, $ability, $model)) {
                return;
            }

            $result = $this->checkAtGate($gate, $authority, $ability, $model);

            if (!is_null($result)) {
                return $result;
            }

            if ($id = $this->checkGetId($authority, $ability, $model)) {
                return $this->allow('Bouncer granted permission via ability #'.$id);
            }

            // If the response from "checkGetId" is "false", then this ability
            // has been explicity forbidden. We'll return false so the gate
            // doesn't run any further checks. Otherwise we return null.
            return $id;
        });
    }

    /**
     * Determines whether we're in the middle of checking the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return bool|null
     */
    protected function isCurrentCheck(Model $authority, $ability, $model = null)
    {
        if (count($this->currentChecks) == 0) {
            return false;
        }

        return end($this->currentChecks) == [$authority, $ability, $model];
    }

    /**
     * Get the raw result from the gate for the given check.
     *
     * @param  \Illuminate\Contracts\Auth\Access\Gate  $gate
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return bool|null
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function checkAtGate(Gate $gate, $authority, $ability, $model = null)
    {
        $this->currentChecks[] = [$authority, $ability, $model];

        try {
            return $gate->getRaw($ability, $model);
        } finally {
            array_pop($this->currentChecks);
        }
    }

    /**
     * Parse the arguments we got from the gate.
     *
     * @param  mixed  $arguments
     * @param  mixed  $additional
     * @return array
     */
    protected function parseGateArguments($arguments, $additional)
    {
        // The way arguments are passed into the gate's before callback has changed in Laravel
        // in the middle of the 5.2 release. Before, arguments were spread out. Now they're
        // all supplied in a single array instead. We will normalize it into two values.
        if (! is_null($additional)) {
            return [$arguments, $additional];
        }

        if (is_array($arguments)) {
            return [
                isset($arguments[0]) ? $arguments[0] : null,
                isset($arguments[1]) ? $arguments[1] : null,
            ];
        }

        return [$arguments, null];
    }

    /**
     * Determine if the given authority has the given ability.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return bool
     */
    public function check(Model $authority, $ability, $model = null)
    {
        return (bool) $this->checkGetId($authority, $ability, $model);
    }

    /**
     * Determine if the given authority has the given ability, and return the ability ID.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return int|bool|null
     */
    abstract protected function checkGetId(Model $authority, $ability, $model = null);

    /**
     * Check if an authority has the given roles.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  array|string  $roles
     * @param  string  $boolean
     * @return bool
     */
    public function checkRole(Model $authority, $roles, $boolean = 'or')
    {
        $available = $this->getRoles($authority)
                          ->intersect(Models::role()->getRoleNames($roles));

        if ($boolean == 'or') {
            return $available->count() > 0;
        } elseif ($boolean === 'not') {
            return $available->count() === 0;
        }

        return $available->count() == count((array) $roles);
    }

    /**
     * Get the given authority's roles.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return \Illuminate\Support\Collection
     */
    public function getRoles(Model $authority)
    {
        $collection = $authority->roles()->get(['name'])->pluck('name');

        // In Laravel 5.1, "pluck" returns an Eloquent collection,
        // so we call "toBase" on it. In 5.2, "pluck" returns a
        // base instance, so there is no "toBase" available.
        if (method_exists($collection, 'toBase')) {
            $collection = $collection->toBase();
        }

        return $collection;
    }

    /**
     * Get a list of the authority's abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAbilities(Model $authority, $allowed = true)
    {
        return Abilities::forAuthority($authority, $allowed)->get();
    }

    /**
     * Get a list of the authority's forbidden abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForbiddenAbilities(Model $authority)
    {
        return $this->getAbilities($authority, false);
    }

    /**
     * Determine whether the authority owns the given model.
     *
     * @return bool
     */
    public function isOwnedBy($authority, $model)
    {
        return $model instanceof Model && Models::isOwnedBy($authority, $model);
    }
}
