<?php

namespace Silber\Bouncer;

use Illuminate\Auth\Access\Gate as BaseGate;

class Gate extends BaseGate
{
    /**
     * Get the raw result from the authorization callback.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return mixed
     */
    public function getRaw($ability, $arguments = [])
    {
        return $this->raw($ability, $arguments);
    }

    /**
     * Resolve the callable for the given ability and arguments.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return callable
     */
    protected function resolveAuthCallback($user, $ability, array $arguments)
    {
        if (isset($arguments[0]) &&
            ! is_null($policy = $this->getPolicyFor($arguments[0])) &&
            $callback = $this->resolvePolicyCallback($user, $ability, $arguments, $policy)) {
            return $callback;
        }

        if (isset($this->abilities[$ability])) {
            return $this->abilities[$ability];
        }

        return function () {
            return null;
        };
    }

    /**
     * Resolve the callback for a policy check.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @param  mixed  $policy
     * @return bool|callable
     */
    protected function resolvePolicyCallback($user, $ability, array $arguments, $policy)
    {
        if (! is_callable([$policy, $this->formatAbilityToMethod($ability)])) {
            return function () {
                return null;
            };
        }

        return function () use ($user, $ability, $arguments, $policy) {
            // This callback will be responsible for calling the policy's before method and
            // running this policy method if necessary. This is used to when objects are
            // mapped to policy objects in the user's configurations or on this class.
            $result = $this->callPolicyBefore(
                $policy, $user, $ability, $arguments
            );

            // When we receive a non-null result from this before method, we will return it
            // as the "final" results. This will allow developers to override the checks
            // in this policy to return the result for all rules defined in the class.
            if (! is_null($result)) {
                return $result;
            }

            $ability = $this->formatAbilityToMethod($ability);

            // If this first argument is a string, that means they are passing a class name
            // to the policy. We will remove the first argument from this argument array
            // because this policy already knows what type of models it can authorize.
            if (isset($arguments[0]) && is_string($arguments[0])) {
                array_shift($arguments);
            }

            return is_callable([$policy, $ability])
                        ? $policy->{$ability}($user, ...$arguments)
                        : null;
        };
    }
}
