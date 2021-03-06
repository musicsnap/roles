<?php

namespace GeniusTS\Roles\Traits;


use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;

trait HasRoleAndPermission
{

    /**
     * Property for caching roles.
     *
     * @var \Illuminate\Database\Eloquent\Collection|null
     */
    protected $roles;

    /**
     * Property for caching permissions.
     *
     * @var \Illuminate\Database\Eloquent\Collection|null
     */
    protected $permissions;

    /**
     * User belongs to many roles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(config('roles.models.role'))->withTimestamps();
    }

    /**
     * Get all roles as collection.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRoles()
    {
        return (! $this->roles) ? $this->roles = $this->roles()->get() : $this->roles;
    }

    /**
     * Check if the user has a role or roles.
     *
     * @param int|string|array $role
     * @param bool             $all
     *
     * @return bool
     */
    public function isRole($role, $all = false)
    {
        if ($this->isPretendEnabled())
        {
            return $this->pretend('is');
        }

        return $this->{$this->getMethodName('is', $all)}($role);
    }

    /**
     * Check if the user has at least one role.
     *
     * @param int|string|array $roles
     *
     * @return bool
     */
    public function isOne($roles)
    {
        foreach ($this->getArrayFrom($roles) as $role)
        {
            if ($this->hasRole($role))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has all roles.
     *
     * @param int|string|array $roles
     *
     * @return bool
     */
    public function isAll($roles)
    {
        foreach ($this->getArrayFrom($roles) as $role)
        {
            if (! $this->hasRole($role))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the user has role.
     *
     * @param int|string $role
     *
     * @return bool
     */
    public function hasRole($role)
    {
        return $this->getRoles()->contains(function ($model, $key) use ($role) {
            return $role == $model->id || Str::is($role, $model->slug);
        });
    }

    /**
     * Attach role to a user.
     *
     * @param int|\GeniusTS\Roles\Models\Role $role
     *
     * @return null|bool
     */
    public function attachRole($role)
    {
        if (! $this->getRoles()->contains($role))
        {
            $this->roles()->attach($role);

            return $this->roles = null;
        }

        return true;
    }

    /**
     * Detach role from a user.
     *
     * @param int|\GeniusTS\Roles\Models\Role $role
     *
     * @return int
     */
    public function detachRole($role)
    {
        $this->roles = null;

        return $this->roles()->detach($role);
    }

    /**
     * Detach all roles from a user.
     *
     * @return int
     */
    public function detachAllRoles()
    {
        $this->roles = null;

        return $this->roles()->detach();
    }

    /**
     * Get role level of a user.
     *
     * @return int
     */
    public function level()
    {
        return ($role = $this->getRoles()->sortByDesc('level')->first()) ? $role->level : 0;
    }

    /**
     * Get all permissions from roles.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function rolePermissions()
    {
        $permissionModel = app(config('roles.models.permission'));

        if (! $permissionModel instanceof Model)
        {
            throw new InvalidArgumentException('[roles.models.permission] must be an instance of \Illuminate\Database\Eloquent\Model');
        }

        return $permissionModel::select([
            'permissions.*',
            'permission_role.created_at as pivot_created_at',
            'permission_role.updated_at as pivot_updated_at',
        ])
            ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->whereIn('roles.id', $this->getRoles()->pluck('id')->toArray())
            ->orWhere('roles.level', '<', $this->level())
            ->groupBy([
                'permissions.id',
                'permissions.name',
                'permissions.slug',
                'permissions.description',
                'permissions.model',
                'permissions.created_at',
                'permissions.updated_at',
                'pivot_created_at',
                'pivot_updated_at',
            ]);
    }

    /**
     * User belongs to many permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function userPermissions()
    {
        return $this->belongsToMany(config('roles.models.permission'))->withTimestamps();
    }

    /**
     * Get all permissions as collection.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermissions()
    {
        return (! $this->permissions) ? $this->permissions = $this->rolePermissions()
            ->get()
            ->merge($this->userPermissions()->get()) : $this->permissions;
    }

    /**
     * Check if the user has a permission or permissions.
     *
     * @param int|string|array $permission
     * @param bool             $all
     *
     * @return bool
     */
    public function can($permission, $all = false)
    {
        if ($this->isPretendEnabled())
        {
            return $this->pretend('can');
        }

        return $this->{$this->getMethodName('can', $all)}($permission);
    }

    /**
     * Check if the user has at least one permission.
     *
     * @param int|string|array $permissions
     *
     * @return bool
     */
    public function canOne($permissions)
    {
        foreach ($this->getArrayFrom($permissions) as $permission)
        {
            if ($this->hasPermission($permission))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has all permissions.
     *
     * @param int|string|array $permissions
     *
     * @return bool
     */
    public function canAll($permissions)
    {
        foreach ($this->getArrayFrom($permissions) as $permission)
        {
            if (! $this->hasPermission($permission))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the user has a permission.
     *
     * @param int|string $permission
     *
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->getPermissions()->contains(function ($model, $key) use ($permission) {
            return $permission == $model->id || Str::is($permission, $model->slug);
        });
    }

    /**
     * Check if the user is allowed to manipulate with entity.
     *
     * @param string                              $providedPermission
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param bool                                $owner
     * @param string                              $ownerColumn
     *
     * @return bool
     */
    public function allowed($providedPermission, Model $entity, $owner = true, $ownerColumn = 'user_id')
    {
        if ($this->isPretendEnabled())
        {
            return $this->pretend('allowed');
        }

        if ($owner === true && $entity->{$ownerColumn} == $this->id)
        {
            return true;
        }

        return $this->isAllowed($providedPermission, $entity);
    }

    /**
     * Check if the user is allowed to manipulate with provided entity.
     *
     * @param string                              $providedPermission
     * @param \Illuminate\Database\Eloquent\Model $entity
     *
     * @return bool
     */
    protected function isAllowed($providedPermission, Model $entity)
    {
        foreach ($this->getPermissions() as $permission)
        {
            if ($permission->model != '' && get_class($entity) == $permission->model
                && ($permission->id == $providedPermission || $permission->slug === $providedPermission)
            )
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Attach permission to a user.
     *
     * @param int|\GeniusTS\Roles\Models\Permission $permission
     *
     * @return null|bool
     */
    public function attachPermission($permission)
    {
        if (! $this->getPermissions()->contains($permission))
        {
            $this->userPermissions()->attach($permission);

            return $this->permissions = null;
        }

        return true;
    }

    /**
     * Detach permission from a user.
     *
     * @param int|\GeniusTS\Roles\Models\Permission $permission
     *
     * @return int
     */
    public function detachPermission($permission)
    {
        $this->permissions = null;

        return $this->userPermissions()->detach($permission);
    }

    /**
     * Detach all permissions from a user.
     *
     * @return int
     */
    public function detachAllPermissions()
    {
        $this->permissions = null;

        return $this->userPermissions()->detach();
    }

    /**
     * Check if pretend option is enabled.
     *
     * @return bool
     */
    private function isPretendEnabled()
    {
        return (bool) config('roles.pretend.enabled');
    }

    /**
     * Allows to pretend or simulate package behavior.
     *
     * @param string $option
     *
     * @return bool
     */
    private function pretend($option)
    {
        return (bool) config('roles.pretend.options.' . $option);
    }

    /**
     * Get method name.
     *
     * @param string $methodName
     * @param bool   $all
     *
     * @return string
     */
    private function getMethodName($methodName, $all)
    {
        return ((bool) $all) ? $methodName . 'All' : $methodName . 'One';
    }

    /**
     * Get an array from argument.
     *
     * @param int|string|array $argument
     *
     * @return array
     */
    private function getArrayFrom($argument)
    {
        return ! is_array($argument) ? preg_split('/ ?[,|] ?/', $argument) : $argument;
    }

    /**
     * Handle dynamic method calls.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method !== 'is' && Str::startsWith($method, 'is'))
        {
            return $this->isRole(Str::snake(substr($method, 2), config('roles.separator')));
        }

        if (Str::startsWith($method, 'can'))
        {
            return $this->can(Str::snake(substr($method, 3), config('roles.separator')));
        }

        if (Str::startsWith($method, 'allowed'))
        {
            return $this->allowed(
                Str::snake(substr($method, 7), config('roles.separator')),
                $parameters[0],
                $parameters[1] ?? true,
                $parameters[2] ?? 'user_id'
            );
        }

        return parent::__call($method, $parameters);
    }
}
