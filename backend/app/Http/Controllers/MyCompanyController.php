<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Http\Requests\MyCompany\CreateEmployeeRequest;
use App\Http\Requests\MyCompany\InviteEmployeeRequest;
use App\Http\Requests\MyCompany\StoreCategoryRequest;
use App\Http\Requests\MyCompany\StoreServiceRequest;
use App\Http\Requests\MyCompany\UpdateCategoryRequest;
use App\Http\Requests\MyCompany\UpdateCompanyRequest;
use App\Http\Requests\MyCompany\UpdateEmployeeRequest;
use App\Http\Requests\MyCompany\UpdateOpeningHoursRequest;
use App\Http\Requests\MyCompany\UpdateServiceRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\MyCompanyResource;
use App\Http\Resources\OpeningHourResource;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\Company;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MyCompanyController extends Controller
{
    // -------------------------------------------------------------------------
    // Private helper — resolve and verify company ownership
    // -------------------------------------------------------------------------

    /**
     * Returns the Company owned by the authenticated user, or a 403/404 response.
     * The caller must check `instanceof JsonResponse` before proceeding.
     */
    private function resolveOwnedCompany(): Company|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = CompanyUser::where('user_id', $user->id)
            ->where('role', CompanyRole::Owner->value)
            ->first();

        if (! $pivot) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own a company.',
            ], 403);
        }

        $company = Company::find($pivot->company_id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        return $company;
    }

    /**
     * Verify a resource (category / service / employee) belongs to the company.
     */
    private function notFound(string $resource): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => ucfirst($resource) . ' not found.',
        ], 404);
    }

    // =========================================================================
    // Company Profile
    // =========================================================================

    /**
     * GET /api/my-company
     */
    public function show(): MyCompanyResource|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $company->load('openingHours');

        return new MyCompanyResource($company);
    }

    /**
     * PUT /api/my-company
     */
    public function update(UpdateCompanyRequest $request): MyCompanyResource|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $company->update($request->validated());

        return new MyCompanyResource($company->fresh('openingHours'));
    }

    // =========================================================================
    // Service Categories
    // =========================================================================

    /**
     * GET /api/my-company/categories
     */
    public function listCategories(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $categories = ServiceCategory::where('company_id', $company->id)
            ->with('services')
            ->orderBy('name')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }

    /**
     * POST /api/my-company/categories
     */
    public function storeCategory(StoreCategoryRequest $request): ServiceCategoryResource|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $category = ServiceCategory::create([
            'company_id' => $company->id,
            'name'       => $request->validated('name'),
        ]);

        $category->load('services');

        return (new ServiceCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/my-company/categories/{id}
     */
    public function updateCategory(UpdateCategoryRequest $request, int $id): ServiceCategoryResource|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $category = ServiceCategory::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $category) {
            return $this->notFound('category');
        }

        $category->update($request->validated());
        $category->load('services');

        return new ServiceCategoryResource($category);
    }

    /**
     * DELETE /api/my-company/categories/{id}
     *
     * Deletes the category and all its services (cascade via DB or explicit delete).
     */
    public function destroyCategory(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $category = ServiceCategory::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $category) {
            return $this->notFound('category');
        }

        // Explicitly delete services to fire model events if any are registered
        $category->services()->delete();
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted.',
        ]);
    }

    // =========================================================================
    // Services
    // =========================================================================

    /**
     * POST /api/my-company/services
     */
    public function storeService(StoreServiceRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        // Verify the category belongs to this company
        $categoryBelongs = ServiceCategory::where('id', $request->validated('category_id'))
            ->where('company_id', $company->id)
            ->exists();

        if (! $categoryBelongs) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        $service = Service::create(array_merge(
            $request->validated(),
            ['company_id' => $company->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Service created.',
            'data'    => $this->serviceArray($service),
        ], 201);
    }

    /**
     * PUT /api/my-company/services/{id}
     */
    public function updateService(UpdateServiceRequest $request, int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $service = Service::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $service) {
            return $this->notFound('service');
        }

        // If category_id is being changed, ensure the new category belongs to this company
        if ($request->has('category_id')) {
            $categoryBelongs = ServiceCategory::where('id', $request->validated('category_id'))
                ->where('company_id', $company->id)
                ->exists();

            if (! $categoryBelongs) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found.',
                ], 404);
            }
        }

        $service->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Service updated.',
            'data'    => $this->serviceArray($service->fresh()),
        ]);
    }

    /**
     * DELETE /api/my-company/services/{id}
     */
    public function destroyService(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $service = Service::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $service) {
            return $this->notFound('service');
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted.',
        ]);
    }

    // =========================================================================
    // Employees
    // =========================================================================

    /**
     * GET /api/my-company/employees
     */
    public function listEmployees(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $employees = CompanyUser::where('company_id', $company->id)
            ->with(['user', 'services'])
            ->get();

        return EmployeeResource::collection($employees);
    }

    /**
     * POST /api/my-company/employees/invite
     *
     * Invite an existing user by email. If not found, return a 422 suggesting
     * the owner create an account for them instead.
     */
    public function inviteEmployee(InviteEmployeeRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address. Use the "create employee" endpoint to create a new account.',
            ], 422);
        }

        // Prevent duplicate membership
        $alreadyMember = CompanyUser::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyMember) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a member of your company.',
            ], 422);
        }

        $pivot = CompanyUser::create([
            'company_id'  => $company->id,
            'user_id'     => $user->id,
            'role'        => $request->validated('role', CompanyRole::Employee->value),
            'specialties' => $request->validated('specialties', []),
            'is_active'   => true,
        ]);

        $pivot->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Employee added.',
            'data'    => new EmployeeResource($pivot),
        ], 201);
    }

    /**
     * POST /api/my-company/employees/create
     *
     * Create a new User account and add them as an employee in one transaction.
     */
    public function createEmployee(CreateEmployeeRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $pivot = DB::transaction(function () use ($request, $company): CompanyUser {
            $user = User::create([
                'first_name' => $request->validated('first_name'),
                'last_name'  => $request->validated('last_name'),
                'email'      => $request->validated('email'),
                'phone'      => $request->validated('phone'),
                'password'   => Hash::make($request->validated('password')),
                'role'       => UserRole::User,
            ]);

            return CompanyUser::create([
                'company_id'  => $company->id,
                'user_id'     => $user->id,
                'role'        => $request->validated('role', CompanyRole::Employee->value),
                'specialties' => $request->validated('specialties', []),
                'is_active'   => true,
            ]);
        });

        $pivot->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Employee account created and added to your company.',
            'data'    => new EmployeeResource($pivot),
        ], 201);
    }

    /**
     * PUT /api/my-company/employees/{id}
     *
     * Update role, is_active, or specialties for a company_user row.
     * Cannot demote/change the owner themselves.
     */
    public function updateEmployee(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $pivot = CompanyUser::where('id', $id)
            ->where('company_id', $company->id)
            ->with('user')
            ->first();

        if (! $pivot) {
            return $this->notFound('employee');
        }

        // Owner can modify everything, including their own record

        $validated = $request->validated();

        // Extract service_ids before passing the rest to update()
        $serviceIds = null;
        if (array_key_exists('service_ids', $validated)) {
            $serviceIds = $validated['service_ids'];
            unset($validated['service_ids']);
        }

        if (! empty($validated)) {
            $pivot->update($validated);
        }

        // Sync services when service_ids was explicitly sent (even if empty array)
        if ($serviceIds !== null) {
            // Build sync payload: each service gets an empty pivot (no custom duration)
            $syncPayload = array_fill_keys($serviceIds, ['duration' => null]);
            $pivot->services()->sync($syncPayload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee updated.',
            'data'    => new EmployeeResource($pivot->fresh(['user', 'services'])),
        ]);
    }

    /**
     * DELETE /api/my-company/employees/{id}
     *
     * Remove an employee from the company (deletes the pivot row, not the user).
     */
    public function destroyEmployee(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $pivot = CompanyUser::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $pivot) {
            return $this->notFound('employee');
        }

        // Prevent the owner from removing themselves
        if ($pivot->role === CompanyRole::Owner && $pivot->user_id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove yourself as the company owner.',
            ], 403);
        }

        $pivot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee removed from company.',
        ]);
    }

    // =========================================================================
    // Opening Hours
    // =========================================================================

    /**
     * GET /api/my-company/hours
     */
    public function listHours(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $hours = CompanyOpeningHour::where('company_id', $company->id)
            ->orderBy('day_of_week')
            ->get();

        return OpeningHourResource::collection($hours);
    }

    /**
     * PUT /api/my-company/hours
     *
     * Bulk upsert: each entry in `hours[]` is matched by day_of_week and
     * created-or-updated. Days not present in the payload are left untouched.
     */
    public function updateHours(UpdateOpeningHoursRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        DB::transaction(function () use ($request, $company): void {
            foreach ($request->validated('hours') as $entry) {
                $isClosed = (bool) $entry['is_closed'];

                CompanyOpeningHour::updateOrCreate(
                    [
                        'company_id'  => $company->id,
                        'day_of_week' => $entry['day_of_week'],
                    ],
                    [
                        'open_time'  => $isClosed ? null : ($entry['open_time'] ?? null),
                        'close_time' => $isClosed ? null : ($entry['close_time'] ?? null),
                        'is_closed'  => $isClosed,
                    ]
                );
            }
        });

        $hours = CompanyOpeningHour::where('company_id', $company->id)
            ->orderBy('day_of_week')
            ->get();

        return OpeningHourResource::collection($hours);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function serviceArray(Service $service): array
    {
        return [
            'id'              => (string) $service->id,
            'categoryId'      => (string) $service->category_id,
            'name'            => $service->name,
            'description'     => $service->description,
            'durationMinutes' => (int) $service->duration,
            'price'           => (float) $service->price,
            'isActive'        => (bool) $service->is_active,
        ];
    }
}
