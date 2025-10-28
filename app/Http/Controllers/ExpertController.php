<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Expert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ExpertController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $experts = User::whereHas('roles', function ($query) {
                $query->where('name', 'Expert');
            })
                ->with('expert')
                ->when($request->search, function ($query, $search) {
                    return $query->where('full_name', 'like', "%$search%");
                })
                ->latest()
                ->paginate(10);

            // Transform data
            $formattedExperts = $experts->getCollection()->map(function ($user) {
                return [
                    'expert_id' => $user->expert->id ?? null,
                    'user_id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'type' => $user->type ?? null,
                    'course_is_assigned' => $user->expert->courses->isNotEmpty() ?? false,
                ];
            });

            $paginatedExperts = $experts->toArray();
            $paginatedExperts['data'] = $formattedExperts;

            return response()->json([
                'status' => 'success',
                'message' => 'Experts fetched successfully',
                'data' => $paginatedExperts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
        $validated = $request->validate([
            'full_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
            'qualification' => 'nullable|string',
            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|string',
            'expertise' => 'nullable|array',
            'expertise.*' => 'nullable|string',
            'certifications' => 'nullable|array',
            'certifications.*' => 'nullable|string',
            'bio' => 'nullable|string',
            'status' => 'required|boolean',
        ]);
        try {
            DB::beginTransaction();
            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'username' => User::generateUniqueUsername($validated['full_name']),
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
                'type' => 'expert',
            ]);

            $user->assignRole('Expert');

            $expert = Expert::create([
                'user_id' => $user->id,
                'qualification' => $validated['qualification'],
                'social_links' => $validated['social_links'],
                'expertise' => $validated['expertise'],
                'certifications' => $validated['certifications'],
                'bio' => $validated['bio'],
                'status' => $validated['status'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Expert created successfully',
                'data' => [
                    'expert_id' => $expert->id,
                    'user_id' => $user->id,
                    'full_name' => $expert->user->full_name,
                    'email' => $expert->user->email,
                    'phone_number' => $expert->user->phone_number,
                    'username' => $expert->user->username,
                    'qualification' => $expert->qualification,
                    'social_links' => $expert->social_links,
                    'expertise' => $expert->expertise,
                    'certifications' => $expert->certifications,
                    'bio' => $expert->bio,
                    'type' => $expert->user->type,
                    'is_assigned' => $expert->is_assigned,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Expert $expert)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Expert fetched successfully',
            'data' => [
                'expert_id' => $expert->id,
                'user_id' => $expert->user->id,
                'full_name' => $expert->user->full_name,
                'email' => $expert->user->email,
                'phone_number' => $expert->user->phone_number,
                'username' => $expert->user->username,
                'qualification' => $expert->qualification,
                'social_links' => $expert->social_links ?? [],
                'expertise' => $expert->expertise ?? [],
                'certifications' => $expert->certifications ?? [],
                'bio' => $expert->bio,
                'type' => $expert->user->type ?? null,
                'is_assigned' => $expert->is_assigned,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(Request $request, Expert $expert)
    {
        $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
        $validated = $request->validate([
            'full_name' => 'sometimes|string|nullable',
            'email' => 'sometimes|email|nullable|unique:users,email,' . $expert->user_id,
            'phone_number' => 'sometimes|string|nullable|unique:users,phone_number,' . $expert->user_id,
            'password' => 'sometimes|string|nullable|min:8',
            'qualification' => 'sometimes|string|nullable',
            'social_links' => 'sometimes|array|nullable',
            'social_links.*' => 'sometimes|string|nullable',
            'expertise' => 'sometimes|array|nullable',
            'expertise.*' => 'sometimes|string|nullable',
            'certifications' => 'sometimes|array|nullable',
            'certifications.*' => 'sometimes|string|nullable',
            'bio' => 'sometimes|string|nullable',
            'status' => 'sometimes|boolean|nullable',
        ]);

        try {
            DB::beginTransaction();
            $expert->user->update([
                'full_name' => $validated['full_name'] ?? $expert->user->full_name,
                'email' => $validated['email'] ?? $expert->user->email,
                'phone_number' => $validated['phone_number'] ?? $expert->user->phone_number,
                'status' => $request['status'],
                'type' => 'expert',
                'password' => isset($validated['password'])
                    ? Hash::make($validated['password'])
                    : $expert->user->password,
            ]);

            $expert->update([
                'qualification' => $validated['qualification'] ?? $expert->qualification,
                'social_links' => $validated['social_links'] ?? $expert->social_links,
                'expertise' => $validated['expertise'] ?? $expert->expertise,
                'certifications' => $validated['certifications'] ?? $expert->certifications,
                'bio' => $validated['bio'] ?? $expert->bio,
                'status' => $request['status'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Expert updated successfully',
                'data' => [
                    'expert_id' => $expert->id,
                    'user_id' => $expert->user->id,
                    'full_name' => $expert->user->full_name,
                    'email' => $expert->user->email,
                    'phone_number' => $expert->user->phone_number,
                    'username' => $expert->user->username,
                    'qualification' => $expert->qualification,
                    'social_links' => $expert->social_links ?? [],
                    'expertise' => $expert->expertise ?? [],
                    'certifications' => $expert->certifications ?? [],
                    'bio' => $expert->bio,
                    'type' => $expert->user->type ?? null,
                    'is_assigned' => $expert->is_assigned,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }

    }





    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expert $expert)
    {
        try {
            DB::beginTransaction();
            $expert->user->delete();
            $expert->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Expert deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getExpertByCourse(Request $request, Course $course)
    {
        $expert = $course->expert;

        return response()->json([
            'status' => 'success',
            'message' => 'Expert fetched successfully',
            'data' => [
                'expert_id' => $expert->id,
                'user_id' => $expert->user->id,
                'full_name' => $expert->user->full_name,
                'email' => $expert->user->email,
                'phone_number' => $expert->user->phone_number,
                'username' => $expert->user->username,
                'qualification' => $expert->qualification,
                'social_links' => $expert->social_links ?? [],
                'expertise' => $expert->expertise ?? [],
                'certifications' => $expert->certifications ?? [],
                'bio' => $expert->bio,
                'type' => $expert->user->type ?? null,
                'is_assigned' => $expert->is_assigned,
            ],
        ], 200);
    }

    public function indexExpertsWithoutCourse()
    {
        try {
            $experts = User::whereHas('roles', function ($query) {
                $query->where('name', 'Expert');
            })->whereHas('expert', function ($q) {
                $q->doesntHave('courses');
            })
                ->latest()
                ->paginate(10);
            $formattedExperts = $experts->getCollection()->map(function ($user) {
                return [
                    'expert_id' => $user->expert->id ?? null,
                    'user_id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'is_assigned' => $user->expert->is_assigned ?? false,
                ];
            });

            $paginatedExperts = $experts->toArray();
            $paginatedExperts['data'] = $formattedExperts->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Experts fetched successfully',
                'data' => $paginatedExperts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }

    }
}
