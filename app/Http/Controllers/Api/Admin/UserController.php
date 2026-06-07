<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->payload($u));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! empty($data['roles'])) {
            $user->assign($data['roles']);
        }

        return response()->json($this->payload($user), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return response()->json($this->payload($user));
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->id === $user->id, 422, 'No puedes eliminar tu propia cuenta.');

        $user->delete();

        return response()->noContent();
    }

    /**
     * Replace the user's roles with the given list.
     */
    public function syncRoles(Request $request, User $user)
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string'],
        ]);

        $current = $user->getRoles()->all();
        if (! empty($current)) {
            $user->retract($current);
        }
        if (! empty($data['roles'])) {
            $user->assign($data['roles']);
        }

        return response()->json($this->payload($user->fresh()));
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoles(),
        ];
    }
}
