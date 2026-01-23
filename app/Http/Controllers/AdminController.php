<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * List all users.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Optional search by email
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('email', 'like', "%{$search}%");
        }

        // Optional filter by role
        if ($request->has('role')) {
            $role = $request->get('role');
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        $users = $query->orderBy('created_at', 'desc')
            ->select('id', 'email', 'role', 'email_verified_at', 'created_at', 'updated_at')
            ->paginate(20);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get a specific user.
     */
    public function show(Request $request, User $user)
    {
        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role ?? 'merchant',
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['merchant', 'support', 'admin', 'pro', 'enterprise']),
            ],
        ]);

        DB::transaction(function () use ($request, $user) {
            $updated = [];

            if ($request->has('email')) {
                $oldEmail = $user->email;
                $user->email = $request->email;
                $updated['email'] = $oldEmail;
            }

            if ($request->has('role')) {
                $oldRole = $user->role ?? 'merchant';
                $user->role = $request->role;
                $updated['role'] = $oldRole;
            }

            $user->save();

            Log::info('Admin updated user', [
                'admin_id' => $request->user()->id,
                'user_id' => $user->id,
                'updated_fields' => array_keys($updated),
                'old_values' => $updated,
            ]);
        });

        return response()->json([
            'message' => 'User updated successfully',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role ?? 'merchant',
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user)
    {
        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $userId = $user->id;
        $userEmail = $user->email;

        DB::transaction(function () use ($user) {
            // Delete user's stores (cascade will handle related records)
            $user->stores()->delete();
            
            // Delete the user
            $user->delete();
        });

        Log::info('Admin deleted user', [
            'admin_id' => $request->user()->id,
            'deleted_user_id' => $userId,
            'deleted_user_email' => $userEmail,
        ]);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}



