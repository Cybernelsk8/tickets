<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Admin\DeleteUser;
use App\Actions\Admin\SaveUser;
use App\Models\Area;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?int $areaId = null;

    public string $jobTitle = '';

    public bool $isActive = true;

    /** @var array<int, string> */
    public array $selectedRoles = [];

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users()
    {
        $search = trim($this->search);

        return User::query()
            ->with(['roles', 'permissions', 'area'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(10);
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles()
    {
        return Role::query()->orderBy('name')->get();
    }

    /** @return Collection<int, Permission> */
    #[Computed]
    public function permissions()
    {
        return Permission::query()->orderBy('name')->get();
    }

    /** @return Collection<int, Area> */
    #[Computed]
    public function areas()
    {
        return Area::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        Gate::authorize('create', User::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $userId): void
    {
        $user = User::findOrFail($userId);
        Gate::authorize('update', $user);

        $this->resetForm();
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->areaId = $user->area_id;
        $this->jobTitle = $user->job_title ?? '';
        $this->isActive = $user->is_active;
        $this->selectedRoles = $user->getRoleNames()->all();
        $this->selectedPermissions = $user->getPermissionNames()->all();
        $this->showForm = true;
    }

    public function save(SaveUser $action): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [$this->editingUserId === null ? 'required' : 'nullable', 'string', 'min:8'],
            'areaId' => ['nullable', 'integer', 'exists:areas,id'],
            'jobTitle' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user = $this->editingUserId === null ? null : User::findOrFail($this->editingUserId);

        try {
            $action->handle([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'area_id' => $this->areaId,
                'job_title' => $this->jobTitle ?: null,
                'is_active' => $this->isActive,
                'roles' => $this->selectedRoles,
                'permissions' => $this->selectedPermissions,
            ], $user);
        } catch (ValidationException $exception) {
            $this->addError('user', $exception->validator->errors()->first() ?: $exception->getMessage());

            return;
        }

        unset($this->users);
        $this->closeForm();
        Flux::toast(text: __('Usuario guardado correctamente.'), variant: 'success');
    }

    public function delete(int $userId, DeleteUser $action): void
    {
        try {
            $action->handle(User::findOrFail($userId));
        } catch (ValidationException $exception) {
            $this->addError('user', $exception->validator->errors()->first() ?: $exception->getMessage());

            return;
        }

        unset($this->users);
        Flux::toast(text: __('Usuario eliminado.'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.users.index');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingUserId', 'name', 'email', 'password', 'areaId',
            'jobTitle', 'isActive', 'selectedRoles', 'selectedPermissions',
        ]);
        $this->isActive = true;
        $this->resetErrorBag();
    }

    private function closeForm(): void
    {
        Flux::modal('user-form')->close();
        $this->showForm = false;
    }
}
