<?php

namespace App\Livewire\Admin\Roles;

use App\Actions\Admin\DeleteRole;
use App\Actions\Admin\SaveRole;
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

    public int|string|null $editingRoleId = null;

    public string $name = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Role::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    #[Computed]
    public function roles()
    {
        $search = trim($this->search);

        return Role::query()
            ->with('permissions')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10);
    }

    /** @return Collection<int, Permission> */
    #[Computed]
    public function permissions()
    {
        return Permission::query()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Role::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int|string $roleId): void
    {
        $role = Role::findOrFail($roleId);
        Gate::authorize('update', $role);

        $this->resetForm();
        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions()->pluck('name')->all();
        $this->showForm = true;
    }

    public function save(SaveRole $action): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = $this->editingRoleId === null ? null : Role::findOrFail($this->editingRoleId);

        try {
            $action->handle([
                'name' => $this->name,
                'permissions' => $this->selectedPermissions,
            ], $role);
        } catch (ValidationException $exception) {
            $this->addError('role', $exception->validator->errors()->first() ?: $exception->getMessage());

            return;
        }

        unset($this->roles);
        $this->closeForm();
        Flux::toast(text: __('Rol guardado correctamente.'), variant: 'success');
    }

    public function delete(int $roleId, DeleteRole $action): void
    {
        try {
            $action->handle(Role::findOrFail($roleId));
        } catch (ValidationException $exception) {
            $this->addError('role', $exception->validator->errors()->first() ?: $exception->getMessage());

            return;
        }

        unset($this->roles);
        Flux::toast(text: __('Rol eliminado.'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.roles.index');
    }

    private function resetForm(): void
    {
        $this->reset(['editingRoleId', 'name', 'selectedPermissions']);
        $this->resetErrorBag();
    }

    private function closeForm(): void
    {
        Flux::modal('role-form')->close();
        $this->showForm = false;
    }
}
